<?php

namespace App\Controllers;

use App\Models\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

class AddressController
{
    private $db;

    public function __construct(Database $database)
    {
        $this->db = $database->getConnection();
    }

    /**
     * Listar direcciones del usuario
     */
    public function getAll(Request $request, Response $response): Response
    {
        try {
            $userId = $request->getAttribute('user_id');

            $stmt = $this->db->prepare("
                SELECT * FROM user_addresses
                WHERE user_id = :user_id
                ORDER BY is_default DESC, created_at DESC
            ");
            $stmt->execute(['user_id' => $userId]);
            $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode(['addresses' => $addresses]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Crear dirección
     */
    public function create(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            $userId = $request->getAttribute('user_id');

            // Validar campos requeridos
            $required = ['full_name', 'address_line1', 'city', 'country'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || empty(trim($data[$field]))) {
                    $response->getBody()->write(json_encode(['error' => ucfirst(str_replace('_', ' ', $field)) . ' is required']));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }
            }

            $isDefault = isset($data['is_default']) && $data['is_default'] === true;

            // Si es default, remover default de otras direcciones
            if ($isDefault) {
                $stmt = $this->db->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = :user_id");
                $stmt->execute(['user_id' => $userId]);
            }

            // Crear dirección
            $stmt = $this->db->prepare("
                INSERT INTO user_addresses (
                    user_id, address_type, full_name, phone,
                    address_line1, address_line2, city, state,
                    postal_code, country, is_default
                ) VALUES (
                    :user_id, :address_type, :full_name, :phone,
                    :address_line1, :address_line2, :city, :state,
                    :postal_code, :country, :is_default
                )
            ");

            $stmt->execute([
                'user_id' => $userId,
                'address_type' => $data['address_type'] ?? 'shipping',
                'full_name' => $data['full_name'],
                'phone' => $data['phone'] ?? null,
                'address_line1' => $data['address_line1'],
                'address_line2' => $data['address_line2'] ?? null,
                'city' => $data['city'],
                'state' => $data['state'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'country' => $data['country'],
                'is_default' => $isDefault ? 1 : 0
            ]);

            $addressId = $this->db->lastInsertId();

            $result = [
                'message' => 'Address created successfully',
                'address_id' => $addressId
            ];

            $response->getBody()->write(json_encode($result));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Actualizar dirección
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $addressId = intval($args['id']);
            $userId = $request->getAttribute('user_id');
            $data = $request->getParsedBody();

            // Verificar que la dirección pertenece al usuario
            $stmt = $this->db->prepare("SELECT * FROM user_addresses WHERE id = :id AND user_id = :user_id");
            $stmt->execute(['id' => $addressId, 'user_id' => $userId]);
            $address = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$address) {
                $response->getBody()->write(json_encode(['error' => 'Address not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Construir actualización
            $updates = [];
            $params = ['id' => $addressId];

            $allowedFields = [
                'address_type', 'full_name', 'phone', 'address_line1',
                'address_line2', 'city', 'state', 'postal_code', 'country'
            ];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = :$field";
                    $params[$field] = $data[$field];
                }
            }

            if (empty($updates)) {
                $response->getBody()->write(json_encode(['error' => 'No fields to update']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $sql = "UPDATE user_addresses SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $response->getBody()->write(json_encode(['message' => 'Address updated successfully']));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Eliminar dirección
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $addressId = intval($args['id']);
            $userId = $request->getAttribute('user_id');

            $stmt = $this->db->prepare("DELETE FROM user_addresses WHERE id = :id AND user_id = :user_id");
            $stmt->execute(['id' => $addressId, 'user_id' => $userId]);

            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode(['error' => 'Address not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode(['message' => 'Address deleted successfully']));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Establecer dirección como predeterminada
     */
    public function setDefault(Request $request, Response $response, array $args): Response
    {
        try {
            $this->db->beginTransaction();

            $addressId = intval($args['id']);
            $userId = $request->getAttribute('user_id');

            // Verificar que la dirección existe
            $stmt = $this->db->prepare("SELECT * FROM user_addresses WHERE id = :id AND user_id = :user_id");
            $stmt->execute(['id' => $addressId, 'user_id' => $userId]);
            $address = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$address) {
                $this->db->rollBack();
                $response->getBody()->write(json_encode(['error' => 'Address not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Remover default de todas
            $stmt = $this->db->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = :user_id");
            $stmt->execute(['user_id' => $userId]);

            // Establecer como default
            $stmt = $this->db->prepare("UPDATE user_addresses SET is_default = 1 WHERE id = :id");
            $stmt->execute(['id' => $addressId]);

            $this->db->commit();

            $response->getBody()->write(json_encode(['message' => 'Default address set successfully']));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $this->db->rollBack();
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
