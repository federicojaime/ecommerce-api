<?php
namespace App\Controllers;

use App\Utils\JWT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController {
    private $db;

    public function __construct($database) {
        $this->db = $database->getConnection();
    }

    public function login(Request $request, Response $response): Response {
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (!isset($data['email']) || !isset($data['password'])) {
            $response->getBody()->write(json_encode(['error' => 'Email and password required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$data['email']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($data['password'], $user['password'])) {
            $response->getBody()->write(json_encode(['error' => 'Invalid credentials']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $payload = [
            'user_id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role']
        ];

        $token = JWT::encode($payload);

        $result = [
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role']
            ]
        ];

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function register(Request $request, Response $response): Response {
        $data = json_decode($request->getBody()->getContents(), true);
        
        $required = ['name', 'email', 'password'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                $response->getBody()->write(json_encode(['error' => $field . ' is required']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
        }

        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'Email already exists']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        $role = $data['role'] ?? 'customer';

        $stmt = $this->db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        
        if ($stmt->execute([$data['name'], $data['email'], $hashedPassword, $role])) {
            $userId = $this->db->lastInsertId();
            
            $response->getBody()->write(json_encode([
                'message' => 'User created successfully',
                'user_id' => $userId
            ]));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode(['error' => 'Failed to create user']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

    public function me(Request $request, Response $response): Response {
        $user = $request->getAttribute('user');
        
        $stmt = $this->db->prepare("SELECT id, name, email, role, status, created_at FROM users WHERE id = ?");
        $stmt->execute([$user->user_id]);
        $userData = $stmt->fetch();

        if (!$userData) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode($userData));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
