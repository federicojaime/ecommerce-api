<?php
namespace App\Middleware;

use App\Utils\JWT;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;

class AuthMiddleware {
    public function __invoke(Request $request, RequestHandler $handler): Response {
        $authHeader = $request->getHeaderLine('Authorization');
        
        if (!$authHeader) {
            $response = new Response();
            $response->getBody()->write(json_encode(['message' => 'Order deleted successfully']));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $this->db->rollback();
            $response->getBody()->write(json_encode(['error' => 'Failed to delete order']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    private function generateOrderNumber() {
        $prefix = 'ORD';
        $timestamp = date('Ymd');
        
        // Obtener el último número de orden del día
        $stmt = $this->db->prepare("SELECT order_number FROM orders WHERE order_number LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt->execute(["{$prefix}{$timestamp}%"]);
        $lastOrder = $stmt->fetch();
        
        if ($lastOrder) {
            $lastNumber = (int)substr($lastOrder['order_number'], -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }
        
        return $prefix . $timestamp . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}