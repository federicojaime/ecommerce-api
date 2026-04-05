<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AttributeController
{
    private $db;

    public function __construct($database)
    {
        $this->db = $database->getConnection();
    }

    /**
     * Helper para obtener datos del request
     */
    private function getRequestData(Request $request): array
    {
        $contentType = $request->getHeaderLine('Content-Type');

        if (strpos($contentType, 'application/json') !== false) {
            $body = $request->getBody()->getContents();
            if (!empty($body)) {
                $data = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                    return $data;
                }
            }
        }

        $parsedBody = $request->getParsedBody();
        return is_array($parsedBody) ? $parsedBody : [];
    }

    /**
     * GET /api/admin/attributes - Listar todos los atributos con sus valores
     */
    public function getAll(Request $request, Response $response): Response
    {
        $sql = "SELECT * FROM product_attributes ORDER BY sort_order ASC, id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $attributes = $stmt->fetchAll();

        // Obtener valores para cada atributo
        foreach ($attributes as &$attr) {
            $valStmt = $this->db->prepare(
                "SELECT * FROM product_attribute_values WHERE attribute_id = ? ORDER BY sort_order ASC, id ASC"
            );
            $valStmt->execute([$attr['id']]);
            $attr['values'] = $valStmt->fetchAll();
        }

        $response->getBody()->write(json_encode($attributes));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * GET /api/attributes - Atributos públicos (solo activos)
     */
    public function getPublicAll(Request $request, Response $response): Response
    {
        $sql = "SELECT * FROM product_attributes WHERE is_active = 1 ORDER BY sort_order ASC, id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $attributes = $stmt->fetchAll();

        foreach ($attributes as &$attr) {
            $valStmt = $this->db->prepare(
                "SELECT * FROM product_attribute_values WHERE attribute_id = ? AND is_active = 1 ORDER BY sort_order ASC, id ASC"
            );
            $valStmt->execute([$attr['id']]);
            $attr['values'] = $valStmt->fetchAll();
        }

        $response->getBody()->write(json_encode($attributes));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * POST /api/admin/attributes - Crear atributo
     */
    public function create(Request $request, Response $response): Response
    {
        $data = $this->getRequestData($request);

        if (empty($data['name']) || empty($data['display_name'])) {
            $response->getBody()->write(json_encode(['error' => 'name y display_name son requeridos']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $type = $data['type'] ?? 'select';
        if (!in_array($type, ['color', 'size', 'select'])) {
            $type = 'select';
        }

        $sql = "INSERT INTO product_attributes (name, display_name, type, sort_order) VALUES (?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['name'],
            $data['display_name'],
            $type,
            (int)($data['sort_order'] ?? 0)
        ]);

        $id = $this->db->lastInsertId();

        $response->getBody()->write(json_encode([
            'message' => 'Atributo creado correctamente',
            'id' => $id
        ]));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    }

    /**
     * PUT /api/admin/attributes/{id} - Actualizar atributo
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $data = $this->getRequestData($request);

        // Verificar que existe
        $check = $this->db->prepare("SELECT id FROM product_attributes WHERE id = ?");
        $check->execute([$id]);
        if (!$check->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'Atributo no encontrado']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $fields = [];
        $values = [];

        if (isset($data['name'])) {
            $fields[] = "name = ?";
            $values[] = $data['name'];
        }
        if (isset($data['display_name'])) {
            $fields[] = "display_name = ?";
            $values[] = $data['display_name'];
        }
        if (isset($data['type'])) {
            $type = in_array($data['type'], ['color', 'size', 'select']) ? $data['type'] : 'select';
            $fields[] = "type = ?";
            $values[] = $type;
        }
        if (isset($data['sort_order'])) {
            $fields[] = "sort_order = ?";
            $values[] = (int)$data['sort_order'];
        }
        if (isset($data['is_active'])) {
            $fields[] = "is_active = ?";
            $values[] = $data['is_active'] ? 1 : 0;
        }

        if (empty($fields)) {
            $response->getBody()->write(json_encode(['error' => 'No hay campos para actualizar']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $values[] = $id;
        $sql = "UPDATE product_attributes SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);

        $response->getBody()->write(json_encode(['message' => 'Atributo actualizado correctamente']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * DELETE /api/admin/attributes/{id} - Soft-delete atributo
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];

        $stmt = $this->db->prepare("UPDATE product_attributes SET is_active = 0 WHERE id = ?");
        $stmt->execute([$id]);

        $response->getBody()->write(json_encode(['message' => 'Atributo desactivado correctamente']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * POST /api/admin/attributes/{id}/values - Crear valor de atributo
     */
    public function createValue(Request $request, Response $response, array $args): Response
    {
        $attributeId = $args['id'];
        $data = $this->getRequestData($request);

        // Verificar que el atributo existe
        $check = $this->db->prepare("SELECT id FROM product_attributes WHERE id = ?");
        $check->execute([$attributeId]);
        if (!$check->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'Atributo no encontrado']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        if (empty($data['value'])) {
            $response->getBody()->write(json_encode(['error' => 'value es requerido']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $sql = "INSERT INTO product_attribute_values (attribute_id, value, color_hex, sort_order) VALUES (?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $attributeId,
            $data['value'],
            $data['color_hex'] ?? null,
            (int)($data['sort_order'] ?? 0)
        ]);

        $id = $this->db->lastInsertId();

        $response->getBody()->write(json_encode([
            'message' => 'Valor creado correctamente',
            'id' => $id
        ]));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    }

    /**
     * PUT /api/admin/attributes/{id}/values/{valueId} - Actualizar valor
     */
    public function updateValue(Request $request, Response $response, array $args): Response
    {
        $valueId = $args['valueId'];
        $data = $this->getRequestData($request);

        $check = $this->db->prepare("SELECT id FROM product_attribute_values WHERE id = ?");
        $check->execute([$valueId]);
        if (!$check->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'Valor no encontrado']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $fields = [];
        $values = [];

        if (isset($data['value'])) {
            $fields[] = "value = ?";
            $values[] = $data['value'];
        }
        if (array_key_exists('color_hex', $data)) {
            $fields[] = "color_hex = ?";
            $values[] = $data['color_hex'];
        }
        if (isset($data['sort_order'])) {
            $fields[] = "sort_order = ?";
            $values[] = (int)$data['sort_order'];
        }
        if (isset($data['is_active'])) {
            $fields[] = "is_active = ?";
            $values[] = $data['is_active'] ? 1 : 0;
        }

        if (empty($fields)) {
            $response->getBody()->write(json_encode(['error' => 'No hay campos para actualizar']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $values[] = $valueId;
        $sql = "UPDATE product_attribute_values SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);

        $response->getBody()->write(json_encode(['message' => 'Valor actualizado correctamente']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * DELETE /api/admin/attributes/{id}/values/{valueId} - Soft-delete valor
     */
    public function deleteValue(Request $request, Response $response, array $args): Response
    {
        $valueId = $args['valueId'];

        $stmt = $this->db->prepare("UPDATE product_attribute_values SET is_active = 0 WHERE id = ?");
        $stmt->execute([$valueId]);

        $response->getBody()->write(json_encode(['message' => 'Valor desactivado correctamente']));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
