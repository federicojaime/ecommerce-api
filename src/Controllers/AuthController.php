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

    /**
     * Método helper para obtener datos del request de forma segura
     */
    private function getRequestData(Request $request): array 
    {
        $contentType = $request->getHeaderLine('Content-Type');
        
        // Intentar JSON primero
        if (strpos($contentType, 'application/json') !== false) {
            $body = $request->getBody()->getContents();
            if (!empty($body)) {
                $data = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                    return $data;
                }
            }
        }
        
        // Luego intentar form-data
        $parsedBody = $request->getParsedBody();
        if (is_array($parsedBody)) {
            return $parsedBody;
        }
        
        // Si todo falla, devolver array vacío
        return [];
    }

    public function login(Request $request, Response $response): Response {
        $data = $this->getRequestData($request);
        
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
        $data = $this->getRequestData($request);
        
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

    /**
     * PUT /auth/change-password
     * Cambiar contraseña del usuario autenticado
     */
    public function changePassword(Request $request, Response $response): Response {
        try {
            $data = $this->getRequestData($request);
            $user = $request->getAttribute('user');
            
            // Validar campos requeridos
            if (!isset($data['current_password']) || !isset($data['new_password'])) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Current password and new password are required'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            // Validar longitud mínima de nueva contraseña
            if (strlen($data['new_password']) < 8) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'New password must be at least 8 characters long'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            // Verificar contraseña actual
            $stmt = $this->db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user->user_id]);
            $userData = $stmt->fetch();
            
            if (!$userData || !password_verify($data['current_password'], $userData['password'])) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Current password is incorrect'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            // Verificar que la nueva contraseña sea diferente
            if (password_verify($data['new_password'], $userData['password'])) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'New password must be different from current password'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            // Actualizar contraseña
            $hashedNewPassword = password_hash($data['new_password'], PASSWORD_DEFAULT);
            $updateStmt = $this->db->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $result = $updateStmt->execute([$hashedNewPassword, $user->user_id]);
            
            if (!$result) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Failed to update password'
                ]));
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Password changed successfully'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log("Change password error: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Internal server error'
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * PUT /auth/profile
     * Actualizar perfil del usuario autenticado
     */
    public function updateProfile(Request $request, Response $response): Response {
        try {
            $data = $this->getRequestData($request);
            $user = $request->getAttribute('user');
            
            // Campos permitidos para actualizar
            $allowedFields = ['name', 'email'];
            $updateFields = [];
            $params = [];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field]) && !empty(trim($data[$field]))) {
                    $value = trim($data[$field]);
                    
                    // Validaciones específicas
                    if ($field === 'email') {
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $response->getBody()->write(json_encode([
                                'success' => false,
                                'error' => 'Invalid email format'
                            ]));
                            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                        }
                        
                        // Verificar que el email no esté siendo usado por otro usuario
                        $emailStmt = $this->db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                        $emailStmt->execute([$value, $user->user_id]);
                        if ($emailStmt->fetch()) {
                            $response->getBody()->write(json_encode([
                                'success' => false,
                                'error' => 'Email is already being used by another user'
                            ]));
                            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                        }
                    }
                    
                    if ($field === 'name') {
                        if (strlen($value) < 2) {
                            $response->getBody()->write(json_encode([
                                'success' => false,
                                'error' => 'Name must be at least 2 characters long'
                            ]));
                            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                        }
                    }
                    
                    $updateFields[] = "{$field} = ?";
                    $params[] = $value;
                }
            }
            
            // Agregar campos adicionales si se proporcionan
            if (isset($data['phone']) && !empty(trim($data['phone']))) {
                // Primero verificar si la columna 'phone' existe en la tabla users
                $checkColumnStmt = $this->db->prepare("SHOW COLUMNS FROM users LIKE 'phone'");
                $checkColumnStmt->execute();
                
                if ($checkColumnStmt->fetch()) {
                    $updateFields[] = "phone = ?";
                    $params[] = trim($data['phone']);
                } else {
                    // Si no existe la columna phone, la ignoramos silenciosamente
                    error_log("Phone column does not exist in users table");
                }
            }
            
            if (empty($updateFields)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'No valid fields to update'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            // Realizar actualización
            $updateFields[] = "updated_at = CURRENT_TIMESTAMP";
            $params[] = $user->user_id;
            
            $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);
            
            if (!$result) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Failed to update profile'
                ]));
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }
            
            // Obtener datos actualizados del usuario
            $userStmt = $this->db->prepare("SELECT id, name, email, role, status, created_at, updated_at FROM users WHERE id = ?");
            $userStmt->execute([$user->user_id]);
            $updatedUser = $userStmt->fetch();
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Profile updated successfully',
                'user' => $updatedUser
            ]));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log("Update profile error: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Internal server error'
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * POST /auth/logout (opcional)
     * Cerrar sesión - en un sistema JWT stateless esto es principalmente del lado del cliente
     */
    public function logout(Request $request, Response $response): Response {
        try {
            // En un sistema JWT stateless, el logout se maneja principalmente del lado del cliente
            // eliminando el token. Aquí podríamos registrar el logout en logs si es necesario.
            
            $user = $request->getAttribute('user');
            error_log("User logout: " . ($user ? $user->user_id : 'unknown'));
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Logged out successfully'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log("Logout error: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Logged out successfully'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * GET /auth/validate-token (opcional)
     * Validar si un token sigue siendo válido
     */
    public function validateToken(Request $request, Response $response): Response {
        try {
            $user = $request->getAttribute('user');
            
            // Si llegamos aquí, el token es válido (pasó por el middleware)
            $response->getBody()->write(json_encode([
                'valid' => true,
                'user_id' => $user->user_id,
                'email' => $user->email,
                'role' => $user->role,
                'expires_at' => $user->exp ?? null
            ]));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log("Validate token error: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'valid' => false,
                'error' => 'Invalid token'
            ]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }
    }
}