<?php
namespace App\Middleware;

use App\Utils\JWT;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;

class AuthMiddleware {
    public function __invoke(Request $request, RequestHandler $handler): Response {
        // Obtener el header de autorización
        $authHeader = $request->getHeaderLine('Authorization');
        
        // También verificar en $_SERVER por si acaso
        if (!$authHeader && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        }
        
        // Log para debugging
        error_log("Auth Header: " . $authHeader);
        error_log("All headers: " . print_r($request->getHeaders(), true));
        
        if (!$authHeader) {
            $response = new Response();
            $response->getBody()->write(json_encode(['error' => 'No authorization header']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        // Extraer el token
        $token = str_replace('Bearer ', '', $authHeader);
        
        // Log del token
        error_log("Token extracted: " . substr($token, 0, 50) . "...");
        
        $decoded = JWT::decode($token);

        if (!$decoded) {
            $response = new Response();
            $response->getBody()->write(json_encode(['error' => 'Invalid token']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $request = $request->withAttribute('user', $decoded);
        
        return $handler->handle($request);
    }
}