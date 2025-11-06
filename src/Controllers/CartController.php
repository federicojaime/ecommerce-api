<?php

namespace App\Controllers;

use App\Models\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

class CartController
{
    private $db;

    public function __construct(Database $database)
    {
        $this->db = $database->getConnection();
    }

    /**
     * Obtener carrito actual del usuario
     */
    public function getCart(Request $request, Response $response): Response
    {
        try {
            $user = $request->getAttribute('user');
            $userId = $user->user_id ?? null;
            $sessionId = $this->getSessionId($request);

            // Buscar o crear carrito
            $cart = $this->findOrCreateCart($userId, $sessionId);

            // Obtener items del carrito con información de productos
            $stmt = $this->db->prepare("
                SELECT
                    ci.*,
                    p.name as product_name,
                    p.slug,
                    p.sku,
                    p.stock,
                    p.status as product_status,
                    p.price as current_price,
                    p.sale_price,
                    (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image,
                    (ci.quantity * ci.price) as subtotal
                FROM cart_items ci
                JOIN products p ON ci.product_id = p.id
                WHERE ci.cart_id = :cart_id
                ORDER BY ci.created_at DESC
            ");
            $stmt->execute(['cart_id' => $cart['id']]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcular totales
            $subtotal = 0;
            foreach ($items as &$item) {
                $subtotal += floatval($item['subtotal']);

                // Verificar si el precio cambió
                $currentPrice = $item['sale_price'] ?? $item['current_price'];
                $item['price_changed'] = (floatval($item['price']) != floatval($currentPrice));
            }

            $data = [
                'cart_id' => $cart['id'],
                'items' => $items,
                'totals' => [
                    'subtotal' => number_format($subtotal, 2, '.', ''),
                    'items_count' => count($items)
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
     * Agregar producto al carrito
     */
    public function addItem(Request $request, Response $response): Response
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
            $sessionId = $this->getSessionId($request);

            // Validar datos
            if (!isset($data['product_id']) || !isset($data['quantity'])) {
                $response->getBody()->write(json_encode(['error' => 'Product ID and quantity are required']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $productId = intval($data['product_id']);
            $quantity = intval($data['quantity']);

            if ($quantity <= 0) {
                $response->getBody()->write(json_encode(['error' => 'Quantity must be greater than 0']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Verificar que el producto existe y está disponible
            $stmt = $this->db->prepare("SELECT * FROM products WHERE id = :id AND status = 'active'");
            $stmt->execute(['id' => $productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                $response->getBody()->write(json_encode(['error' => 'Product not found or not available']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Verificar stock
            if ($product['stock'] < $quantity) {
                $response->getBody()->write(json_encode([
                    'error' => 'Insufficient stock',
                    'available_stock' => $product['stock']
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Buscar o crear carrito
            $cart = $this->findOrCreateCart($userId, $sessionId);

            // Verificar si el producto ya está en el carrito
            $stmt = $this->db->prepare("SELECT * FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id");
            $stmt->execute(['cart_id' => $cart['id'], 'product_id' => $productId]);
            $existingItem = $stmt->fetch(PDO::FETCH_ASSOC);

            $price = $product['sale_price'] ?? $product['price'];

            if ($existingItem) {
                // Actualizar cantidad
                $newQuantity = $existingItem['quantity'] + $quantity;

                if ($product['stock'] < $newQuantity) {
                    $response->getBody()->write(json_encode([
                        'error' => 'Insufficient stock for total quantity',
                        'available_stock' => $product['stock']
                    ]));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }

                $stmt = $this->db->prepare("
                    UPDATE cart_items
                    SET quantity = :quantity, price = :price, updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    'quantity' => $newQuantity,
                    'price' => $price,
                    'id' => $existingItem['id']
                ]);

                $message = 'Cart item updated successfully';
            } else {
                // Agregar nuevo item
                $stmt = $this->db->prepare("
                    INSERT INTO cart_items (cart_id, product_id, quantity, price)
                    VALUES (:cart_id, :product_id, :quantity, :price)
                ");
                $stmt->execute([
                    'cart_id' => $cart['id'],
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'price' => $price
                ]);

                $message = 'Item added to cart successfully';
            }

            // Obtener el carrito actualizado
            return $this->getCart($request, $response);

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Actualizar cantidad de un item
     */
    public function updateItem(Request $request, Response $response, array $args): Response
    {
        try {
            $itemId = intval($args['id']);

            // Leer el body como JSON manualmente
            $body = $request->getBody()->getContents();
            $data = json_decode($body, true);
            if (!$data) {
                $data = $request->getParsedBody();
            }

            $user = $request->getAttribute('user');
            $userId = $user->user_id ?? null;
            $sessionId = $this->getSessionId($request);

            if (!isset($data['quantity'])) {
                $response->getBody()->write(json_encode(['error' => 'Quantity is required']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $quantity = intval($data['quantity']);

            if ($quantity <= 0) {
                $response->getBody()->write(json_encode(['error' => 'Quantity must be greater than 0']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Obtener carrito
            $cart = $this->findOrCreateCart($userId, $sessionId);

            // Verificar que el item pertenece al carrito del usuario
            $stmt = $this->db->prepare("
                SELECT ci.*, p.stock
                FROM cart_items ci
                JOIN products p ON ci.product_id = p.id
                WHERE ci.id = :id AND ci.cart_id = :cart_id
            ");
            $stmt->execute(['id' => $itemId, 'cart_id' => $cart['id']]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                $response->getBody()->write(json_encode(['error' => 'Cart item not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Verificar stock
            if ($item['stock'] < $quantity) {
                $response->getBody()->write(json_encode([
                    'error' => 'Insufficient stock',
                    'available_stock' => $item['stock']
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Actualizar cantidad
            $stmt = $this->db->prepare("
                UPDATE cart_items
                SET quantity = :quantity, updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute(['quantity' => $quantity, 'id' => $itemId]);

            return $this->getCart($request, $response);

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Eliminar item del carrito
     */
    public function removeItem(Request $request, Response $response, array $args): Response
    {
        try {
            $itemId = intval($args['id']);
            $user = $request->getAttribute('user');
            $userId = $user->user_id ?? null;
            $sessionId = $this->getSessionId($request);

            // Obtener carrito
            $cart = $this->findOrCreateCart($userId, $sessionId);

            // Verificar que el item pertenece al carrito del usuario
            $stmt = $this->db->prepare("SELECT * FROM cart_items WHERE id = :id AND cart_id = :cart_id");
            $stmt->execute(['id' => $itemId, 'cart_id' => $cart['id']]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                $response->getBody()->write(json_encode(['error' => 'Cart item not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Eliminar item
            $stmt = $this->db->prepare("DELETE FROM cart_items WHERE id = :id");
            $stmt->execute(['id' => $itemId]);

            $response->getBody()->write(json_encode(['message' => 'Item removed from cart successfully']));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Vaciar carrito
     */
    public function clearCart(Request $request, Response $response): Response
    {
        try {
            $user = $request->getAttribute('user');
            $userId = $user->user_id ?? null;
            $sessionId = $this->getSessionId($request);

            // Obtener carrito
            $cart = $this->findOrCreateCart($userId, $sessionId);

            // Eliminar todos los items
            $stmt = $this->db->prepare("DELETE FROM cart_items WHERE cart_id = :cart_id");
            $stmt->execute(['cart_id' => $cart['id']]);

            $response->getBody()->write(json_encode(['message' => 'Cart cleared successfully']));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Encontrar o crear carrito
     */
    private function findOrCreateCart($userId, $sessionId)
    {
        // Buscar carrito existente
        if ($userId) {
            $stmt = $this->db->prepare("SELECT * FROM carts WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 1");
            $stmt->execute(['user_id' => $userId]);
        } else {
            $stmt = $this->db->prepare("SELECT * FROM carts WHERE session_id = :session_id ORDER BY created_at DESC LIMIT 1");
            $stmt->execute(['session_id' => $sessionId]);
        }

        $cart = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cart) {
            // Crear nuevo carrito
            $stmt = $this->db->prepare("INSERT INTO carts (user_id, session_id) VALUES (:user_id, :session_id)");
            $stmt->execute([
                'user_id' => $userId,
                'session_id' => $sessionId
            ]);

            $cartId = $this->db->lastInsertId();
            $cart = ['id' => $cartId, 'user_id' => $userId, 'session_id' => $sessionId];
        }

        return $cart;
    }

    /**
     * Obtener session ID
     */
    private function getSessionId(Request $request)
    {
        // Obtener de headers o generar uno nuevo
        $headers = $request->getHeaders();
        if (isset($headers['X-Session-ID'])) {
            return $headers['X-Session-ID'][0];
        }

        return session_id() ?: uniqid('cart_', true);
    }
}
