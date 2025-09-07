<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UserController
{
    private $db;

    public function __construct($database)
    {
        $this->db = $database->getConnection();
    }

    public function getAll(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();

        $page = max(1, (int)($params['page'] ?? 1));
        $limit = min(100, max(1, (int)($params['limit'] ?? 10)));
        $offset = ($page - 1) * $limit;

        $role = $params['role'] ?? '';
        $status = $params['status'] ?? '';
        $search = $params['search'] ?? '';

        $where = [];
        $bindings = [];

        if ($role) {
            $where[] = "role = ?";
            $bindings[] = $role;
        }

        if ($status) {
            $where[] = "status = ?";
            $bindings[] = $status;
        }

        if ($search) {
            $where[] = "(name LIKE ? OR email LIKE ?)";
            $searchTerm = "%{$search}%";
            $bindings = array_merge($bindings, [$searchTerm, $searchTerm]);
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Contar total
        $countSql = "SELECT COUNT(*) as total FROM users {$whereClause}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($bindings);
        $total = $countStmt->fetch()['total'];

        // Obtener usuarios - CORREGIDO: LIMIT y OFFSET directos
        $sql = "SELECT id, name, email, role, status, created_at, updated_at FROM users 
                {$whereClause} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        $users = $stmt->fetchAll();

        $result = [
            'data' => $users,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ];

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getOne(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];

        $stmt = $this->db->prepare("SELECT id, name, email, role, status, created_at, updated_at FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode($user));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);

        $required = ['name', 'email', 'password'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $response->getBody()->write(json_encode(['error' => $field . ' is required']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
        }

        // Verificar email único
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'Email already exists']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)";
        $params = [
            $data['name'],
            $data['email'],
            $hashedPassword,
            $data['role'] ?? 'customer',
            $data['status'] ?? 'active'
        ];

        $stmt = $this->db->prepare($sql);

        if ($stmt->execute($params)) {
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

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $data = json_decode($request->getBody()->getContents(), true);

        // Verificar que el usuario existe
        $checkStmt = $this->db->prepare("SELECT id FROM users WHERE id = ?");
        $checkStmt->execute([$id]);
        if (!$checkStmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Verificar email único (si se está actualizando)
        if (isset($data['email'])) {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$data['email'], $id]);
            if ($stmt->fetch()) {
                $response->getBody()->write(json_encode(['error' => 'Email already exists']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
        }

        $updateFields = [];
        $params = [];

        $allowedFields = ['name', 'email', 'role', 'status'];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateFields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        // Actualizar password si se proporciona
        if (isset($data['password']) && !empty($data['password'])) {
            $updateFields[] = "password = ?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if ($updateFields) {
            $updateFields[] = "updated_at = CURRENT_TIMESTAMP";
            $params[] = $id;

            $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }

        $response->getBody()->write(json_encode(['message' => 'User updated successfully']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];

        // Verificar que el usuario existe
        $checkStmt = $this->db->prepare("SELECT id FROM users WHERE id = ?");
        $checkStmt->execute([$id]);
        if (!$checkStmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // No permitir eliminar el último admin
        $adminStmt = $this->db->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND status = 'active'");
        $adminStmt->execute();
        $adminCount = $adminStmt->fetch()['count'];

        $userStmt = $this->db->prepare("SELECT role FROM users WHERE id = ?");
        $userStmt->execute([$id]);
        $user = $userStmt->fetch();

        if ($user['role'] === 'admin' && $adminCount <= 1) {
            $response->getBody()->write(json_encode(['error' => 'Cannot delete the last admin user']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);

        $response->getBody()->write(json_encode(['message' => 'User deleted successfully']));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
