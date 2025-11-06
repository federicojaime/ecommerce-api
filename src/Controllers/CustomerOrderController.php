<?php

namespace App\Controllers;

use App\Models\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

class CustomerOrderController
{
    private $db;

    public function __construct(Database $database)
    {
        $this->db = $database->getConnection();
    }

    /**
     * Listar órdenes del cliente autenticado
     */
    public function getMyOrders(Request $request, Response $response): Response
    {
        try {
            // Obtener user_id del token
            $user = $request->getAttribute('user');
            $userId = $user->user_id ?? null;

            if (!$userId) {
                $response->getBody()->write(json_encode(['error' => 'Unauthorized - Invalid user']));
                return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
            }

            $params = $request->getQueryParams();

            $page = isset($params['page']) ? max(1, intval($params['page'])) : 1;
            $limit = isset($params['limit']) ? min(100, max(1, intval($params['limit']))) : 10;
            $offset = ($page - 1) * $limit;

            // Contar total
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM orders WHERE customer_id = :customer_id");
            $stmt->execute(['customer_id' => $userId]);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Obtener órdenes
            $stmt = $this->db->prepare("
                SELECT
                    o.*,
                    (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count
                FROM orders o
                WHERE o.customer_id = :customer_id
                ORDER BY o.created_at DESC
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':customer_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $data = [
                'data' => $orders,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => intval($total),
                    'pages' => ceil($total / $limit)
                ]
            ];

            $response->getBody()->write(json_encode($data));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Obtener detalle de una orden específica
     */
    public function getMyOrder(Request $request, Response $response, array $args): Response
    {
        try {
            $orderId = intval($args['id']);

            // Obtener user_id del token
            $user = $request->getAttribute('user');
            $userId = $user->user_id ?? null;

            if (!$userId) {
                $response->getBody()->write(json_encode(['error' => 'Unauthorized - Invalid user']));
                return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
            }

            // Obtener orden
            $stmt = $this->db->prepare("SELECT * FROM orders WHERE id = :id AND customer_id = :customer_id");
            $stmt->execute(['id' => $orderId, 'customer_id' => $userId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                $response->getBody()->write(json_encode(['error' => 'Order not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Obtener items
            $stmt = $this->db->prepare("SELECT * FROM order_items WHERE order_id = :order_id");
            $stmt->execute(['order_id' => $orderId]);
            $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode($order));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Cancelar orden (solo si está en pending)
     */
    public function cancelOrder(Request $request, Response $response, array $args): Response
    {
        try {
            $this->db->beginTransaction();

            $orderId = intval($args['id']);

            // Obtener user_id del token
            $user = $request->getAttribute('user');
            $userId = $user->user_id ?? null;

            if (!$userId) {
                $this->db->rollBack();
                $response->getBody()->write(json_encode(['error' => 'Unauthorized - Invalid user']));
                return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
            }

            // Obtener orden
            $stmt = $this->db->prepare("SELECT * FROM orders WHERE id = :id AND customer_id = :customer_id");
            $stmt->execute(['id' => $orderId, 'customer_id' => $userId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                $this->db->rollBack();
                $response->getBody()->write(json_encode(['error' => 'Order not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Solo se pueden cancelar órdenes pending
            if ($order['status'] !== 'pending') {
                $this->db->rollBack();
                $response->getBody()->write(json_encode(['error' => 'Only pending orders can be cancelled']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Actualizar estado
            $stmt = $this->db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = :id");
            $stmt->execute(['id' => $orderId]);

            // Restaurar stock
            $stmt = $this->db->prepare("SELECT * FROM order_items WHERE order_id = :order_id");
            $stmt->execute(['order_id' => $orderId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($items as $item) {
                $stmt = $this->db->prepare("UPDATE products SET stock = stock + :quantity WHERE id = :id");
                $stmt->execute(['quantity' => $item['quantity'], 'id' => $item['product_id']]);
            }

            $this->db->commit();

            $response->getBody()->write(json_encode(['message' => 'Order cancelled successfully']));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $this->db->rollBack();
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
