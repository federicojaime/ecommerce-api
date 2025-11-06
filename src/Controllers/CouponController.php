<?php

namespace App\Controllers;

use App\Models\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

class CouponController
{
    private $db;

    public function __construct(Database $database)
    {
        $this->db = $database->getConnection();
    }

    /**
     * Validar cupón
     */
    public function validate(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();

            if (!isset($data['code'])) {
                $response->getBody()->write(json_encode(['error' => 'Coupon code is required']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $code = strtoupper(trim($data['code']));
            $subtotal = floatval($data['subtotal'] ?? 0);

            $stmt = $this->db->prepare("
                SELECT * FROM coupons
                WHERE code = :code
                AND status = 'active'
                AND (valid_from IS NULL OR valid_from <= NOW())
                AND (valid_until IS NULL OR valid_until >= NOW())
                AND (usage_limit IS NULL OR used_count < usage_limit)
            ");
            $stmt->execute(['code' => $code]);
            $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$coupon) {
                $response->getBody()->write(json_encode([
                    'valid' => false,
                    'error' => 'Invalid or expired coupon code'
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }

            // Validar monto mínimo
            if ($subtotal < $coupon['min_purchase']) {
                $response->getBody()->write(json_encode([
                    'valid' => false,
                    'error' => "Minimum purchase of {$coupon['min_purchase']} required"
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }

            // Calcular descuento
            $discount = 0;
            if ($coupon['type'] === 'percentage') {
                $discount = ($subtotal * $coupon['value']) / 100;
                if ($coupon['max_discount'] && $discount > $coupon['max_discount']) {
                    $discount = $coupon['max_discount'];
                }
            } else {
                $discount = $coupon['value'];
            }

            $result = [
                'valid' => true,
                'coupon' => [
                    'code' => $coupon['code'],
                    'description' => $coupon['description'],
                    'type' => $coupon['type'],
                    'value' => $coupon['value'],
                    'discount_amount' => number_format($discount, 2, '.', '')
                ]
            ];

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * [ADMIN] Listar cupones
     */
    public function getAll(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $status = $params['status'] ?? null;

            $sql = "SELECT * FROM coupons";
            if ($status) {
                $sql .= " WHERE status = :status";
            }
            $sql .= " ORDER BY created_at DESC";

            $stmt = $this->db->prepare($sql);
            if ($status) {
                $stmt->execute(['status' => $status]);
            } else {
                $stmt->execute();
            }

            $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode(['coupons' => $coupons]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * [ADMIN] Crear cupón
     */
    public function create(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();

            // Validar campos requeridos
            $required = ['code', 'type', 'value'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || empty(trim($data[$field]))) {
                    $response->getBody()->write(json_encode(['error' => ucfirst($field) . ' is required']));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }
            }

            $code = strtoupper(trim($data['code']));

            // Verificar que el código no existe
            $stmt = $this->db->prepare("SELECT id FROM coupons WHERE code = :code");
            $stmt->execute(['code' => $code]);
            if ($stmt->fetch()) {
                $response->getBody()->write(json_encode(['error' => 'Coupon code already exists']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Crear cupón
            $stmt = $this->db->prepare("
                INSERT INTO coupons (
                    code, description, type, value, min_purchase,
                    max_discount, usage_limit, valid_from, valid_until, status
                ) VALUES (
                    :code, :description, :type, :value, :min_purchase,
                    :max_discount, :usage_limit, :valid_from, :valid_until, :status
                )
            ");

            $stmt->execute([
                'code' => $code,
                'description' => $data['description'] ?? null,
                'type' => $data['type'],
                'value' => $data['value'],
                'min_purchase' => $data['min_purchase'] ?? 0,
                'max_discount' => $data['max_discount'] ?? null,
                'usage_limit' => $data['usage_limit'] ?? null,
                'valid_from' => $data['valid_from'] ?? null,
                'valid_until' => $data['valid_until'] ?? null,
                'status' => $data['status'] ?? 'active'
            ]);

            $couponId = $this->db->lastInsertId();

            $result = [
                'message' => 'Coupon created successfully',
                'coupon_id' => $couponId
            ];

            $response->getBody()->write(json_encode($result));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * [ADMIN] Actualizar cupón
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $couponId = intval($args['id']);
            $data = $request->getParsedBody();

            // Verificar que existe
            $stmt = $this->db->prepare("SELECT * FROM coupons WHERE id = :id");
            $stmt->execute(['id' => $couponId]);
            $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$coupon) {
                $response->getBody()->write(json_encode(['error' => 'Coupon not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Construir actualización
            $updates = [];
            $params = ['id' => $couponId];

            $allowedFields = [
                'description', 'type', 'value', 'min_purchase',
                'max_discount', 'usage_limit', 'valid_from', 'valid_until', 'status'
            ];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = :$field";
                    $params[$field] = $data[$field];
                }
            }

            if (isset($data['code'])) {
                $code = strtoupper(trim($data['code']));
                // Verificar que no existe otro cupón con ese código
                $stmt = $this->db->prepare("SELECT id FROM coupons WHERE code = :code AND id != :id");
                $stmt->execute(['code' => $code, 'id' => $couponId]);
                if ($stmt->fetch()) {
                    $response->getBody()->write(json_encode(['error' => 'Coupon code already exists']));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }
                $updates[] = 'code = :code';
                $params['code'] = $code;
            }

            if (empty($updates)) {
                $response->getBody()->write(json_encode(['error' => 'No fields to update']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $sql = "UPDATE coupons SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $response->getBody()->write(json_encode(['message' => 'Coupon updated successfully']));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * [ADMIN] Eliminar cupón
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $couponId = intval($args['id']);

            $stmt = $this->db->prepare("DELETE FROM coupons WHERE id = :id");
            $stmt->execute(['id' => $couponId]);

            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode(['error' => 'Coupon not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode(['message' => 'Coupon deleted successfully']));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
