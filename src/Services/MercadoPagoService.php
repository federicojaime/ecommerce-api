<?php

namespace App\Services;

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;
use Exception;

class MercadoPagoService
{
    private $accessToken;
    private $publicKey;
    private $webhookSecret;
    private $db;

    public function __construct($database = null)
    {
        $this->accessToken = $_ENV['MP_ACCESS_TOKEN'] ?? null;
        $this->publicKey = $_ENV['MP_PUBLIC_KEY'] ?? null;
        $this->webhookSecret = $_ENV['MP_WEBHOOK_SECRET'] ?? null;

        if (!$this->accessToken) {
            throw new Exception('Mercado Pago access token not configured');
        }

        // Configurar SDK v3.x
        MercadoPagoConfig::setAccessToken($this->accessToken);

        if ($database) {
            $this->db = $database->getConnection();
        }
    }

    /**
     * Crear preferencia de pago
     */
    public function createPreference($orderData)
    {
        try {
            $client = new PreferenceClient();

            // Items
            $items = [];
            foreach ($orderData['items'] as $item) {
                $items[] = [
                    'id' => isset($item['product_id']) ? (string)$item['product_id'] : null,
                    'title' => $item['product_name'],
                    'quantity' => (int)$item['quantity'],
                    'unit_price' => (float)$item['price'],
                    'currency_id' => 'ARS'
                ];
            }

            // Agregar shipping como item si existe
            if (isset($orderData['shipping_amount']) && $orderData['shipping_amount'] > 0) {
                $items[] = [
                    'title' => 'Envío',
                    'quantity' => 1,
                    'unit_price' => (float)$orderData['shipping_amount'],
                    'currency_id' => 'ARS'
                ];
            }

            // URLs de retorno - SIEMPRE usar URL de producción
            $baseUrl = 'https://decohomesinrival.com.ar';

            // Separar nombre en first_name y last_name
            $nameParts = explode(' ', $orderData['customer_name'] ?? 'Cliente', 2);
            $firstName = $nameParts[0];
            $lastName = isset($nameParts[1]) ? $nameParts[1] : 'Apellido';

            // Limpiar número de teléfono (quitar + y espacios)
            $phoneNumber = preg_replace('/[^0-9]/', '', $orderData['customer_phone'] ?? '');

            // Construir request
            $request = [
                'items' => $items,
                'payer' => [
                    'name' => $firstName,
                    'surname' => $lastName,
                    'email' => $orderData['customer_email'] ?? '',
                    'phone' => [
                        'area_code' => '',
                        'number' => $phoneNumber
                    ]
                ],
                'external_reference' => (string)$orderData['order_id'],
                'back_urls' => [
                    'success' => $baseUrl . '/checkout/success',
                    'failure' => $baseUrl . '/checkout/failure',
                    'pending' => $baseUrl . '/checkout/pending'
                ],
                'auto_return' => 'approved',
                'notification_url' => $baseUrl . '/ecommerce-api/public/api/webhooks/mercadopago',
                'statement_descriptor' => 'DECOHOMES'
            ];

            // Log del request para debugging
            error_log('MercadoPago Request: ' . json_encode($request, JSON_PRETTY_PRINT));

            // Crear preferencia
            $preference = $client->create($request);

            if (!$preference->id) {
                throw new Exception('Error creating Mercado Pago preference');
            }

            error_log('MercadoPago Preference created successfully: ' . $preference->id);

            return [
                'preference_id' => $preference->id,
                'init_point' => $preference->init_point,
                'sandbox_init_point' => $preference->sandbox_init_point ?? null
            ];

        } catch (MPApiException $e) {
            error_log('MercadoPago API error: ' . $e->getMessage());

            // Obtener detalles del error
            $errorMessage = $e->getMessage();

            // Intentar obtener más detalles si están disponibles
            try {
                $apiResponse = $e->getApiResponse();
                if ($apiResponse) {
                    // Convertir a array si es objeto
                    $responseData = json_decode(json_encode($apiResponse), true);
                    error_log('API Response: ' . json_encode($responseData, JSON_PRETTY_PRINT));

                    if (isset($responseData['message'])) {
                        $errorMessage = $responseData['message'];
                    } elseif (isset($responseData['error'])) {
                        $errorMessage = $responseData['error'];
                    } elseif (isset($responseData['cause'])) {
                        $errorMessage = json_encode($responseData['cause']);
                    }
                }
            } catch (Exception $ex) {
                error_log('Could not parse API response: ' . $ex->getMessage());
            }

            throw new Exception('Error creating payment preference: ' . $errorMessage);
        } catch (Exception $e) {
            error_log('MercadoPago createPreference error: ' . $e->getMessage());
            throw new Exception('Error creating payment preference: ' . $e->getMessage());
        }
    }

