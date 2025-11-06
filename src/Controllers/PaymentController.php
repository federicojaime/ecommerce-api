<?php

namespace App\Controllers;

use App\Models\Database;
use App\Services\MercadoPagoService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

class PaymentController
{
    private $db;
    private $mpService;

    public function __construct(Database $database)
    {
        $this->db = $database->getConnection();
        $this->mpService = new MercadoPagoService($database);
    }

    /**
     * Obtener estado del pago de una orden
     * GET /api/payments/{orderId}
     */
    public function getPaymentByOrder(Request $request, Response $response, array $args): Response
    {
        try {
            $orderId = $args['orderId'];
            $userId = $request->getAttribute('user_id');

            // Verificar que la orden pertenece al usuario
            $stmt = $this->db->prepare("SELECT * FROM orders WHERE id = ? AND customer_id = ?");
            $stmt->execute([$orderId, $userId]);
            $order = $stmt->fetch();

            if (!$order) {
                $response->getBody()->write(json_encode(['error' => 'Order not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Buscar el pago
            $stmt = $this->db->prepare("
                SELECT
                    id, payment_id, payment_method, payment_type, payment_status,
                    amount, currency, payer_email, payer_name,
                    created_at, updated_at
                FROM payments
                WHERE order_id = ?
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$orderId]);
            $payment = $stmt->fetch();

            if (!$payment) {
                $result = [
                    'order_id' => $orderId,
                    'order_status' => $order['status'],
                    'payment_status' => 'not_found',
                    'message' => 'No payment found for this order yet'
                ];
            } else {
                $result = [
                    'order_id' => $orderId,
                    'order_status' => $order['status'],
                    'order_number' => $order['order_number'],
                    'payment' => [
                        'payment_id' => $payment['payment_id'],
                        'status' => $payment['payment_status'],
                        'amount' => number_format($payment['amount'], 2, '.', ''),
                        'currency' => $payment['currency'],
                        'payment_method' => $payment['payment_method'],
                        'payment_type' => $payment['payment_type'],
                        'payer_email' => $payment['payer_email'],
                        'payer_name' => $payment['payer_name'],
                        'created_at' => $payment['created_at'],
                        'updated_at' => $payment['updated_at']
                    ]
                ];
            }

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (Exception $e) {
            error_log('Get payment error: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Obtener información detallada de un pago específico
     * GET /api/payments/detail/{paymentId}
     */
    public function getPaymentDetail(Request $request, Response $response, array $args): Response
    {
        try {
            $paymentId = $args['paymentId'];
            $userId = $request->getAttribute('user_id');

            // Buscar el pago y verificar que pertenece a una orden del usuario
            $stmt = $this->db->prepare("
                SELECT p.*, o.customer_id
                FROM payments p
                JOIN orders o ON p.order_id = o.id
                WHERE p.payment_id = ?
            ");
            $stmt->execute([$paymentId]);
            $payment = $stmt->fetch();

            if (!$payment) {
                $response->getBody()->write(json_encode(['error' => 'Payment not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Verificar que pertenece al usuario
            if ($payment['customer_id'] != $userId) {
                $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            // Obtener info actualizada desde Mercado Pago
            try {
                $mpInfo = $this->mpService->getPaymentInfo($paymentId);

                $result = [
                    'payment_id' => $payment['payment_id'],
                    'order_id' => $payment['order_id'],
                    'status' => $mpInfo['status'],
                    'status_detail' => $mpInfo['status_detail'],
                    'amount' => number_format($mpInfo['transaction_amount'], 2, '.', ''),
                    'currency' => $mpInfo['currency_id'],
                    'payment_method' => $mpInfo['payment_method_id'],
                    'payment_type' => $mpInfo['payment_type_id'],
                    'payer' => $mpInfo['payer'],
                    'date_created' => $mpInfo['date_created'],
                    'date_approved' => $mpInfo['date_approved']
                ];
            } catch (Exception $e) {
                // Si falla la consulta a MP, usar datos de BD
                $result = [
                    'payment_id' => $payment['payment_id'],
                    'order_id' => $payment['order_id'],
                    'status' => $payment['payment_status'],
                    'amount' => number_format($payment['amount'], 2, '.', ''),
                    'currency' => $payment['currency'],
                    'payment_method' => $payment['payment_method'],
                    'payment_type' => $payment['payment_type'],
                    'payer_email' => $payment['payer_email'],
                    'payer_name' => $payment['payer_name'],
                    'created_at' => $payment['created_at'],
                    'note' => 'Data from database (Mercado Pago API unavailable)'
                ];
            }

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (Exception $e) {
            error_log('Get payment detail error: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Listar todos los pagos del usuario
     * GET /api/payments
     */
    public function getAllPayments(Request $request, Response $response): Response
    {
        try {
            $userId = $request->getAttribute('user_id');

            $stmt = $this->db->prepare("
                SELECT
                    p.payment_id, p.payment_method, p.payment_status,
                    p.amount, p.currency, p.created_at,
                    o.order_number, o.id as order_id
                FROM payments p
                JOIN orders o ON p.order_id = o.id
                WHERE o.customer_id = ?
                ORDER BY p.created_at DESC
            ");
            $stmt->execute([$userId]);
            $payments = $stmt->fetchAll();

            $result = [
                'total' => count($payments),
                'payments' => array_map(function($payment) {
                    return [
                        'payment_id' => $payment['payment_id'],
                        'order_id' => $payment['order_id'],
                        'order_number' => $payment['order_number'],
                        'status' => $payment['payment_status'],
                        'amount' => number_format($payment['amount'], 2, '.', ''),
                        'currency' => $payment['currency'],
                        'payment_method' => $payment['payment_method'],
                        'created_at' => $payment['created_at']
                    ];
                }, $payments)
            ];

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (Exception $e) {
            error_log('Get all payments error: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
