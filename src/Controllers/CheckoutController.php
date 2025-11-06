<?php

namespace App\Controllers;

use App\Models\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

class CheckoutController
{
    private $db;

    public function __construct(Database $database)
    {
        $this->db = $database->getConnection();
    }

    /**
     * Validar datos de checkout antes de procesar
     */
    public function validate(Request $request, Response $response): Response
    {
        try {
            // Leer el body como JSON manualmente
            $body = $request->getBody()->getContents();
            $data = json_decode($body, true);
            if (!$data) {
                $data = $request->getParsedBody();
            }

            $user = $request->getAttribute('user');
            $userId = $user->user_id ?? null;
            $errors = [];

            // Validar campos requeridos
            $requiredFields = ['customer_name', 'customer_email', 'customer_phone', 'shipping_address'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty(trim($data[$field]))) {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
                }
            }

            // Validar email
            if (isset($data['customer_email']) && !filter_var($data['customer_email'], FILTER_VALIDATE_EMAIL)) {
                $errors['customer_email'] = 'Invalid email format';
            }

            // Obtener carrito
            $cart = $this->getCart($userId);
            if (!$cart || empty($cart['items'])) {
                $errors['cart'] = 'Cart is empty';
            }

            // Validar stock de cada producto
            if ($cart && !empty($cart['items'])) {
                foreach ($cart['items'] as $item) {
                    if ($item['stock'] < $item['quantity']) {
                        $errors['stock_' . $item['product_id']] = "Insufficient stock for {$item['product_name']}";
                    }
                }
            }

            $isValid = empty($errors);

            $result = [
                'valid' => $isValid,
                'errors' => $errors
            ];

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Calcular totales del checkout
     */
    public function calculate(Request $request, Response $response): Response
    {
        try {
            // Leer el body como JSON manualmente
            $body = $request->getBody()->getContents();
            $data = json_decode($body, true);
            if (!$data) {
                $data = $request->getParsedBody();
            }

            $user = $request->getAttribute('user');
            $userId = $user->user_id ?? null;

            // Obtener carrito
            $cart = $this->getCart($userId);

            if (!$cart || empty($cart['items'])) {
                $response->getBody()->write(json_encode(['error' => 'Cart is empty']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Calcular subtotal
            $subtotal = 0;
            foreach ($cart['items'] as $item) {
                $subtotal += floatval($item['price']) * floatval($item['quantity']);
            }

            // Obtener configuraciones
            $taxRate = $this->getSetting('tax_rate', 16.00);
            $shippingCost = $this->getSetting('shipping_cost', 50.00);

            // Aplicar cupón si existe
            $discount = 0;
            $couponCode = $data['coupon_code'] ?? null;
            $couponData = null;

            if ($couponCode) {
                $couponValidation = $this->validateCoupon($couponCode, $subtotal);
                if ($couponValidation['valid']) {
                    $discount = $couponValidation['discount'];
                    $couponData = $couponValidation['coupon'];
                }
            }

            // Calcular totales
            $subtotalAfterDiscount = $subtotal - $discount;
            $taxAmount = ($subtotalAfterDiscount * $taxRate) / 100;
            $total = $subtotalAfterDiscount + $taxAmount + $shippingCost;

            $result = [
                'subtotal' => number_format($subtotal, 2, '.', ''),
                'discount' => number_format($discount, 2, '.', ''),
                'subtotal_after_discount' => number_format($subtotalAfterDiscount, 2, '.', ''),
                'tax_rate' => $taxRate,
                'tax_amount' => number_format($taxAmount, 2, '.', ''),
                'shipping_amount' => number_format($shippingCost, 2, '.', ''),
                'total_amount' => number_format($total, 2, '.', ''),
                'coupon' => $couponData
            ];

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Completar la compra
     */
    public function complete(Request $request, Response $response): Response
    {
        try {
            $this->db->beginTransaction();

            // Leer el body como JSON manualmente
            $body = $request->getBody()->getContents();
            $data = json_decode($body, true);
            if (!$data) {
                $data = $request->getParsedBody();
            }

            $user = $request->getAttribute('user');
            $userId = $user->user_id ?? null;

            // Validar datos
            $requiredFields = ['customer_name', 'customer_email', 'customer_phone', 'shipping_address', 'payment_method'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty(trim($data[$field]))) {
                    $this->db->rollBack();
                    $response->getBody()->write(json_encode(['error' => ucfirst(str_replace('_', ' ', $field)) . ' is required']));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }
            }

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
                    $product = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$product) {
                        $this->db->rollBack();
                        $response->getBody()->write(json_encode(['error' => 'Product not found: ' . $item['product_id']]));
                        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
                    }

                    $cartItems[] = [
                        'product_id' => $product['id'],
                        'product_name' => $product['name'],
                        'sku' => $product['sku'],
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
                // Verificar stock
                if ($item['stock'] < $item['quantity']) {
                    $this->db->rollBack();
                    $response->getBody()->write(json_encode([
                        'error' => "Insufficient stock for {$item['product_name']}",
                        'product_id' => $item['product_id']
                    ]));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }
                $subtotal += floatval($item['price']) * floatval($item['quantity']);
            }

            // Aplicar cupón
            $discount = 0;
            $couponId = null;
            if (isset($data['coupon_code']) && !empty($data['coupon_code'])) {
                $couponValidation = $this->validateCoupon($data['coupon_code'], $subtotal);
                if ($couponValidation['valid']) {
                    $discount = $couponValidation['discount'];
                    $couponId = $couponValidation['coupon']['id'];
                }
            }

            $taxRate = $this->getSetting('tax_rate', 16.00);
            $shippingCost = $data['shipping_amount'] ?? $this->getSetting('shipping_cost', 50.00);

            $subtotalAfterDiscount = $subtotal - $discount;
            $taxAmount = ($subtotalAfterDiscount * $taxRate) / 100;
            $total = $subtotalAfterDiscount + $taxAmount + $shippingCost;

            // Generar número de orden
            $orderNumber = 'ORD' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

            // Crear orden
            $stmt = $this->db->prepare("
                INSERT INTO orders (
                    order_number, customer_id, customer_name, customer_email, customer_phone,
                    status, subtotal, tax_amount, shipping_amount, total_amount,
                    payment_method, shipping_address, billing_address, notes,
                    coupon_id, discount_amount
                ) VALUES (
                    :order_number, :customer_id, :customer_name, :customer_email, :customer_phone,
                    'pending', :subtotal, :tax_amount, :shipping_amount, :total_amount,
                    :payment_method, :shipping_address, :billing_address, :notes,
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
                'payment_method' => $data['payment_method'],
                'shipping_address' => $data['shipping_address'],
                'billing_address' => $data['billing_address'] ?? $data['shipping_address'],
                'notes' => $data['notes'] ?? null,
                'coupon_id' => $couponId,
                'discount_amount' => $discount
            ]);

            $orderId = $this->db->lastInsertId();

            // Crear items de la orden y reducir stock
            foreach ($cartItems as $item) {
                // Insertar item
                $stmt = $this->db->prepare("
                    INSERT INTO order_items (order_id, product_id, product_name, product_sku, quantity, price, total)
                    VALUES (:order_id, :product_id, :product_name, :product_sku, :quantity, :price, :total)
                ");
                $stmt->execute([
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'product_sku' => $item['sku'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => floatval($item['price']) * floatval($item['quantity'])
                ]);

                // Reducir stock
                $stmt = $this->db->prepare("UPDATE products SET stock = stock - :quantity WHERE id = :id");
                $stmt->execute(['quantity' => $item['quantity'], 'id' => $item['product_id']]);
            }

            // Limpiar carrito SOLO si se usó el carrito BD (no si se enviaron items directamente)
            if (isset($cart) && isset($cart['cart_id'])) {
                $stmt = $this->db->prepare("DELETE FROM cart_items WHERE cart_id = :cart_id");
                $stmt->execute(['cart_id' => $cart['cart_id']]);
            }

            // Registrar uso de cupón
            if ($couponId) {
                $stmt = $this->db->prepare("
                    INSERT INTO coupon_usage (coupon_id, user_id, order_id, discount_amount)
                    VALUES (:coupon_id, :user_id, :order_id, :discount_amount)
                ");
                $stmt->execute([
                    'coupon_id' => $couponId,
                    'user_id' => $userId,
                    'order_id' => $orderId,
                    'discount_amount' => $discount
                ]);

                // Incrementar contador de uso
                $stmt = $this->db->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = :id");
                $stmt->execute(['id' => $couponId]);
            }

            $this->db->commit();

            $result = [
                'message' => 'Order created successfully',
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'total_amount' => number_format($total, 2, '.', '')
            ];

            $response->getBody()->write(json_encode($result));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $this->db->rollBack();
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Obtener carrito del usuario
     */
    private function getCart($userId)
    {
        $stmt = $this->db->prepare("SELECT * FROM carts WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 1");
        $stmt->execute(['user_id' => $userId]);
        $cart = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cart) {
            return null;
        }

        // Obtener items
        $stmt = $this->db->prepare("
            SELECT ci.*, p.name as product_name, p.sku, p.stock, p.status as product_status
            FROM cart_items ci
            JOIN products p ON ci.product_id = p.id
            WHERE ci.cart_id = :cart_id
        ");
        $stmt->execute(['cart_id' => $cart['id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['cart_id' => $cart['id'], 'items' => $items];
    }

    /**
     * Validar cupón
     */
    private function validateCoupon($code, $subtotal)
    {
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
            return ['valid' => false, 'error' => 'Invalid or expired coupon'];
        }

        // Validar monto mínimo
        if ($subtotal < $coupon['min_purchase']) {
            return [
                'valid' => false,
                'error' => "Minimum purchase of {$coupon['min_purchase']} required"
            ];
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

        return [
            'valid' => true,
            'discount' => $discount,
            'coupon' => $coupon
        ];
    }

    /**
     * Obtener configuración
     */
    private function getSetting($key, $default)
    {
        // Aquí deberías implementar la lógica para obtener settings de la BD
        // Por ahora retornamos el valor por defecto
        return $default;
    }
}
