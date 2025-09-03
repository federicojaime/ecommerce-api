<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CategoryController {
    private $db;

    public function __construct($database) {
        $this->db = $database->getConnection();
    }

    public function getAll(Request $request, Response $response): Response {
        $sql = "SELECT c1.*, c2.name as parent_name,
                       (SELECT COUNT(*) FROM products WHERE category_id = c1.id) as products_count
                FROM categories c1 
                LEFT JOIN categories c2 ON c1.parent_id = c2.id 
                ORDER BY c1.name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $categories = $stmt->fetchAll();
        
        $response->getBody()->write(json_encode($categories));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getOne(Request $request, Response $response, array $args): Response {
        $id = $args['id'];
        
        $sql = "SELECT c1.*, c2.name as parent_name FROM categories c1 
                LEFT JOIN categories c2 ON c1.parent_id = c2.id 
                WHERE c1.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $category = $stmt->fetch();
        
        if (!$category) {
            $response->getBody()->write(json_encode(['error' => 'Category not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        
        // Obtener subcategorías
        $subStmt = $this->db->prepare("SELECT * FROM categories WHERE parent_id = ? ORDER BY name");
        $subStmt->execute([$id]);
        $category['subcategories'] = $subStmt->fetchAll();
        
        $response->getBody()->write(json_encode($category));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response {
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (empty($data['name'])) {
            $response->getBody()->write(json_encode(['error' => 'Name is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
        $slug = $this->generateSlug($data['name']);
        
        // Verificar slug único
        $stmt = $this->db->prepare("SELECT id FROM categories WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'Category slug already exists']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
        $sql = "INSERT INTO categories (name, slug, description, parent_id, status) VALUES (?, ?, ?, ?, ?)";
        $params = [
            $data['name'],
            $slug,
            $data['description'] ?? null,
            $data['parent_id'] ?? null,
            $data['status'] ?? 'active'
        ];
        
        $stmt = $this->db->prepare($sql);
        
        if ($stmt->execute($params)) {
            $categoryId = $this->db->lastInsertId();
            
            $response->getBody()->write(json_encode([
                'message' => 'Category created successfully',
                'category_id' => $categoryId
            ]));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        }
        
        $response->getBody()->write(json_encode(['error' => 'Failed to create category']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

    public function update(Request $request, Response $response, array $args): Response {
        $id = $args['id'];
        $data = json_decode($request->getBody()->getContents(), true);
        
        // Verificar que la categoría existe
        $checkStmt = $this->db->prepare("SELECT id FROM categories WHERE id = ?");
        $checkStmt->execute([$id]);
        if (!$checkStmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'Category not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        
        $updateFields = [];
        $params = [];
        
        if (isset($data['name'])) {
            $slug = $this->generateSlug($data['name']);
            
            // Verificar slug único (excluyendo la categoría actual)
            $stmt = $this->db->prepare("SELECT id FROM categories WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $id]);
            if ($stmt->fetch()) {
                $response->getBody()->write(json_encode(['error' => 'Category slug already exists']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            $updateFields[] = "name = ?";
            $updateFields[] = "slug = ?";
            $params[] = $data['name'];
            $params[] = $slug;
        }
        
        $allowedFields = ['description', 'parent_id', 'status'];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateFields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        
        if ($updateFields) {
            $updateFields[] = "updated_at = CURRENT_TIMESTAMP";
            $params[] = $id;
            
            $sql = "UPDATE categories SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }
        
        $response->getBody()->write(json_encode(['message' => 'Category updated successfully']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function delete(Request $request, Response $response, array $args): Response {
        $id = $args['id'];
        
        // Verificar que la categoría existe
        $checkStmt = $this->db->prepare("SELECT id FROM categories WHERE id = ?");
        $checkStmt->execute([$id]);
        if (!$checkStmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'Category not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        
        // Verificar si hay productos asociados
        $productStmt = $this->db->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
        $productStmt->execute([$id]);
        $productCount = $productStmt->fetch()['count'];
        
        if ($productCount > 0) {
            $response->getBody()->write(json_encode(['error' => 'Cannot delete category with associated products']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
        // Verificar si hay subcategorías
        $subStmt = $this->db->prepare("SELECT COUNT(*) as count FROM categories WHERE parent_id = ?");
        $subStmt->execute([$id]);
        $subCount = $subStmt->fetch()['count'];
        
        if ($subCount > 0) {
            $response->getBody()->write(json_encode(['error' => 'Cannot delete category with subcategories']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
        $stmt = $this->db->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        
        $response->getBody()->write(json_encode(['message' => 'Category deleted successfully']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function generateSlug($name) {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }
}