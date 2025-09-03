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
            $response->getBody()->write(json_encode(['error' => 'No authorization header']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $token = str_replace('Bearer ', '', $authHeader);
        $decoded = JWT::decode($token);

        if (!$decoded) {
            $response = new Response();
            $response->getBody()->write(json_encode(['error' => 'Invalid token']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        // Agregar usuario al request
        $request = $request->withAttribute('user', $decoded);
        
        return $handler->handle($request);
    }
}
