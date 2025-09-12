<?php

namespace App\Controllers;

use App\Utils\FileUpload;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ProductController
{
    private $db;
    private $fileUpload;

    public function __construct($database)
    {
        $this->db = $database->getConnection();
        $this->fileUpload = new FileUpload();
    }

    /**
     * Método helper para obtener datos del request de forma segura
     */
    private function getRequestData(Request $request): array 
    {
        $contentType = $request->getHeaderLine('Content-Type');
        
        // Log para debug
        error_log("Content-Type: " . $contentType);
        
        // Si es multipart/form-data (con archivos)
        if (strpos($contentType, 'multipart/form-data') !== false) {
            $parsedBody = $request->getParsedBody();
            error_log("Multipart ParsedBody: " . print_r($parsedBody, true));
            return is_array($parsedBody) ? $parsedBody : [];
        }
        
        // Si es application/json
        if (strpos($contentType, 'application/json') !== false) {
            $body = $request->getBody()->getContents();
            error_log("JSON Body: " . $body);
            
            if (!empty($body)) {
                $data = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                    error_log("JSON Parsed successfully: " . print_r($data, true));
                    return $data;
                } else {
                    error_log("JSON Parse Error: " . json_last_error_msg());
                }
            }
        }
        
        // Fallback para form-urlencoded
        $parsedBody = $request->getParsedBody();
        error_log("Fallback ParsedBody: " . print_r($parsedBody, true));
        
        if (is_array($parsedBody)) {
            return $parsedBody;
        }
        
        // Si todo falla, devolver array vacío
        error_log("No valid data found, returning empty array");
        return [];
    }

    /**
     * Agregar URLs a las imágenes de los productos
     */
    private function addImageUrls($products): array {
        if (!is_array($products)) {
            return $products;
        }

        foreach ($products as &$product) {
            if (isset($product['primary_image']) && !empty($product['primary_image'])) {
                $product['primary_image_url'] = $this->fileUpload->getImageUrl($product['primary_image']);
            }

            // Si el producto tiene imágenes en array
            if (isset($product['images']) && is_array($product['images'])) {
                foreach ($product['images'] as &$image) {
                    if (isset($image['image_path']) && !empty($image['image_path'])) {
                        $image['image_url'] = $this->fileUpload->getImageUrl($image['image_path']);
                    }
                }
            }
        }

        return $products;
    }

    public function getAll(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();

        $page = max(1, (int)($params['page'] ?? 1));
        $limit = min(100, max(1, (int)($params['limit'] ?? 10)));
        $offset = ($page - 1) * $limit;

        $search = $params['search'] ?? '';
        $category = $params['category'] ?? '';
        $status = $params['status'] ?? '';

        $where = [];
        $bindings = [];

        if ($search) {
            $where[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
            $searchTerm = "%{$search}%";
            $bindings = array_merge($bindings, [$searchTerm, $searchTerm, $searchTerm]);
        }

        if ($category) {
            $where[] = "p.category_id = ?";
            $bindings[] = $category;
        }

        if ($status) {
            $where[] = "p.status = ?";
            $bindings[] = $status;
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Contar total
        $countSql = "SELECT COUNT(*) as total FROM products p {$whereClause}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($bindings);
        $total = $countStmt->fetch()['total'];

        // Obtener productos
        $sql = "SELECT p.*, c.name as category_name,
                       (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                {$whereClause}
                ORDER BY p.id DESC 
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        $products = $stmt->fetchAll();

        // Agregar URLs de imágenes
        $products = $this->addImageUrls($products);

        $result = [
            'data' => $products,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ];

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getOne(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];

        $sql = "SELECT p.*, c.name as category_name FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.id = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $product = $stmt->fetch();

        if (!$product) {
            $response->getBody()->write(json_encode(['error' => 'Product not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Obtener imágenes
        $imgStmt = $this->db->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order, id");
        $imgStmt->execute([$id]);
        $product['images'] = $imgStmt->fetchAll();

        // Agregar URLs de imágenes
        $product = $this->addImageUrls($product);

        $response->getBody()->write(json_encode($product));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response
    {
        try {
            $data = $this->getRequestData($request);
            $uploadedFiles = $request->getUploadedFiles();
            
            error_log("=== CREATE PRODUCT START ===");
            error_log("Create - Data: " . print_r($data, true));
            error_log("Create - Files: " . print_r(array_keys($uploadedFiles), true));

            // Validar campos requeridos
            $required = ['name', 'sku', 'price'];
            $missingFields = [];
            
            foreach ($required as $field) {
                if (!isset($data[$field]) || trim($data[$field]) === '') {
                    $missingFields[] = $field;
                }
            }

            if (!empty($missingFields)) {
                $response->getBody()->write(json_encode([
                    'error' => 'Missing required fields: ' . implode(', ', $missingFields),
                    'required_fields' => $required,
                    'received_data' => array_keys($data)
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Validar tipos de datos
            if (!is_numeric($data['price']) || $data['price'] <= 0) {
                $response->getBody()->write(json_encode(['error' => 'Price must be a positive number']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Verificar SKU único
            $stmt = $this->db->prepare("SELECT id FROM products WHERE sku = ?");
            $stmt->execute([$data['sku']]);
            if ($stmt->fetch()) {
                $response->getBody()->write(json_encode(['error' => 'SKU already exists']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $this->db->beginTransaction();

            $slug = $this->generateSlug($data['name']);

            $sql = "INSERT INTO products (name, slug, description, short_description, sku, price, sale_price, 
                    stock, min_stock, status, featured, weight, dimensions, category_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $data['name'],
                $slug,
                $data['description'] ?? null,
                $data['short_description'] ?? null,
                $data['sku'],
                (float)$data['price'],
                isset($data['sale_price']) && $data['sale_price'] !== '' ? (float)$data['sale_price'] : null,
                isset($data['stock']) ? (int)$data['stock'] : 0,
                isset($data['min_stock']) ? (int)$data['min_stock'] : 0,
                $data['status'] ?? 'active',
                isset($data['featured']) ? ($data['featured'] === 'true' || $data['featured'] === '1' || $data['featured'] === true ? 1 : 0) : 0,
                isset($data['weight']) && $data['weight'] !== '' ? (float)$data['weight'] : null,
                $data['dimensions'] ?? null,
                isset($data['category_id']) && $data['category_id'] !== '' ? (int)$data['category_id'] : null
            ];

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $productId = $this->db->lastInsertId();

            error_log("Product created with ID: " . $productId);

            // Manejar imagen si existe
            $imageProcessed = false;
            if (isset($uploadedFiles['image']) && $uploadedFiles['image']->getError() === UPLOAD_ERR_OK) {
                $uploadedFile = $uploadedFiles['image'];
                error_log("Processing image upload for product: " . $productId);
                
                try {
                    $imagePath = $this->fileUpload->uploadFile($uploadedFile, 'products');
                    error_log("Image path returned: " . $imagePath);
                    
                    // Guardar imagen en la base de datos
                    $imgSql = "INSERT INTO product_images (product_id, image_path, alt_text, is_primary, sort_order) VALUES (?, ?, ?, ?, ?)";
                    $imgStmt = $this->db->prepare($imgSql);
                    $result = $imgStmt->execute([
                        $productId,
                        $imagePath,
                        $data['name'],
                        1, // Primera imagen es primaria
                        0
                    ]);
                    
                    if ($result) {
                        error_log("Image saved successfully in database: " . $imagePath);
                        $imageProcessed = true;
                    } else {
                        error_log("Failed to save image in database");
                    }
                } catch (\Exception $e) {
                    error_log("Image upload error: " . $e->getMessage());
                    // No fallar la creación del producto por un error de imagen
                }
            } else if (isset($uploadedFiles['image'])) {
                error_log("Image upload error code: " . $uploadedFiles['image']->getError());
            } else {
                error_log("No image file found in upload");
            }

            $this->db->commit();
            error_log("=== CREATE PRODUCT SUCCESS ===");

            $resultMessage = 'Product created successfully';
            if ($imageProcessed) {
                $resultMessage .= ' with image';
            }

            $response->getBody()->write(json_encode([
                'message' => $resultMessage,
                'product_id' => $productId,
                'image_processed' => $imageProcessed
            ]));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            error_log("Product creation error: " . $e->getMessage());
            error_log("Product creation stack trace: " . $e->getTraceAsString());
            $response->getBody()->write(json_encode([
                'error' => 'Internal server error: ' . $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Actualizar producto - AHORA AGREGA IMÁGENES EN LUGAR DE REEMPLAZARLAS
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];
            $data = $this->getRequestData($request);
            $uploadedFiles = $request->getUploadedFiles();

            error_log("=== UPDATE PRODUCT START ===");
            error_log("Product ID: " . $id);
            error_log("Data received: " . print_r($data, true));
            error_log("Files received: " . print_r(array_keys($uploadedFiles), true));

            // Verificar que el producto existe
            $checkStmt = $this->db->prepare("SELECT id FROM products WHERE id = ?");
            $checkStmt->execute([$id]);
            if (!$checkStmt->fetch()) {
                $response->getBody()->write(json_encode(['error' => 'Product not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $this->db->beginTransaction();

            // Preparar campos para actualizar
            $updateFields = [];
            $params = [];

            $allowedFields = [
                'name' => 'string',
                'description' => 'string', 
                'short_description' => 'string',
                'sku' => 'string',
                'price' => 'float',
                'sale_price' => 'float',
                'stock' => 'int',
                'min_stock' => 'int',
                'status' => 'string',
                'featured' => 'bool',
                'weight' => 'float',
                'dimensions' => 'string',
                'category_id' => 'int'
            ];

            foreach ($allowedFields as $field => $type) {
                if (array_key_exists($field, $data) && $data[$field] !== '') {
                    // Validaciones especiales
                    if ($field === 'sku' && !empty($data[$field])) {
                        $skuStmt = $this->db->prepare("SELECT id FROM products WHERE sku = ? AND id != ?");
                        $skuStmt->execute([$data[$field], $id]);
                        if ($skuStmt->fetch()) {
                            $this->db->rollback();
                            $response->getBody()->write(json_encode(['error' => 'SKU already exists']));
                            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                        }
                    }

                    if ($field === 'price' && (!is_numeric($data[$field]) || $data[$field] <= 0)) {
                        $this->db->rollback();
                        $response->getBody()->write(json_encode(['error' => 'Price must be a positive number']));
                        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                    }
                    
                    $updateFields[] = "{$field} = ?";
                    
                    // Convertir tipos
                    switch ($type) {
                        case 'int':
                            $params[] = (int)$data[$field];
                            break;
                        case 'float':
                            $params[] = (float)$data[$field];
                            break;
                        case 'bool':
                            // Convertir string 'true'/'false' o valores booleanos
                            if (is_string($data[$field])) {
                                $params[] = ($data[$field] === 'true' || $data[$field] === '1') ? 1 : 0;
                            } else {
                                $params[] = $data[$field] ? 1 : 0;
                            }
                            break;
                        default:
                            $params[] = $data[$field];
                    }
                }
            }

            // Generar slug si se actualiza el nombre
            if (isset($data['name']) && !empty($data['name'])) {
                $updateFields[] = "slug = ?";
                $params[] = $this->generateSlug($data['name']);
            }

            // Actualizar producto si hay campos
            if (!empty($updateFields)) {
                $updateFields[] = "updated_at = CURRENT_TIMESTAMP";
                $params[] = $id;

                $sql = "UPDATE products SET " . implode(', ', $updateFields) . " WHERE id = ?";
                error_log("Update SQL: " . $sql);
                error_log("Update Params: " . print_r($params, true));
                
                $stmt = $this->db->prepare($sql);
                $result = $stmt->execute($params);
                
                if (!$result) {
                    error_log("Failed to update product data");
                    $this->db->rollback();
                    $response->getBody()->write(json_encode(['error' => 'Failed to update product data']));
                    return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
                }
                
                error_log("Product data updated successfully");
            }

            // PROCESAMIENTO DE IMAGEN - AGREGAR NUEVA SIN ELIMINAR EXISTENTES
            $imageProcessed = false;
            $imagesAdded = 0;
            
            // Procesar imagen principal si existe
            if (isset($uploadedFiles['image']) && $uploadedFiles['image']->getError() === UPLOAD_ERR_OK) {
                $uploadedFile = $uploadedFiles['image'];
                error_log("Processing main image upload for product update: " . $id);
                
                try {
                    // Subir nueva imagen
                    $imagePath = $this->fileUpload->uploadFile($uploadedFile, 'products');
                    error_log("New image uploaded successfully: " . $imagePath);
                    
                    // Obtener el orden más alto actual
                    $maxOrderStmt = $this->db->prepare("SELECT COALESCE(MAX(sort_order), -1) as max_order FROM product_images WHERE product_id = ?");
                    $maxOrderStmt->execute([$id]);
                    $maxOrder = $maxOrderStmt->fetch()['max_order'];
                    $newOrder = $maxOrder + 1;
                    
                    // Verificar si ya existe una imagen primaria
                    $primaryStmt = $this->db->prepare("SELECT COUNT(*) as count FROM product_images WHERE product_id = ? AND is_primary = 1");
                    $primaryStmt->execute([$id]);
                    $hasPrimary = $primaryStmt->fetch()['count'] > 0;
                    
                    // Insertar nueva imagen
                    $imgSql = "INSERT INTO product_images (product_id, image_path, alt_text, is_primary, sort_order) VALUES (?, ?, ?, ?, ?)";
                    $imgStmt = $this->db->prepare($imgSql);
                    $imgResult = $imgStmt->execute([
                        $id,
                        $imagePath,
                        $data['name'] ?? 'Product image',
                        $hasPrimary ? 0 : 1, // Solo es primaria si no hay otra
                        $newOrder
                    ]);
                    
                    if ($imgResult) {
                        error_log("New image added successfully: " . $imagePath);
                        $imageProcessed = true;
                        $imagesAdded++;
                    } else {
                        error_log("Failed to save new image in database");
                    }
                    
                } catch (\Exception $e) {
                    error_log("Main image upload error: " . $e->getMessage());
                    error_log("Main image upload stack trace: " . $e->getTraceAsString());
                }
            }
            
            // Procesar imágenes adicionales si existen (images[])
            if (isset($uploadedFiles['images']) && is_array($uploadedFiles['images'])) {
                error_log("Processing additional images: " . count($uploadedFiles['images']));
                
                foreach ($uploadedFiles['images'] as $index => $uploadedFile) {
                    if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
                        try {
                            // Subir imagen adicional
                            $imagePath = $this->fileUpload->uploadFile($uploadedFile, 'products');
                            error_log("Additional image {$index} uploaded successfully: " . $imagePath);
                            
                            // Obtener el orden más alto actual
                            $maxOrderStmt = $this->db->prepare("SELECT COALESCE(MAX(sort_order), -1) as max_order FROM product_images WHERE product_id = ?");
                            $maxOrderStmt->execute([$id]);
                            $maxOrder = $maxOrderStmt->fetch()['max_order'];
                            $newOrder = $maxOrder + 1;
                            
                            // Insertar imagen adicional (nunca es primaria)
                            $imgSql = "INSERT INTO product_images (product_id, image_path, alt_text, is_primary, sort_order) VALUES (?, ?, ?, ?, ?)";
                            $imgStmt = $this->db->prepare($imgSql);
                            $imgResult = $imgStmt->execute([
                                $id,
                                $imagePath,
                                $data['name'] ?? 'Product image',
                                0, // Nunca es primaria
                                $newOrder
                            ]);
                            
                            if ($imgResult) {
                                error_log("Additional image {$index} added successfully: " . $imagePath);
                                $imageProcessed = true;
                                $imagesAdded++;
                            } else {
                                error_log("Failed to save additional image {$index} in database");
                            }
                            
                        } catch (\Exception $e) {
                            error_log("Additional image {$index} upload error: " . $e->getMessage());
                        }
                    } else {
                        error_log("Additional image {$index} upload error code: " . $uploadedFile->getError());
                    }
                }
            }
            
            // Log si no se encontraron archivos
            if (!isset($uploadedFiles['image']) && !isset($uploadedFiles['images'])) {
                error_log("No image files found in upload - this is normal for text-only updates");
            }

            $this->db->commit();
            error_log("=== UPDATE PRODUCT SUCCESS ===");

            $resultMessage = 'Product updated successfully';
            if ($imageProcessed) {
                $resultMessage .= " with {$imagesAdded} new image" . ($imagesAdded > 1 ? 's' : '');
            }

            $response->getBody()->write(json_encode([
                'message' => $resultMessage,
                'image_processed' => $imageProcessed,
                'images_added' => $imagesAdded
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            error_log("Product update error: " . $e->getMessage());
            error_log("Product update stack trace: " . $e->getTraceAsString());
            $response->getBody()->write(json_encode([
                'error' => 'Update failed: ' . $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];

        // Verificar que el producto existe
        $checkStmt = $this->db->prepare("SELECT id FROM products WHERE id = ?");
        $checkStmt->execute([$id]);
        if (!$checkStmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'Product not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        try {
            $this->db->beginTransaction();

            // Obtener y eliminar imágenes del filesystem
            $imgStmt = $this->db->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
            $imgStmt->execute([$id]);
            $images = $imgStmt->fetchAll();

            foreach ($images as $image) {
                $this->fileUpload->deleteFile($image['image_path']);
                error_log("Deleted image file: " . $image['image_path']);
            }

            // Eliminar producto (las imágenes se eliminan por CASCADE)
            $stmt = $this->db->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$id]);

            $this->db->commit();

            $response->getBody()->write(json_encode(['message' => 'Product deleted successfully']));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Product deletion error: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Failed to delete product']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Generar slug a partir del nombre
     */
    private function generateSlug($name)
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }

    /**
     * Helper method para obtener mensajes de error de upload
     */
    private function getUploadErrorMessage($errorCode): string {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'File exceeds upload_max_filesize directive';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File exceeds MAX_FILE_SIZE directive';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'Upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }
}