    /**
     * Obtener información de un pago
     */
    public function getPaymentInfo($paymentId)
    {
        try {
            $client = new PaymentClient();
            $payment = $client->get($paymentId);

            if (!$payment) {
                throw new Exception('Payment not found');
            }

            return [
                'id' => $payment->id,
                'status' => $payment->status,
                'status_detail' => $payment->status_detail,
                'payment_method_id' => $payment->payment_method_id,
                'payment_type_id' => $payment->payment_type_id,
                'transaction_amount' => $payment->transaction_amount,
                'currency_id' => $payment->currency_id,
                'external_reference' => $payment->external_reference ?? null,
                'payer' => [
                    'email' => $payment->payer->email ?? null,
                    'first_name' => $payment->payer->first_name ?? null,
                    'last_name' => $payment->payer->last_name ?? null,
                    'identification' => $payment->payer->identification ?? null
                ],
                'date_created' => $payment->date_created,
                'date_approved' => $payment->date_approved ?? null,
                'full_data' => $payment
            ];

        } catch (MPApiException $e) {
            error_log('MercadoPago API error: ' . $e->getMessage());
            throw new Exception('Error getting payment info: ' . $e->getMessage());
        } catch (Exception $e) {
            error_log('MercadoPago getPaymentInfo error: ' . $e->getMessage());
            throw new Exception('Error getting payment info: ' . $e->getMessage());
        }
    }

