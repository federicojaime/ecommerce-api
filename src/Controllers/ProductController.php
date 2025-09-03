<?php
namespace App\Controllers;

use App\Utils\FileUpload;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ProductController {
    private $db;
    private $fileUpload;

    public function __construct($database) {
        $this->db = $database->getConnection();
        $this->fileUpload = new FileUpload();
    }

    public function getAll(Request $request, Response $response): Response {
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
                LIMIT ? OFFSET ?";
        
        $bindings[] = $limit;
        $bindings[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        $products = $stmt->fetchAll();
        
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

    public function getOne(Request $request, Response $response, array $args): Response {
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
        
        $response->getBody()->write(json_encode($product));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response {
        $data = $request->getParsedBody();
        
        $required = ['name', 'sku', 'price'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $response->getBody()->write(json_encode(['error' => $field . ' is required']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
        }
        
        // Verificar SKU único
        $stmt = $this->db->prepare("SELECT id FROM products WHERE sku = ?");
        $stmt->execute([$data['sku']]);
        if ($stmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'SKU already exists']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
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
            $data['price'],
            $data['sale_price'] ?? null,
            $data['stock'] ?? 0,
            $data['min_stock'] ?? 0,
            $data['status'] ?? 'active',
            isset($data['featured']) ? (bool)$data['featured'] : false,
            $data['weight'] ?? null,
            $data['dimensions'] ?? null,
            $data['category_id'] ?? null
        ];
        
        $stmt = $this->db->prepare($sql);
        
        if ($stmt->execute($params)) {
            $productId = $this->db->lastInsertId();
            
            // Manejar upload de imágenes
            $uploadedFiles = $request->getUploadedFiles();
            if (isset($uploadedFiles['images'])) {
                $this->handleImageUploads($uploadedFiles['images'], $productId);
            }
            
            $response->getBody()->write(json_encode([
                'message' => 'Product created successfully',
                'product_id' => $productId
            ]));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        }
        
        $response->getBody()->write(json_encode(['error' => 'Failed to create product']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

    public function update(Request $request, Response $response, array $args): Response {
        $id = $args['id'];
        $data = $request->getParsedBody();
        
        // Verificar que el producto existe
        $checkStmt = $this->db->prepare("SELECT id FROM products WHERE id = ?");
        $checkStmt->execute([$id]);
        if (!$checkStmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'Product not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        
        // Verificar SKU único (excluyendo el producto actual)
        if (isset($data['sku'])) {
            $stmt = $this->db->prepare("SELECT id FROM products WHERE sku = ? AND id != ?");
            $stmt->execute([$data['sku'], $id]);
            if ($stmt->fetch()) {
                $response->getBody()->write(json_encode(['error' => 'SKU already exists']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
        }
        
        $updateFields = [];
        $params = [];
        
        $allowedFields = ['name', 'description', 'short_description', 'sku', 'price', 'sale_price', 
                         'stock', 'min_stock', 'status', 'featured', 'weight', 'dimensions', 'category_id'];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateFields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        
        if (isset($data['name'])) {
            $updateFields[] = "slug = ?";
            $params[] = $this->generateSlug($data['name']);
        }
        
        if ($updateFields) {
            $updateFields[] = "updated_at = CURRENT_TIMESTAMP";
            $params[] = $id;
            
            $sql = "UPDATE products SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }
        
        // Manejar upload de nuevas imágenes
        $uploadedFiles = $request->getUploadedFiles();
        if (isset($uploadedFiles['images'])) {
            $this->handleImageUploads($uploadedFiles['images'], $id);
        }
        
        $response->getBody()->write(json_encode(['message' => 'Product updated successfully']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function delete(Request $request, Response $response, array $args): Response {
        $id = $args['id'];
        
        // Verificar que el producto existe
        $checkStmt = $this->db->prepare("SELECT id FROM products WHERE id = ?");
        $checkStmt->execute([$id]);
        if (!$checkStmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'Product not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        
        // Eliminar imágenes físicas
        $imgStmt = $this->db->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
        $imgStmt->execute([$id]);
        $images = $imgStmt->fetchAll();
        
        foreach ($images as $image) {
            $this->fileUpload->delete('products/' . basename($image['image_path']));
        }
        
        // Eliminar producto (cascade eliminará imágenes de BD)
        $stmt = $this->db->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        
        $response->getBody()->write(json_encode(['message' => 'Product deleted successfully']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function generateSlug($name) {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }

    private function handleImageUploads($files, $productId) {
        if (!is_array($files)) {
            $files = [$files];
        }
        
        foreach ($files as $index => $file) {
            if ($file->getError() === UPLOAD_ERR_OK) {
                try {
                    $filename = $file->getClientFilename();
                    $extension = pathinfo($filename, PATHINFO_EXTENSION);
                    $newFilename = uniqid() . '.' . $extension;
                    
                    $uploadPath = 'public/uploads/products/' . $newFilename;
                    $file->moveTo($uploadPath);
                    
                    // Guardar en BD
                    $stmt = $this->db->prepare("INSERT INTO product_images (product_id, image_path, is_primary, sort_order) VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        $productId, 
                        'products/' . $newFilename, 
                        $index === 0 ? 1 : 0, 
                        $index
                    ]);
                } catch (\Exception $e) {
                    // Log error pero continuar con otras imágenes
                    error_log('Failed to upload image: ' . $e->getMessage());
                }
            }
        }
    }
}