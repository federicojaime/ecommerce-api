<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CategoryController {
    private $db;

    public function __construct($database) {
        $this->db = $database->getConnection();
    }

    /**
     * Método helper para obtener datos del request de forma segura
     */
    private function getRequestData(Request $request): array 
    {
        $contentType = $request->getHeaderLine('Content-Type');
        
        // Intentar JSON primero
        if (strpos($contentType, 'application/json') !== false) {
            $body = $request->getBody()->getContents();
            if (!empty($body)) {
                $data = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                    return $data;
                }
            }
        }
        
        // Luego intentar form-data
        $parsedBody = $request->getParsedBody();
        if (is_array($parsedBody)) {
            return $parsedBody;
        }
        
        // Si todo falla, devolver array vacío
        return [];
    }

    /**
     * Verifica si la columna sort_order existe en la tabla categories
     */
    private function ensureSortOrderColumn(): void {
        try {
            $checkColumn = $this->db->query("SHOW COLUMNS FROM categories LIKE 'sort_order'");
            if ($checkColumn->rowCount() === 0) {
                $this->db->exec("ALTER TABLE categories ADD COLUMN sort_order INT NOT NULL DEFAULT 0");
                // Inicializar sort_order basado en el orden actual por nombre
                $this->db->exec("SET @row_number = 0; UPDATE categories SET sort_order = (@row_number:=@row_number + 1) ORDER BY name");
            }
        } catch (\Exception $e) {
            // Si falla, continuamos sin el campo
        }
    }

    public function getAll(Request $request, Response $response): Response {
        $this->ensureSortOrderColumn();

        $sql = "SELECT c1.*, c2.name as parent_name,
                       (SELECT COUNT(*) FROM products WHERE category_id = c1.id) as products_count
                FROM categories c1
                LEFT JOIN categories c2 ON c1.parent_id = c2.id
                ORDER BY c1.sort_order ASC, c1.name ASC";

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
        $data = $this->getRequestData($request);

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

        // Validar parent_id si se proporciona
        $parentId = null;
        if (isset($data['parent_id']) && !empty($data['parent_id'])) {
            // Convertir a null si es vacío, 0, o "null"
            if ($data['parent_id'] === '' || $data['parent_id'] === '0' || $data['parent_id'] === 'null') {
                $parentId = null;
            } else {
                // Verificar que la categoría padre existe
                $parentStmt = $this->db->prepare("SELECT id FROM categories WHERE id = ?");
                $parentStmt->execute([$data['parent_id']]);
                if (!$parentStmt->fetch()) {
                    $response->getBody()->write(json_encode(['error' => 'Parent category does not exist']));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }
                $parentId = $data['parent_id'];
            }
        }

        $sql = "INSERT INTO categories (name, slug, description, parent_id, status) VALUES (?, ?, ?, ?, ?)";
        $params = [
            $data['name'],
            $slug,
            $data['description'] ?? null,
            $parentId,
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
        $data = $this->getRequestData($request);

        // Verificar que la categoría existe
        $checkStmt = $this->db->prepare("SELECT id FROM categories WHERE id = ?");
        $checkStmt->execute([$id]);
        if (!$checkStmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'Category not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $updateFields = [];
        $params = [];

        if (isset($data['name']) && !empty($data['name'])) {
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

        // Validar parent_id si se proporciona
        if (array_key_exists('parent_id', $data)) {
            // Convertir a null si es vacío, 0, o "null"
            if ($data['parent_id'] === '' || $data['parent_id'] === '0' || $data['parent_id'] === 'null' || $data['parent_id'] === null) {
                $updateFields[] = "parent_id = ?";
                $params[] = null;
            } else {
                // Verificar que la categoría padre existe
                $parentStmt = $this->db->prepare("SELECT id FROM categories WHERE id = ?");
                $parentStmt->execute([$data['parent_id']]);
                if (!$parentStmt->fetch()) {
                    $response->getBody()->write(json_encode(['error' => 'Parent category does not exist']));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }

                // Evitar que una categoría sea su propia padre
                if ($data['parent_id'] == $id) {
                    $response->getBody()->write(json_encode(['error' => 'A category cannot be its own parent']));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }

                $updateFields[] = "parent_id = ?";
                $params[] = $data['parent_id'];
            }
        }

        // Otros campos permitidos
        $allowedFields = ['description', 'status'];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateFields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($updateFields)) {
            $response->getBody()->write(json_encode(['error' => 'No valid fields to update']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $updateFields[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $id;

        $sql = "UPDATE categories SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

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

    /**
     * Reordenar categorías
     * POST /api/admin/categories/reorder
     * Body: { "category_ids": [3, 1, 2, 5, 4] }
     */
    public function reorderCategories(Request $request, Response $response): Response {
        $data = $this->getRequestData($request);

        if (empty($data['category_ids']) || !is_array($data['category_ids'])) {
            $response->getBody()->write(json_encode(['error' => 'category_ids array is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $categoryIds = $data['category_ids'];

        try {
            // Asegurar que la columna existe
            $this->ensureSortOrderColumn();

            // Construir query CASE WHEN para actualizar en una sola consulta
            $cases = [];
            $params = [];

            foreach ($categoryIds as $index => $id) {
                $cases[] = "WHEN id = ? THEN ?";
                $params[] = (int)$id;
                $params[] = $index + 1;
            }

            if (empty($cases)) {
                $response->getBody()->write(json_encode(['error' => 'No valid category IDs provided']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $sql = "UPDATE categories SET sort_order = CASE " . implode(' ', $cases) . " END WHERE id IN (" . implode(',', array_fill(0, count($categoryIds), '?')) . ")";

            // Agregar los IDs al final de los parámetros
            foreach ($categoryIds as $id) {
                $params[] = (int)$id;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Categories reordered successfully',
                'updated_count' => count($categoryIds)
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'error' => 'Failed to reorder categories',
                'details' => $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Obtener categorías ordenadas para el sorter
     * GET /api/admin/categories/sorted
     */
    public function getSortedCategories(Request $request, Response $response): Response {
        $this->ensureSortOrderColumn();

        $sql = "SELECT c1.id, c1.name, c1.slug, c1.status, c1.sort_order, c1.parent_id,
                       c2.name as parent_name,
                       (SELECT COUNT(*) FROM products WHERE category_id = c1.id) as products_count
                FROM categories c1
                LEFT JOIN categories c2 ON c1.parent_id = c2.id
                ORDER BY c1.sort_order ASC, c1.name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $categories = $stmt->fetchAll();

        $response->getBody()->write(json_encode([
            'data' => $categories,
            'total' => count($categories)
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}