    /**
     * Procesar webhook de Mercado Pago
     */
    public function processWebhook($webhookData)
    {
        try {
            if (!$this->db) {
                throw new Exception('Database not available');
            }

            // Guardar webhook para auditoría
            $webhookId = $this->saveWebhook($webhookData);

            // Extraer datos del webhook
            $topic = $webhookData['topic'] ?? $webhookData['type'] ?? null;
            $resourceId = $webhookData['data']['id'] ?? $webhookData['id'] ?? null;

            if (!$topic || !$resourceId) {
                throw new Exception('Invalid webhook data');
            }

            error_log("Processing webhook - Topic: $topic, ID: $resourceId");

            // Procesar según el tipo
            if ($topic === 'payment' || $topic === 'payment.created' || $topic === 'payment.updated') {
                return $this->processPaymentWebhook($resourceId, $webhookId);
            }

            return ['status' => 'ignored', 'message' => 'Webhook type not handled'];

        } catch (Exception $e) {
            error_log('MercadoPago processWebhook error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Procesar webhook de pago
     */
    private function processPaymentWebhook($paymentId, $webhookId)
    {
        try {
            $this->db->beginTransaction();

            // Obtener info del pago desde MP
            $paymentInfo = $this->getPaymentInfo($paymentId);

            // Buscar la orden por external_reference
            $orderId = $paymentInfo['external_reference'];

            if (!$orderId) {
                throw new Exception('No external reference in payment');
            }

            // Verificar que la orden existe
            $stmt = $this->db->prepare("SELECT * FROM orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new Exception("Order not found: $orderId");
            }

            // Verificar que el monto coincida
            $expectedAmount = (float)$order['total_amount'];
            $paidAmount = (float)$paymentInfo['transaction_amount'];

            if (abs($expectedAmount - $paidAmount) > 0.01) {
                error_log("Amount mismatch - Expected: $expectedAmount, Paid: $paidAmount");
                throw new Exception('Payment amount does not match order total');
            }

            // Verificar si el pago ya fue procesado
            $stmt = $this->db->prepare("SELECT id FROM payments WHERE payment_id = ?");
            $stmt->execute([$paymentId]);
            $existingPayment = $stmt->fetch();

            if ($existingPayment) {
                // Actualizar payment existente
                $this->updatePayment($paymentId, $paymentInfo);
            } else {
                // Crear nuevo payment
                $this->createPayment($orderId, $paymentInfo);
            }

            // Actualizar estado de la orden según el estado del pago
            $this->updateOrderStatus($orderId, $paymentInfo['status']);

            // Si el pago fue aprobado, vaciar el carrito
            if ($paymentInfo['status'] === 'approved') {
                $this->clearCart($order['customer_id']);
            }

            // Marcar webhook como procesado
            $stmt = $this->db->prepare("UPDATE mercadopago_webhooks SET processed = TRUE, processed_at = NOW() WHERE id = ?");
            $stmt->execute([$webhookId]);

            $this->db->commit();

            return [
                'status' => 'success',
                'order_id' => $orderId,
                'payment_status' => $paymentInfo['status']
            ];

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            // Guardar error en webhook
            if (isset($webhookId)) {
                $stmt = $this->db->prepare("UPDATE mercadopago_webhooks SET error_message = ? WHERE id = ?");
                $stmt->execute([$e->getMessage(), $webhookId]);
            }

            throw $e;
        }
    }

    /**
     * Crear registro de pago
     */
    private function createPayment($orderId, $paymentInfo)
    {
        $stmt = $this->db->prepare("
            INSERT INTO payments (
                order_id, payment_id, payment_method, payment_type, payment_status,
                amount, currency, external_reference, payment_data,
                payer_email, payer_name, payer_identification
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $payerName = ($paymentInfo['payer']['first_name'] ?? '') . ' ' . ($paymentInfo['payer']['last_name'] ?? '');
        $payerIdentification = isset($paymentInfo['payer']['identification'])
            ? json_encode($paymentInfo['payer']['identification'])
            : null;

        $stmt->execute([
            $orderId,
            $paymentInfo['id'],
            $paymentInfo['payment_method_id'],
            $paymentInfo['payment_type_id'],
            $paymentInfo['status'],
            $paymentInfo['transaction_amount'],
            $paymentInfo['currency_id'],
            $paymentInfo['external_reference'],
            json_encode($paymentInfo['full_data']),
            $paymentInfo['payer']['email'] ?? null,
            trim($payerName),
            $payerIdentification
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Actualizar registro de pago
     */
    private function updatePayment($paymentId, $paymentInfo)
    {
        $stmt = $this->db->prepare("
            UPDATE payments
            SET payment_status = ?, payment_data = ?, updated_at = NOW()
            WHERE payment_id = ?
        ");

        $stmt->execute([
            $paymentInfo['status'],
            json_encode($paymentInfo['full_data']),
            $paymentId
        ]);
    }

    /**
     * Actualizar estado de orden
     */
    private function updateOrderStatus($orderId, $paymentStatus)
    {
        $orderStatus = $this->mapPaymentStatusToOrderStatus($paymentStatus);

        $stmt = $this->db->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$orderStatus, $orderId]);

        error_log("Order $orderId status updated to: $orderStatus (payment status: $paymentStatus)");
    }

    /**
     * Mapear estado de pago MP a estado de orden
     */
    private function mapPaymentStatusToOrderStatus($paymentStatus)
    {
        $statusMap = [
            'approved' => 'paid',
            'pending' => 'pending',
            'in_process' => 'pending',
            'rejected' => 'cancelled',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded'
        ];

        return $statusMap[$paymentStatus] ?? 'pending';
    }

    /**
     * Vaciar carrito del usuario
     */
    private function clearCart($userId)
    {
        if (!$userId) {
            return;
        }

        // Obtener cart_id
        $stmt = $this->db->prepare("SELECT id FROM carts WHERE user_id = ?");
        $stmt->execute([$userId]);
        $cart = $stmt->fetch();

        if ($cart) {
            // Eliminar items del carrito
            $stmt = $this->db->prepare("DELETE FROM cart_items WHERE cart_id = ?");
            $stmt->execute([$cart['id']]);

            error_log("Cart cleared for user: $userId");
        }
    }

    /**
     * Guardar webhook para auditoría
     */
    private function saveWebhook($webhookData)
    {
        $stmt = $this->db->prepare("
            INSERT INTO mercadopago_webhooks (
                event_type, event_action, data_id, webhook_data, ip_address
            ) VALUES (?, ?, ?, ?, ?)
        ");

        $eventType = $webhookData['topic'] ?? $webhookData['type'] ?? 'unknown';
        $eventAction = $webhookData['action'] ?? null;
        $dataId = $webhookData['data']['id'] ?? $webhookData['id'] ?? null;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

        $stmt->execute([
            $eventType,
            $eventAction,
            $dataId,
            json_encode($webhookData),
            $ipAddress
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Obtener URL base de la aplicación
     */
    private function getBaseUrl()
    {
        // Primero intentar con la URL del servidor
        if (isset($_SERVER['HTTP_HOST'])) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            return $protocol . '://' . $_SERVER['HTTP_HOST'];
        }

        // Fallback a URL configurada (para producción)
        return 'https://decohomesinrival.com.ar';
    }

    /**
     * Validar firma de webhook (opcional, para mayor seguridad)
     */
    public function validateWebhookSignature($signature, $data)
    {
        if (!$this->webhookSecret) {
            return true; // Si no hay secret configurado, no validar
        }

        $expectedSignature = hash_hmac('sha256', $data, $this->webhookSecret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Obtener public key para el frontend
     */
    public function getPublicKey()
    {
        return $this->publicKey;
    }
}
