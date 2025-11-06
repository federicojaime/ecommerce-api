<?php

namespace App\Controllers;

use App\Models\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

class ReviewController
{
    private $db;

    public function __construct(Database $database)
    {
        $this->db = $database->getConnection();
    }

    /**
     * Obtener reseñas de un producto
     */
    public function getProductReviews(Request $request, Response $response, array $args): Response
    {
        try {
            $productId = intval($args['product_id']);

            $stmt = $this->db->prepare("
                SELECT
                    r.*,
                    u.name as user_name
                FROM product_reviews r
                JOIN users u ON r.user_id = u.id
                WHERE r.product_id = :product_id AND r.status = 'approved'
                ORDER BY r.created_at DESC
            ");
            $stmt->execute(['product_id' => $productId]);
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcular estadísticas
            $avgRating = 0;
            if (!empty($reviews)) {
                $totalRating = array_sum(array_column($reviews, 'rating'));
                $avgRating = $totalRating / count($reviews);
            }

            $data = [
                'reviews' => $reviews,
                'stats' => [
                    'average_rating' => round($avgRating, 2),
                    'total_reviews' => count($reviews)
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
     * Crear reseña
     */
    public function createReview(Request $request, Response $response, array $args): Response
    {
        try {
            $productId = intval($args['product_id']);
            $userId = $request->getAttribute('user_id');
            $data = $request->getParsedBody();

            // Validar datos
            if (!isset($data['rating']) || $data['rating'] < 1 || $data['rating'] > 5) {
                $response->getBody()->write(json_encode(['error' => 'Rating must be between 1 and 5']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Verificar que el producto existe
            $stmt = $this->db->prepare("SELECT id FROM products WHERE id = :id");
            $stmt->execute(['id' => $productId]);
            if (!$stmt->fetch()) {
                $response->getBody()->write(json_encode(['error' => 'Product not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Verificar que el usuario compró el producto
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                WHERE o.customer_id = :user_id AND oi.product_id = :product_id
            ");
            $stmt->execute(['user_id' => $userId, 'product_id' => $productId]);
            $hasPurchased = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;

            if (!$hasPurchased) {
                $response->getBody()->write(json_encode(['error' => 'You must purchase this product before reviewing']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            // Verificar que no ha dejado reseña previamente
            $stmt = $this->db->prepare("
                SELECT id FROM product_reviews
                WHERE user_id = :user_id AND product_id = :product_id
            ");
            $stmt->execute(['user_id' => $userId, 'product_id' => $productId]);
            if ($stmt->fetch()) {
                $response->getBody()->write(json_encode(['error' => 'You have already reviewed this product']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Crear reseña
            $stmt = $this->db->prepare("
                INSERT INTO product_reviews (product_id, user_id, rating, title, comment, status)
                VALUES (:product_id, :user_id, :rating, :title, :comment, 'pending')
            ");
            $stmt->execute([
                'product_id' => $productId,
                'user_id' => $userId,
                'rating' => intval($data['rating']),
                'title' => $data['title'] ?? null,
                'comment' => $data['comment'] ?? null
            ]);

            $reviewId = $this->db->lastInsertId();

            $result = [
                'message' => 'Review submitted successfully and is pending approval',
                'review_id' => $reviewId
            ];

            $response->getBody()->write(json_encode($result));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Actualizar mi reseña
     */
    public function updateReview(Request $request, Response $response, array $args): Response
    {
        try {
            $reviewId = intval($args['id']);
            $userId = $request->getAttribute('user_id');
            $data = $request->getParsedBody();

            // Verificar que la reseña existe y pertenece al usuario
            $stmt = $this->db->prepare("SELECT * FROM product_reviews WHERE id = :id AND user_id = :user_id");
            $stmt->execute(['id' => $reviewId, 'user_id' => $userId]);
            $review = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$review) {
                $response->getBody()->write(json_encode(['error' => 'Review not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Construir actualización
            $updates = [];
            $params = ['id' => $reviewId];

            if (isset($data['rating'])) {
                if ($data['rating'] < 1 || $data['rating'] > 5) {
                    $response->getBody()->write(json_encode(['error' => 'Rating must be between 1 and 5']));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }
                $updates[] = 'rating = :rating';
                $params['rating'] = intval($data['rating']);
            }

            if (isset($data['title'])) {
                $updates[] = 'title = :title';
                $params['title'] = $data['title'];
            }

            if (isset($data['comment'])) {
                $updates[] = 'comment = :comment';
                $params['comment'] = $data['comment'];
            }

            if (empty($updates)) {
                $response->getBody()->write(json_encode(['error' => 'No fields to update']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Actualizar y volver a pending
            $updates[] = "status = 'pending'";
            $sql = "UPDATE product_reviews SET " . implode(', ', $updates) . " WHERE id = :id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $response->getBody()->write(json_encode(['message' => 'Review updated successfully']));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Eliminar mi reseña
     */
    public function deleteReview(Request $request, Response $response, array $args): Response
    {
        try {
            $reviewId = intval($args['id']);
            $userId = $request->getAttribute('user_id');

            $stmt = $this->db->prepare("DELETE FROM product_reviews WHERE id = :id AND user_id = :user_id");
            $stmt->execute(['id' => $reviewId, 'user_id' => $userId]);

            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode(['error' => 'Review not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode(['message' => 'Review deleted successfully']));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * [ADMIN] Listar todas las reseñas
     */
    public function getAllReviews(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $status = $params['status'] ?? null;

            $sql = "
                SELECT
                    r.*,
                    u.name as user_name,
                    p.name as product_name
                FROM product_reviews r
                JOIN users u ON r.user_id = u.id
                JOIN products p ON r.product_id = p.id
            ";

            if ($status) {
                $sql .= " WHERE r.status = :status";
            }

            $sql .= " ORDER BY r.created_at DESC";

            $stmt = $this->db->prepare($sql);
            if ($status) {
                $stmt->execute(['status' => $status]);
            } else {
                $stmt->execute();
            }

            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode(['reviews' => $reviews]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * [ADMIN] Aprobar/Rechazar reseña
     */
    public function moderateReview(Request $request, Response $response, array $args): Response
    {
        try {
            $reviewId = intval($args['id']);
            $data = $request->getParsedBody();

            if (!isset($data['status']) || !in_array($data['status'], ['approved', 'rejected'])) {
                $response->getBody()->write(json_encode(['error' => 'Status must be approved or rejected']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $stmt = $this->db->prepare("UPDATE product_reviews SET status = :status WHERE id = :id");
            $stmt->execute(['status' => $data['status'], 'id' => $reviewId]);

            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode(['error' => 'Review not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode(['message' => 'Review moderated successfully']));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
