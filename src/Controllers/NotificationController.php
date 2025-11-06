<?php

namespace App\Controllers;

use App\Models\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

class NotificationController
{
    private $db;

    public function __construct(Database $database)
    {
        $this->db = $database->getConnection();
    }

    /**
     * Obtener notificaciones del usuario
     */
    public function getAll(Request $request, Response $response): Response
    {
        try {
            $userId = $request->getAttribute('user_id');
            $params = $request->getQueryParams();
            $unreadOnly = isset($params['unread']) && $params['unread'] === 'true';

            $sql = "SELECT * FROM notifications WHERE user_id = :user_id";
            if ($unreadOnly) {
                $sql .= " AND read_at IS NULL";
            }
            $sql .= " ORDER BY created_at DESC LIMIT 50";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Contar no leídas
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = :user_id AND read_at IS NULL");
            $stmt->execute(['user_id' => $userId]);
            $unreadCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            $data = [
                'notifications' => $notifications,
                'unread_count' => intval($unreadCount)
            ];

            $response->getBody()->write(json_encode($data));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Marcar notificación como leída
     */
    public function markAsRead(Request $request, Response $response, array $args): Response
    {
        try {
            $notificationId = intval($args['id']);
            $userId = $request->getAttribute('user_id');

            $stmt = $this->db->prepare("
                UPDATE notifications
                SET read_at = NOW()
                WHERE id = :id AND user_id = :user_id AND read_at IS NULL
            ");
            $stmt->execute(['id' => $notificationId, 'user_id' => $userId]);

            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode(['error' => 'Notification not found or already read']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode(['message' => 'Notification marked as read']));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Marcar todas como leídas
     */
    public function markAllAsRead(Request $request, Response $response): Response
    {
        try {
            $userId = $request->getAttribute('user_id');

            $stmt = $this->db->prepare("
                UPDATE notifications
                SET read_at = NOW()
                WHERE user_id = :user_id AND read_at IS NULL
            ");
            $stmt->execute(['user_id' => $userId]);

            $response->getBody()->write(json_encode([
                'message' => 'All notifications marked as read',
                'count' => $stmt->rowCount()
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Eliminar notificación
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $notificationId = intval($args['id']);
            $userId = $request->getAttribute('user_id');

            $stmt = $this->db->prepare("DELETE FROM notifications WHERE id = :id AND user_id = :user_id");
            $stmt->execute(['id' => $notificationId, 'user_id' => $userId]);

            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode(['error' => 'Notification not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode(['message' => 'Notification deleted successfully']));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
