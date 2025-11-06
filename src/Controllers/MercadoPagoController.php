<?php

namespace App\Controllers;

use App\Models\Database;
use App\Services\MercadoPagoService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

class MercadoPagoController
{
    private $db;
    private $mpService;

    public function __construct(Database $database)
    {
        $this->db = $database->getConnection();
        $this->mpService = new MercadoPagoService($database);
    }

    /**
     * Crear preferencia de pago
     * POST /api/checkout/mercadopago/create-preference
     */
    public function createPreference(Request $request, Response $response): Response
    {
        try {
            // Leer el body como JSON manualmente
            $body = $request->getBody()->getContents();
            $data = json_decode($body, true);

            // Si json_decode falla, intentar con getParsedBody()
            if (!$data) {
                $data = $request->getParsedBody();
            }

            // Obtener user_id del token
            $user = $request->getAttribute('user');
            $userId = $user->user_id ?? null;

            // Validar campos requeridos
            $requiredFields = ['customer_name', 'customer_email', 'customer_phone', 'shipping_address'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty(trim($data[$field]))) {
                    $response->getBody()->write(json_encode([
                        'error' => ucfirst(str_replace('_', ' ', $field)) . ' is required'
                    ]));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }
            }

            $this->db->beginTransaction();

            // IMPORTANTE: Obtener items del request o del carrito BD
            $cartItems = [];

            // Si se envían items en el request, usarlos (acepta 'items' o 'cart')
            $requestItems = $data['items'] ?? $data['cart'] ?? null;

            if ($requestItems && is_array($requestItems) && !empty($requestItems)) {
                // Validar y completar datos de cada item desde la BD
                foreach ($requestItems as $item) {
                    if (!isset($item['product_id']) || !isset($item['quantity'])) {
                        $this->db->rollBack();
                        $response->getBody()->write(json_encode(['error' => 'Invalid item data']));
                        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                    }

                    // Obtener datos del producto desde BD
                    $stmt = $this->db->prepare("SELECT * FROM products WHERE id = :id AND status = 'active'");
                    $stmt->execute(['id' => $item['product_id']]);
                    $product = $stmt->fetch();

                    if (!$product) {
                        $this->db->rollBack();
                        $response->getBody()->write(json_encode(['error' => 'Product not found: ' . $item['product_id']]));
                        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
                    }

                    // Verificar stock
                    if ($product['stock'] < $item['quantity']) {
                        $this->db->rollBack();
                        $response->getBody()->write(json_encode([
                            'error' => "Insufficient stock for {$product['name']}",
                            'product_id' => $product['id']
                        ]));
                        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                    }

                    $cartItems[] = [
                        'product_id' => $product['id'],
                        'product_name' => $product['name'],
                        'product_sku' => $product['sku'],
                        'price' => $item['price'] ?? $product['price'], // Usar precio del frontend o del producto
                        'quantity' => intval($item['quantity']),
                        'stock' => $product['stock']
                    ];
                }
            } else {
                // Si no hay items en el request, buscar en carrito BD (comportamiento original)
                $cart = $this->getCart($userId);

                if (!$cart || empty($cart['items'])) {
                    $this->db->rollBack();
                    $response->getBody()->write(json_encode(['error' => 'Cart is empty']));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }

                $cartItems = $cart['items'];
            }

            // Calcular totales
            $subtotal = 0;
            foreach ($cartItems as $item) {
                $subtotal += floatval($item['price']) * floatval($item['quantity']);
            }

            // Aplicar cupón si existe
            $discount = 0;
            $couponId = null;
            if (isset($data['coupon_code']) && !empty($data['coupon_code'])) {
                $couponValidation = $this->validateCoupon($data['coupon_code'], $subtotal);
                if ($couponValidation['valid']) {
                    $discount = $couponValidation['discount'];
                    $couponId = $couponValidation['coupon']['id'];
                }
            }

            $taxRate = $this->getSetting('tax_rate', 0.00);
            $shippingCost = $data['shipping_amount'] ?? $this->getSetting('shipping_cost', 0.00);

            $subtotalAfterDiscount = $subtotal - $discount;
            $taxAmount = ($subtotalAfterDiscount * $taxRate) / 100;
            $total = $subtotalAfterDiscount + $taxAmount + $shippingCost;

            // Generar número de orden
            $orderNumber = 'ORD' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

            // Crear orden en estado 'pending'
            $stmt = $this->db->prepare("
                INSERT INTO orders (
                    order_number, customer_id, customer_name, customer_email, customer_phone,
                    status, subtotal, tax_amount, shipping_amount, total_amount,
                    payment_method, shipping_address, billing_address, notes,
                    coupon_id, discount_amount
                ) VALUES (
                    :order_number, :customer_id, :customer_name, :customer_email, :customer_phone,
                    'pending', :subtotal, :tax_amount, :shipping_amount, :total_amount,
                    'mercadopago', :shipping_address, :billing_address, :notes,
                    :coupon_id, :discount_amount
                )
            ");

            $stmt->execute([
                'order_number' => $orderNumber,
                'customer_id' => $userId,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'],
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'shipping_amount' => $shippingCost,
                'total_amount' => $total,
                'shipping_address' => $data['shipping_address'],
                'billing_address' => $data['billing_address'] ?? $data['shipping_address'],
                'notes' => $data['notes'] ?? null,
                'coupon_id' => $couponId,
                'discount_amount' => $discount
            ]);

            $orderId = $this->db->lastInsertId();

            // Crear items de la orden
            foreach ($cartItems as $item) {
                $stmt = $this->db->prepare("
                    INSERT INTO order_items (order_id, product_id, product_name, product_sku, quantity, price, total)
                    VALUES (:order_id, :product_id, :product_name, :product_sku, :quantity, :price, :total)
                ");
                $stmt->execute([
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'product_sku' => $item['product_sku'] ?? null,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => floatval($item['price']) * floatval($item['quantity'])
                ]);

                // Reducir stock
                $stmt = $this->db->prepare("
                    UPDATE products SET stock = stock - ? WHERE id = ?
                ");
                $stmt->execute([$item['quantity'], $item['product_id']]);
            }

            // Preparar datos para Mercado Pago
            $orderData = [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'items' => $cartItems,
                'shipping_amount' => $shippingCost,
                'total_amount' => $total,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone']
            ];

            // Crear preferencia en Mercado Pago
            $preference = $this->mpService->createPreference($orderData);

            // Guardar preference_id en la orden
            $stmt = $this->db->prepare("UPDATE orders SET notes = ? WHERE id = ?");
            $notes = "Preference ID: " . $preference['preference_id'];
            if ($data['notes'] ?? null) {
                $notes .= "\nNotas: " . $data['notes'];
            }
            $stmt->execute([$notes, $orderId]);

            $this->db->commit();

            // Respuesta
            $result = [
                'success' => true,
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'preference_id' => $preference['preference_id'],
                'init_point' => $preference['init_point'],
                'total_amount' => number_format($total, 2, '.', '')
            ];

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Create preference error: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Webhook de Mercado Pago
     * POST /api/webhooks/mercadopago
     */
    public function handleWebhook(Request $request, Response $response): Response
    {
        try {
            // Obtener datos del webhook
            $body = $request->getBody()->getContents();
            $data = json_decode($body, true);

            // Log para debugging
            error_log('Mercado Pago Webhook received: ' . $body);

            // También puede venir por query params
            if (empty($data)) {
                $queryParams = $request->getQueryParams();
                if (!empty($queryParams)) {
                    $data = $queryParams;
                }
            }

            // Validar firma (opcional)
            $signature = $request->getHeaderLine('X-Signature');
            if ($signature && !$this->mpService->validateWebhookSignature($signature, $body)) {
                error_log('Invalid webhook signature');
                $response->getBody()->write(json_encode(['error' => 'Invalid signature']));
                return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
            }

            // Procesar webhook
            $result = $this->mpService->processWebhook($data);

            $response->getBody()->write(json_encode($result));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');

        } catch (Exception $e) {
            error_log('Webhook error: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Página de éxito
     * GET /api/checkout/mercadopago/success
     */
    public function success(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $orderId = $queryParams['external_reference'] ?? null;
        $paymentId = $queryParams['payment_id'] ?? null;

        $message = [
            'success' => true,
            'message' => 'Payment successful',
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'redirect_url' => '/orders/' . $orderId
        ];

        $response->getBody()->write(json_encode($message));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Página de error
     * GET /api/checkout/mercadopago/failure
     */
    public function failure(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $orderId = $queryParams['external_reference'] ?? null;

        $message = [
            'success' => false,
            'message' => 'Payment failed',
            'order_id' => $orderId,
            'redirect_url' => '/checkout'
        ];

        $response->getBody()->write(json_encode($message));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Página de pendiente
     * GET /api/checkout/mercadopago/pending
     */
    public function pending(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $orderId = $queryParams['external_reference'] ?? null;
        $paymentId = $queryParams['payment_id'] ?? null;

        $message = [
            'success' => true,
            'message' => 'Payment pending',
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'redirect_url' => '/orders/' . $orderId
        ];

        $response->getBody()->write(json_encode($message));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Obtener public key para el frontend
     * GET /api/mercadopago/public-key
     */
    public function getPublicKey(Request $request, Response $response): Response
    {
        $publicKey = $this->mpService->getPublicKey();

        $result = [
            'public_key' => $publicKey
        ];

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    // ========== HELPER METHODS ==========

    private function getCart($userId)
    {
        $stmt = $this->db->prepare("SELECT id FROM carts WHERE user_id = ?");
        $stmt->execute([$userId]);
        $cart = $stmt->fetch();

        if (!$cart) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT ci.*, p.name as product_name, p.sku as product_sku, p.price, p.stock
            FROM cart_items ci
            JOIN products p ON ci.product_id = p.id
            WHERE ci.cart_id = ?
        ");
        $stmt->execute([$cart['id']]);
        $items = $stmt->fetchAll();

        return [
            'cart_id' => $cart['id'],
            'items' => $items
        ];
    }

    private function validateCoupon($code, $subtotal)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM coupons
            WHERE code = ? AND status = 'active'
            AND (valid_from IS NULL OR valid_from <= NOW())
            AND (valid_until IS NULL OR valid_until >= NOW())
            AND (usage_limit IS NULL OR used_count < usage_limit)
        ");
        $stmt->execute([$code]);
        $coupon = $stmt->fetch();

        if (!$coupon) {
            return ['valid' => false];
        }

        if ($subtotal < $coupon['min_purchase']) {
            return ['valid' => false, 'error' => 'Minimum purchase not met'];
        }

        $discount = 0;
        if ($coupon['type'] === 'percentage') {
            $discount = ($subtotal * $coupon['value']) / 100;
            if ($coupon['max_discount'] && $discount > $coupon['max_discount']) {
                $discount = $coupon['max_discount'];
            }
        } else {
            $discount = $coupon['value'];
        }

        return [
            'valid' => true,
            'discount' => $discount,
            'coupon' => $coupon
        ];
    }

    private function getSetting($key, $default = null)
    {
        $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $setting = $stmt->fetch();

        return $setting ? $setting['setting_value'] : $default;
    }
}
