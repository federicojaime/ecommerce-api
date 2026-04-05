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
        // Permitir límite más alto para ordenamiento (hasta 500)
        $limit = min(500, max(1, (int)($params['limit'] ?? 10)));
        $offset = ($page - 1) * $limit;

        $search = $params['search'] ?? '';
        $category = $params['category'] ?? '';
        $status = $params['status'] ?? '';
        $archived = $params['archived'] ?? '';

        $where = [];
        $bindings = [];

        // Verificar si existe la columna archived
        $hasArchived = false;
        try {
            $checkStmt = $this->db->query("SHOW COLUMNS FROM products LIKE 'archived'");
            $hasArchived = $checkStmt->rowCount() > 0;

            // Si no existe, crearla
            if (!$hasArchived) {
                $this->db->exec("ALTER TABLE products ADD COLUMN archived TINYINT(1) NOT NULL DEFAULT 0");
                $hasArchived = true;
                error_log("Created 'archived' column in products table");
            }
        } catch (\Exception $e) {
            error_log("Error checking/creating archived column: " . $e->getMessage());
        }

        // Filtrar por archivados
        if ($hasArchived) {
            if ($archived === '1' || $archived === 'true') {
                $where[] = "p.archived = 1";
            } elseif ($archived === '0' || $archived === 'false' || $archived === '') {
                // Por defecto, no mostrar archivados
                $where[] = "p.archived = 0";
            }
            // Si archived === 'all', no filtramos
        }

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

        // Verificar si existe la columna sort_order
        $hasSortOrder = false;
        try {
            $checkStmt = $this->db->query("SHOW COLUMNS FROM products LIKE 'sort_order'");
            $hasSortOrder = $checkStmt->rowCount() > 0;
        } catch (\Exception $e) {
            // Si falla, asumimos que no existe
        }

        // Ordenar según parámetro sort del usuario, o por sort_order si existe
        $sort = $params['sort'] ?? '';
        switch ($sort) {
            case 'price_asc':
                $orderBy = "ORDER BY CAST(COALESCE(NULLIF(p.sale_price, ''), p.price) AS DECIMAL(10,2)) ASC";
                break;
            case 'price_desc':
                $orderBy = "ORDER BY CAST(COALESCE(NULLIF(p.sale_price, ''), p.price) AS DECIMAL(10,2)) DESC";
                break;
            case 'name_asc':
                $orderBy = "ORDER BY p.name ASC";
                break;
            case 'name_desc':
                $orderBy = "ORDER BY p.name DESC";
                break;
            case 'newest':
                $orderBy = "ORDER BY p.created_at DESC";
                break;
            default:
                $orderBy = $hasSortOrder ? "ORDER BY p.sort_order ASC, p.id ASC" : "ORDER BY p.id DESC";
                break;
        }

        // Obtener productos
        $sql = "SELECT p.*, c.name as category_name,
                       (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                {$whereClause}
                {$orderBy}
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        $products = $stmt->fetchAll();

        // Agregar URLs de imágenes
        $products = $this->addImageUrls($products);

        // ========== COLORES DISPONIBLES POR PRODUCTO ==========
        try {
            if (!empty($products)) {
                $productIds = array_column($products, 'id');
                $placeholders = implode(',', array_fill(0, count($productIds), '?'));

                $colorSql = "SELECT DISTINCT pv.product_id, pav.color_hex
                             FROM product_variants pv
                             JOIN product_variant_attributes pva ON pva.variant_id = pv.id
                             JOIN product_attribute_values pav ON pav.id = pva.attribute_value_id
                             JOIN product_attributes pa ON pa.id = pva.attribute_id
                             WHERE pv.product_id IN ({$placeholders})
                               AND pv.is_active = 1 AND pa.type = 'color' AND pav.color_hex IS NOT NULL";

                $colorStmt = $this->db->prepare($colorSql);
                $colorStmt->execute($productIds);
                $colorRows = $colorStmt->fetchAll();

                // Agrupar por product_id
                $colorMap = [];
                foreach ($colorRows as $row) {
                    $pid = $row['product_id'];
                    if (!isset($colorMap[$pid])) {
                        $colorMap[$pid] = [];
                    }
                    if (!in_array($row['color_hex'], $colorMap[$pid])) {
                        $colorMap[$pid][] = $row['color_hex'];
                    }
                }

                // Asignar a cada producto
                foreach ($products as &$p) {
                    $p['available_colors'] = $colorMap[$p['id']] ?? [];
                }
                unset($p);
            }
        } catch (\Exception $e) {
            // Si las tablas de variantes no existen, no fallar
            error_log("Colors query failed (tables may not exist): " . $e->getMessage());
            foreach ($products as &$p) {
                $p['available_colors'] = [];
            }
            unset($p);
        }

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

        // Obtener imágenes ordenadas
        $imgStmt = $this->db->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC");
        $imgStmt->execute([$id]);
        $product['images'] = $imgStmt->fetchAll();

        // Agregar URLs de imágenes
        $product = $this->addImageUrls($product);

        // ========== VARIANTES ==========
        try {
            $variantSql = "SELECT pv.id as variant_id, pv.sku_suffix, pv.price_adjustment, pv.stock as variant_stock,
                                  pv.is_active as variant_active, pv.sort_order as variant_sort,
                                  pva.attribute_id, pva.attribute_value_id,
                                  pa.name as attribute_name, pa.display_name, pa.type as attribute_type,
                                  pav.value as attribute_value, pav.color_hex
                           FROM product_variants pv
                           LEFT JOIN product_variant_attributes pva ON pva.variant_id = pv.id
                           LEFT JOIN product_attributes pa ON pa.id = pva.attribute_id
                           LEFT JOIN product_attribute_values pav ON pav.id = pva.attribute_value_id
                           WHERE pv.product_id = ? AND pv.is_active = 1
                           ORDER BY pv.sort_order ASC, pv.id ASC, pa.sort_order ASC";

            $varStmt = $this->db->prepare($variantSql);
            $varStmt->execute([$id]);
            $varRows = $varStmt->fetchAll();

            // Agrupar variantes
            $variants = [];
            $availableAttributes = [];

            foreach ($varRows as $row) {
                $vid = $row['variant_id'];
                if (!isset($variants[$vid])) {
                    $variants[$vid] = [
                        'id' => (int)$row['variant_id'],
                        'sku_suffix' => $row['sku_suffix'],
                        'price_adjustment' => $row['price_adjustment'],
                        'stock' => (int)$row['variant_stock'],
                        'attributes' => []
                    ];
                }
                if ($row['attribute_id']) {
                    $variants[$vid]['attributes'][] = [
                        'attribute_id' => (int)$row['attribute_id'],
                        'attribute_value_id' => (int)$row['attribute_value_id'],
                        'attribute_name' => $row['attribute_name'],
                        'display_name' => $row['display_name'],
                        'attribute_type' => $row['attribute_type'],
                        'value' => $row['attribute_value'],
                        'color_hex' => $row['color_hex']
                    ];

                    // Construir available_attributes agrupado por attribute_id
                    $attrId = (int)$row['attribute_id'];
                    if (!isset($availableAttributes[$attrId])) {
                        $availableAttributes[$attrId] = [
                            'attribute_id' => $attrId,
                            'attribute_name' => $row['attribute_name'],
                            'display_name' => $row['display_name'],
                            'attribute_type' => $row['attribute_type'],
                            'values' => []
                        ];
                    }
                    $valId = (int)$row['attribute_value_id'];
                    $exists = false;
                    foreach ($availableAttributes[$attrId]['values'] as $existing) {
                        if ($existing['value_id'] === $valId) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $availableAttributes[$attrId]['values'][] = [
                            'value_id' => $valId,
                            'value' => $row['attribute_value'],
                            'color_hex' => $row['color_hex']
                        ];
                    }
                }
            }

            $product['variants'] = array_values($variants);
            $product['available_attributes'] = array_values($availableAttributes);
        } catch (\Exception $e) {
            // Si las tablas de variantes no existen, no fallar
            error_log("Variants query failed (tables may not exist): " . $e->getMessage());
            $product['variants'] = [];
            $product['available_attributes'] = [];
        }

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

            // Manejar imágenes - procesar TODAS las imágenes subidas
            $imagesProcessed = 0;
            $sortOrder = 0;

            // Procesar imagen principal si existe
            if (isset($uploadedFiles['image']) && $uploadedFiles['image']->getError() === UPLOAD_ERR_OK) {
                $uploadedFile = $uploadedFiles['image'];
                error_log("Processing main image upload for product: " . $productId);

                try {
                    $imagePath = $this->fileUpload->uploadFile($uploadedFile, 'products');
                    error_log("Image path returned: " . $imagePath);

                    $imgSql = "INSERT INTO product_images (product_id, image_path, alt_text, is_primary, sort_order) VALUES (?, ?, ?, ?, ?)";
                    $imgStmt = $this->db->prepare($imgSql);
                    $result = $imgStmt->execute([
                        $productId,
                        $imagePath,
                        $data['name'],
                        1, // Primera imagen es primaria
                        $sortOrder++
                    ]);

                    if ($result) {
                        error_log("Main image saved successfully: " . $imagePath);
                        $imagesProcessed++;
                    }
                } catch (\Exception $e) {
                    error_log("Main image upload error: " . $e->getMessage());
                }
            }

            // Procesar imágenes adicionales (images[])
            if (isset($uploadedFiles['images']) && is_array($uploadedFiles['images'])) {
                error_log("Processing " . count($uploadedFiles['images']) . " additional images");

                foreach ($uploadedFiles['images'] as $index => $uploadedFile) {
                    if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
                        try {
                            $imagePath = $this->fileUpload->uploadFile($uploadedFile, 'products');
                            error_log("Additional image {$index} uploaded: " . $imagePath);

                            $imgSql = "INSERT INTO product_images (product_id, image_path, alt_text, is_primary, sort_order) VALUES (?, ?, ?, ?, ?)";
                            $imgStmt = $this->db->prepare($imgSql);
                            $result = $imgStmt->execute([
                                $productId,
                                $imagePath,
                                $data['name'] . ' - Imagen ' . ($sortOrder + 1),
                                $imagesProcessed === 0 ? 1 : 0, // Primera es primaria si no hay otra
                                $sortOrder++
                            ]);

                            if ($result) {
                                error_log("Additional image {$index} saved successfully");
                                $imagesProcessed++;
                            }
                        } catch (\Exception $e) {
                            error_log("Additional image {$index} upload error: " . $e->getMessage());
                        }
                    } else {
                        error_log("Additional image {$index} error code: " . $uploadedFile->getError());
                    }
                }
            }

            error_log("Total images processed: " . $imagesProcessed);

            $this->db->commit();
            error_log("=== CREATE PRODUCT SUCCESS ===");

            $resultMessage = 'Product created successfully';
            if ($imagesProcessed > 0) {
                $resultMessage .= " with {$imagesProcessed} image" . ($imagesProcessed > 1 ? 's' : '');
            }

            $response->getBody()->write(json_encode([
                'message' => $resultMessage,
                'product_id' => $productId,
                'images_processed' => $imagesProcessed
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
                // Caso especial: sale_price vacío o 0 debe guardarse como NULL
                if ($field === 'sale_price' && array_key_exists($field, $data)) {
                    $salePrice = $data[$field];
                    // Si está vacío, es 0, o no es un número válido mayor a 0, guardar NULL
                    if ($salePrice === '' || $salePrice === null || $salePrice === '0' || (is_numeric($salePrice) && (float)$salePrice <= 0)) {
                        $updateFields[] = "sale_price = NULL";
                        // No agregamos nada a $params porque usamos NULL directamente en el SQL
                        continue;
                    }
                }

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
                $params[] = $this->generateSlug($data['name'], $id);
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
     * ========== ARCHIVAR/DESARCHIVAR PRODUCTOS ==========
     */

    /**
     * Archivar producto (soft delete)
     * PUT /api/admin/products/{id}/archive
     */
    public function archive(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];

        try {
            // Verificar que el producto existe
            $checkStmt = $this->db->prepare("SELECT id, name FROM products WHERE id = ?");
            $checkStmt->execute([$id]);
            $product = $checkStmt->fetch();

            if (!$product) {
                $response->getBody()->write(json_encode(['error' => 'Product not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Verificar/crear columna archived si no existe
            try {
                $checkCol = $this->db->query("SHOW COLUMNS FROM products LIKE 'archived'");
                if ($checkCol->rowCount() === 0) {
                    $this->db->exec("ALTER TABLE products ADD COLUMN archived TINYINT(1) NOT NULL DEFAULT 0");
                }
            } catch (\Exception $e) {
                error_log("Error creating archived column: " . $e->getMessage());
            }

            // Archivar el producto
            $stmt = $this->db->prepare("UPDATE products SET archived = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$id]);

            error_log("Product archived: " . $product['name'] . " (ID: " . $id . ")");

            $response->getBody()->write(json_encode([
                'message' => 'Producto archivado correctamente',
                'product_id' => $id,
                'product_name' => $product['name']
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            error_log("Archive product error: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Failed to archive product']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Desarchivar producto (restaurar)
     * PUT /api/admin/products/{id}/unarchive
     */
    public function unarchive(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];

        try {
            // Verificar que el producto existe
            $checkStmt = $this->db->prepare("SELECT id, name FROM products WHERE id = ?");
            $checkStmt->execute([$id]);
            $product = $checkStmt->fetch();

            if (!$product) {
                $response->getBody()->write(json_encode(['error' => 'Product not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Desarchivar el producto
            $stmt = $this->db->prepare("UPDATE products SET archived = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$id]);

            error_log("Product unarchived: " . $product['name'] . " (ID: " . $id . ")");

            $response->getBody()->write(json_encode([
                'message' => 'Producto restaurado correctamente',
                'product_id' => $id,
                'product_name' => $product['name']
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            error_log("Unarchive product error: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Failed to unarchive product']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * ========== MÉTODOS PARA GESTIÓN DE IMÁGENES ==========
     */

    /**
     * Subir imágenes adicionales a un producto existente
     * POST /api/admin/products/{id}/images
     */
    public function uploadImages(Request $request, Response $response, array $args): Response
    {
        $productId = $args['id'];

        try {
            $this->db->beginTransaction();

            // Verificar que el producto existe
            $productStmt = $this->db->prepare("SELECT id FROM products WHERE id = ?");
            $productStmt->execute([$productId]);
            if (!$productStmt->fetch()) {
                $response->getBody()->write(json_encode(['error' => 'Product not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Obtener los archivos subidos
            $uploadedFiles = $request->getUploadedFiles();
            error_log("Uploaded files: " . print_r(array_keys($uploadedFiles), true));

            if (empty($uploadedFiles) || !isset($uploadedFiles['images'])) {
                $response->getBody()->write(json_encode(['error' => 'No images provided']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $images = $uploadedFiles['images'];

            // Si es un solo archivo, convertirlo a array
            if (!is_array($images)) {
                $images = [$images];
            }

            // Obtener el siguiente sort_order disponible
            $sortStmt = $this->db->prepare(
                "SELECT COALESCE(MAX(sort_order), 0) as max_order FROM product_images WHERE product_id = ?"
            );
            $sortStmt->execute([$productId]);
            $sortOrder = $sortStmt->fetch()['max_order'] + 1;

            $uploadedImages = [];

            foreach ($images as $image) {
                try {
                    // Validar el archivo
                    $error = $image->getError();
                    if ($error !== UPLOAD_ERR_OK) {
                        error_log("Upload error: " . $error);
                        continue;
                    }

                    // Subir la imagen
                    $imagePath = $this->fileUpload->uploadFile($image, 'products');

                    if ($imagePath) {
                        // Insertar en la base de datos
                        $stmt = $this->db->prepare(
                            "INSERT INTO product_images (product_id, image_path, sort_order, is_primary)
                             VALUES (?, ?, ?, 0)"
                        );
                        $stmt->execute([$productId, $imagePath, $sortOrder]);

                        $imageId = $this->db->lastInsertId();

                        $uploadedImages[] = [
                            'id' => $imageId,
                            'image_path' => $imagePath,
                            'image_url' => $this->fileUpload->getImageUrl($imagePath),
                            'sort_order' => $sortOrder,
                            'is_primary' => false
                        ];

                        $sortOrder++;
                    }
                } catch (\Exception $e) {
                    error_log("Error uploading individual image: " . $e->getMessage());
                    // Continuar con las siguientes imágenes
                    continue;
                }
            }

            if (empty($uploadedImages)) {
                $this->db->rollback();
                $response->getBody()->write(json_encode(['error' => 'Failed to upload any images']));
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }

            $this->db->commit();

            $response->getBody()->write(json_encode([
                'message' => 'Images uploaded successfully',
                'images' => $uploadedImages
            ]));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            error_log("Upload images error: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Failed to upload images: ' . $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Eliminar imagen específica
     * DELETE /api/admin/products/{product_id}/images/{image_id}
     */
    public function deleteImage(Request $request, Response $response, array $args): Response
    {
        $productId = $args['product_id'];
        $imageId = $args['image_id'];

        try {
            $this->db->beginTransaction();

            // Verificar que el producto existe
            $productStmt = $this->db->prepare("SELECT id FROM products WHERE id = ?");
            $productStmt->execute([$productId]);
            if (!$productStmt->fetch()) {
                $response->getBody()->write(json_encode(['error' => 'Product not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Obtener información de la imagen
            $imgStmt = $this->db->prepare("SELECT * FROM product_images WHERE id = ? AND product_id = ?");
            $imgStmt->execute([$imageId, $productId]);
            $image = $imgStmt->fetch();

            if (!$image) {
                $response->getBody()->write(json_encode(['error' => 'Image not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Verificar que no sea la única imagen
            $countStmt = $this->db->prepare("SELECT COUNT(*) as count FROM product_images WHERE product_id = ?");
            $countStmt->execute([$productId]);
            $imageCount = $countStmt->fetch()['count'];

            if ($imageCount <= 1) {
                $response->getBody()->write(json_encode(['error' => 'Cannot delete the last image']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $wasPrimary = (bool)$image['is_primary'];

            // Eliminar archivo físico
            $this->fileUpload->deleteFile($image['image_path']);

            // Eliminar de la base de datos
            $deleteStmt = $this->db->prepare("DELETE FROM product_images WHERE id = ?");
            $deleteStmt->execute([$imageId]);

            // Si era primaria, hacer primaria a la siguiente
            if ($wasPrimary) {
                $nextStmt = $this->db->prepare(
                    "UPDATE product_images SET is_primary = 1 
                     WHERE product_id = ? 
                     ORDER BY sort_order ASC, id ASC 
                     LIMIT 1"
                );
                $nextStmt->execute([$productId]);
                error_log("Made next image primary for product: " . $productId);
            }

            // Reordenar las imágenes restantes
            $this->reorderImagesHelper($productId);

            $this->db->commit();

            $response->getBody()->write(json_encode(['message' => 'Image deleted successfully']));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            error_log("Delete image error: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Failed to delete image']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Reordenar imágenes
     * PUT /api/admin/products/{product_id}/images/reorder
     */
    public function reorderImages(Request $request, Response $response, array $args): Response
    {
        $productId = $args['product_id'];
        $data = $this->getRequestData($request);

        // Verificar que el producto existe
        $productStmt = $this->db->prepare("SELECT id FROM products WHERE id = ?");
        $productStmt->execute([$productId]);
        if (!$productStmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'Product not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        if (!isset($data['image_ids']) || !is_array($data['image_ids'])) {
            $response->getBody()->write(json_encode(['error' => 'image_ids array is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $this->db->beginTransaction();

            // Actualizar el order de cada imagen
            foreach ($data['image_ids'] as $order => $imageId) {
                $updateStmt = $this->db->prepare(
                    "UPDATE product_images SET sort_order = ? WHERE id = ? AND product_id = ?"
                );
                $updateStmt->execute([$order, $imageId, $productId]);
            }

            $this->db->commit();

            $response->getBody()->write(json_encode(['message' => 'Images reordered successfully']));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            error_log("Reorder images error: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Failed to reorder images']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Establecer imagen primaria
     * PUT /api/admin/products/{product_id}/images/{image_id}/primary
     */
    public function setPrimaryImage(Request $request, Response $response, array $args): Response
    {
        $productId = $args['product_id'];
        $imageId = $args['image_id'];

        try {
            $this->db->beginTransaction();

            // Verificar que el producto existe
            $productStmt = $this->db->prepare("SELECT id FROM products WHERE id = ?");
            $productStmt->execute([$productId]);
            if (!$productStmt->fetch()) {
                $response->getBody()->write(json_encode(['error' => 'Product not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Verificar que la imagen existe
            $imgStmt = $this->db->prepare("SELECT id FROM product_images WHERE id = ? AND product_id = ?");
            $imgStmt->execute([$imageId, $productId]);
            if (!$imgStmt->fetch()) {
                $response->getBody()->write(json_encode(['error' => 'Image not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Quitar primary de todas las imágenes del producto
            $updateAllStmt = $this->db->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?");
            $updateAllStmt->execute([$productId]);

            // Establecer la nueva imagen como primaria
            $setPrimaryStmt = $this->db->prepare("UPDATE product_images SET is_primary = 1 WHERE id = ?");
            $setPrimaryStmt->execute([$imageId]);

            $this->db->commit();

            $response->getBody()->write(json_encode(['message' => 'Primary image updated successfully']));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            error_log("Set primary image error: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Failed to set primary image']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * ========== REORDENAMIENTO DE PRODUCTOS ==========
     */

    /**
     * Reordenar productos manualmente
     * PUT /api/admin/products/reorder
     */
    public function reorderProducts(Request $request, Response $response): Response
    {
        try {
            $data = json_decode($request->getBody()->getContents(), true);

            if (!isset($data['product_ids']) || !is_array($data['product_ids'])) {
                $response->getBody()->write(json_encode(['error' => 'product_ids array is required']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $productIds = $data['product_ids'];
            error_log("Reordering " . count($productIds) . " products");

            // Verificar/crear columna sort_order si no existe
            try {
                $checkSql = "SHOW COLUMNS FROM products LIKE 'sort_order'";
                $checkStmt = $this->db->query($checkSql);
                if ($checkStmt->rowCount() === 0) {
                    error_log("Creating sort_order column in products table");
                    $this->db->exec("ALTER TABLE products ADD COLUMN sort_order INT NOT NULL DEFAULT 0");
                }
            } catch (\Exception $e) {
                error_log("Error checking/creating sort_order column: " . $e->getMessage());
            }

            $this->db->beginTransaction();

            // Usar CASE WHEN para actualizar todos en una sola query (mucho más rápido)
            if (count($productIds) > 0) {
                $cases = [];
                $ids = [];
                foreach ($productIds as $index => $productId) {
                    $cases[] = "WHEN id = " . (int)$productId . " THEN " . (int)$index;
                    $ids[] = (int)$productId;
                }

                $sql = "UPDATE products SET sort_order = CASE " . implode(' ', $cases) . " END WHERE id IN (" . implode(',', $ids) . ")";
                $this->db->exec($sql);
            }

            $this->db->commit();

            $response->getBody()->write(json_encode([
                'message' => 'Products reordered successfully',
                'count' => count($productIds)
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            error_log("Reorder products error: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Failed to reorder products']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Actualizar productos destacados en batch
     * POST /api/admin/products/featured
     */
    public function updateFeaturedProducts(Request $request, Response $response): Response
    {
        try {
            $data = json_decode($request->getBody()->getContents(), true);

            if (!isset($data['featured_ids']) || !is_array($data['featured_ids'])) {
                $response->getBody()->write(json_encode(['error' => 'featured_ids array is required']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $featuredIds = $data['featured_ids'];
            error_log("Updating featured products: " . count($featuredIds) . " products");

            // Verificar/crear columnas si no existen
            try {
                $checkFeatured = $this->db->query("SHOW COLUMNS FROM products LIKE 'is_featured'");
                if ($checkFeatured->rowCount() === 0) {
                    $this->db->exec("ALTER TABLE products ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0");
                }
                $checkOrder = $this->db->query("SHOW COLUMNS FROM products LIKE 'featured_order'");
                if ($checkOrder->rowCount() === 0) {
                    $this->db->exec("ALTER TABLE products ADD COLUMN featured_order INT DEFAULT NULL");
                }
            } catch (\Exception $e) {
                error_log("Error checking/creating featured columns: " . $e->getMessage());
            }

            $this->db->beginTransaction();

            // Primero, quitar destacado de todos los productos
            $this->db->exec("UPDATE products SET is_featured = 0, featured_order = NULL");

            // Luego, marcar los destacados con su orden
            if (count($featuredIds) > 0) {
                $stmt = $this->db->prepare("UPDATE products SET is_featured = 1, featured_order = ? WHERE id = ?");
                foreach ($featuredIds as $order => $productId) {
                    $stmt->execute([$order + 1, $productId]);
                }
            }

            $this->db->commit();

            $response->getBody()->write(json_encode([
                'message' => 'Productos destacados actualizados correctamente',
                'count' => count($featuredIds)
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            error_log("Update featured products error: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Failed to update featured products']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Obtener productos ordenados para el ordenador
     * GET /api/admin/products/sorted
     */
    public function getSortedProducts(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $categoryId = $queryParams['category_id'] ?? null;

            // Verificar si existe la columna sort_order
            $hasSortOrder = false;
            try {
                $checkSql = "SHOW COLUMNS FROM products LIKE 'sort_order'";
                $checkStmt = $this->db->query($checkSql);
                $hasSortOrder = $checkStmt->rowCount() > 0;
            } catch (\Exception $e) {
                error_log("Could not check for sort_order column: " . $e->getMessage());
            }

            // Construir SQL según si existe sort_order
            if ($hasSortOrder) {
                $sql = "SELECT p.id, p.name, p.sku, p.price, p.stock, p.status, p.sort_order, p.category_id,
                               c.name as category_name,
                               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
                        FROM products p
                        LEFT JOIN categories c ON p.category_id = c.id";
            } else {
                $sql = "SELECT p.id, p.name, p.sku, p.price, p.stock, p.status, 0 as sort_order, p.category_id,
                               c.name as category_name,
                               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
                        FROM products p
                        LEFT JOIN categories c ON p.category_id = c.id";
            }

            $params = [];
            if ($categoryId) {
                $sql .= " WHERE p.category_id = ?";
                $params[] = $categoryId;
            }

            $sql .= $hasSortOrder ? " ORDER BY p.sort_order ASC, p.id ASC" : " ORDER BY p.id ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode([
                'data' => $products,
                'total' => count($products)
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            error_log("Get sorted products error: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Failed to get sorted products']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * ========== MÉTODOS HELPER ==========
     */

    /**
     * Método helper para reordenar imágenes automáticamente
     */
    private function reorderImagesHelper($productId): void
    {
        // Obtener todas las imágenes ordenadas
        $stmt = $this->db->prepare("SELECT id FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$productId]);
        $images = $stmt->fetchAll();

        // Reordenar con números consecutivos
        foreach ($images as $index => $image) {
            $updateStmt = $this->db->prepare("UPDATE product_images SET sort_order = ? WHERE id = ?");
            $updateStmt->execute([$index, $image['id']]);
        }
    }

    /**
     * Generar slug único a partir del nombre
     */
    private function generateSlug($name, $excludeId = null)
    {
        $baseSlug = strtolower(trim($name));
        $baseSlug = preg_replace('/[^a-z0-9-]/', '-', $baseSlug);
        $baseSlug = preg_replace('/-+/', '-', $baseSlug);
        $baseSlug = trim($baseSlug, '-');

        // Verificar si el slug ya existe y generar uno único
        $slug = $baseSlug;
        $counter = 1;

        while (true) {
            $sql = "SELECT id FROM products WHERE slug = ?";
            $params = [$slug];

            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            if (!$stmt->fetch()) {
                break; // Slug es único
            }

            // Agregar sufijo numérico
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
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