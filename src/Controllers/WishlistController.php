<?php

namespace App\Controllers;

use App\Models\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

class WishlistController
{
    private $db;

    public function __construct(Database $database)
    {
        $this->db = $database->getConnection();
    }

    /**
     * Obtener wishlist del usuario
     */
    public function getWishlist(Request $request, Response $response): Response
    {
        try {
            $user = $request->getAttribute('user');
            $userId = $user->user_id ?? null;

            if (!$userId) {
                $response->getBody()->write(json_encode(['error' => 'Unauthorized - Invalid user']));
                return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
            }

            $stmt = $this->db->prepare("
                SELECT
                    w.id as wishlist_id,
                    w.created_at as added_at,
                    p.id as product_id,
                    p.name,
                    p.slug,
                    p.sku,
                    p.price,
                    p.sale_price,
                    p.stock,
                    p.status,
                    (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image_path
                FROM wishlists w
                JOIN products p ON w.product_id = p.id
                WHERE w.user_id = :user_id
                ORDER BY w.created_at DESC
            ");
            $stmt->execute(['user_id' => $userId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Construir URLs completas de imágenes y calcular precio final
            // SIEMPRE usar URL de producción para las imágenes
            $baseUrl = 'https://decohomesinrival.com.ar/ecommerce-api/public/uploads/';

            foreach ($items as &$item) {
                // URL de imagen completa - SIEMPRE PRODUCCIÓN
                if ($item['image_path']) {
                    $item['image_url'] = $baseUrl . ltrim($item['image_path'], '/');
                } else {
                    $item['image_url'] = null;
                }

                // Precio final (usar sale_price si existe)
                $item['final_price'] = $item['sale_price'] ?? $item['price'];

                // Formato de precios
                $item['price'] = number_format($item['price'], 2, '.', '');
                if ($item['sale_price']) {
                    $item['sale_price'] = number_format($item['sale_price'], 2, '.', '');
                }
                $item['final_price'] = number_format($item['final_price'], 2, '.', '');

                // Calcular descuento en porcentaje si hay sale_price
                if ($item['sale_price']) {
                    $discount = (($item['price'] - $item['sale_price']) / $item['price']) * 100;
                    $item['discount_percentage'] = round($discount);
                } else {
                    $item['discount_percentage'] = 0;
                }

                // Disponibilidad
                $item['in_stock'] = $item['stock'] > 0 && $item['status'] === 'active';
            }

            $result = [
                'total' => count($items),
                'items' => $items
            ];

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Agregar producto a wishlist
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

            $user = $request->getAttribute('user');
            $userId = $user->user_id ?? null;

            if (!$userId) {
                $response->getBody()->write(json_encode(['error' => 'Unauthorized - Invalid user']));
                return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
            }

            if (!isset($data['product_id'])) {
                $response->getBody()->write(json_encode(['error' => 'Product ID is required']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $productId = intval($data['product_id']);

            // Verificar que el producto existe
            $stmt = $this->db->prepare("SELECT id FROM products WHERE id = :id");
            $stmt->execute(['id' => $productId]);
            if (!$stmt->fetch()) {
                $response->getBody()->write(json_encode(['error' => 'Product not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Intentar insertar (ignorar si ya existe)
            $stmt = $this->db->prepare("
                INSERT INTO wishlists (user_id, product_id)
                VALUES (:user_id, :product_id)
                ON DUPLICATE KEY UPDATE product_id = product_id
            ");
            $stmt->execute(['user_id' => $userId, 'product_id' => $productId]);

            $response->getBody()->write(json_encode(['message' => 'Product added to wishlist']));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Eliminar producto de wishlist
     */
    public function removeItem(Request $request, Response $response, array $args): Response
    {
        try {
            $productId = intval($args['product_id']);
            $user = $request->getAttribute('user');
            $userId = $user->user_id ?? null;

            if (!$userId) {
                $response->getBody()->write(json_encode(['error' => 'Unauthorized - Invalid user']));
                return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
            }

            $stmt = $this->db->prepare("DELETE FROM wishlists WHERE user_id = :user_id AND product_id = :product_id");
            $stmt->execute(['user_id' => $userId, 'product_id' => $productId]);

            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode(['error' => 'Item not found in wishlist']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode(['message' => 'Product removed from wishlist']));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
