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

        $stmt = $this->db->prepare("
            SELECT
                id, name, email, phone, birth_date, gender,
                document_type, document_number, bio, avatar,
                newsletter_subscribed, email_verified,
                role, status, created_at, updated_at,
                last_login_at
            FROM users WHERE id = ?
        ");
        $stmt->execute([$user->user_id]);
        $userData = $stmt->fetch();

        if (!$userData) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Calcular edad si hay fecha de nacimiento
        if ($userData['birth_date']) {
            $birthDate = new \DateTime($userData['birth_date']);
            $today = new \DateTime();
            $userData['age'] = $today->diff($birthDate)->y;
        }

        // Obtener estadísticas del usuario
        $statsStmt = $this->db->prepare("
            SELECT COUNT(*) as total_orders
            FROM orders
            WHERE customer_id = ?
        ");
        $statsStmt->execute([$user->user_id]);
        $stats = $statsStmt->fetch();
        $userData['stats'] = $stats;

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
            $allowedFields = [
                'name', 'email', 'phone', 'birth_date', 'gender',
                'document_type', 'document_number', 'bio', 'newsletter_subscribed'
            ];

            $updateFields = [];
            $params = [];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $value = $data[$field];

                    // Permitir valores vacíos para algunos campos opcionales
                    if (in_array($field, ['phone', 'birth_date', 'bio', 'document_type', 'document_number'])) {
                        if ($value === '' || $value === null) {
                            $updateFields[] = "{$field} = NULL";
                            continue;
                        }
                    }

                    // Validar que no esté vacío para campos requeridos
                    if (in_array($field, ['name', 'email']) && empty(trim($value))) {
                        continue;
                    }

                    // Validaciones específicas
                    if ($field === 'email') {
                        $value = trim($value);
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
                        $value = trim($value);
                        if (strlen($value) < 2) {
                            $response->getBody()->write(json_encode([
                                'success' => false,
                                'error' => 'Name must be at least 2 characters long'
                            ]));
                            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                        }
                    }

                    if ($field === 'birth_date') {
                        // Validar formato de fecha
                        $date = \DateTime::createFromFormat('Y-m-d', $value);
                        if (!$date || $date->format('Y-m-d') !== $value) {
                            $response->getBody()->write(json_encode([
                                'success' => false,
                                'error' => 'Invalid birth date format. Use YYYY-MM-DD'
                            ]));
                            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                        }

                        // Validar que sea mayor de 13 años
                        $today = new \DateTime();
                        $age = $today->diff($date)->y;
                        if ($age < 13) {
                            $response->getBody()->write(json_encode([
                                'success' => false,
                                'error' => 'You must be at least 13 years old'
                            ]));
                            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                        }
                    }

                    if ($field === 'gender') {
                        $validGenders = ['male', 'female', 'other', 'prefer_not_to_say'];
                        if (!in_array($value, $validGenders)) {
                            $response->getBody()->write(json_encode([
                                'success' => false,
                                'error' => 'Invalid gender value'
                            ]));
                            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                        }
                    }

                    if ($field === 'newsletter_subscribed') {
                        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                        if ($value === null) {
                            continue;
                        }
                        $value = $value ? 1 : 0;
                    }

                    $updateFields[] = "{$field} = ?";
                    $params[] = is_string($value) ? trim($value) : $value;
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
            $userStmt = $this->db->prepare("
                SELECT
                    id, name, email, phone, birth_date, gender,
                    document_type, document_number, bio,
                    newsletter_subscribed, email_verified,
                    role, status, created_at, updated_at
                FROM users WHERE id = ?
            ");
            $userStmt->execute([$user->user_id]);
            $updatedUser = $userStmt->fetch();

            // Calcular edad si hay fecha de nacimiento
            if ($updatedUser['birth_date']) {
                $birthDate = new \DateTime($updatedUser['birth_date']);
                $today = new \DateTime();
                $updatedUser['age'] = $today->diff($birthDate)->y;
            }

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
                'error' => 'Internal server error: ' . $e->getMessage()
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

    /**
     * POST /auth/forgot-password
     * Solicitar recuperación de contraseña
     */
    public function forgotPassword(Request $request, Response $response): Response {
        try {
            $data = $this->getRequestData($request);

            if (!isset($data['email'])) {
                $response->getBody()->write(json_encode(['error' => 'Email is required']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Verificar que el email existe
            $stmt = $this->db->prepare("SELECT id, name, email FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$data['email']]);
            $user = $stmt->fetch();

            // Por seguridad, siempre retornamos éxito aunque el email no exista
            if ($user) {
                // Generar token de recuperación
                $token = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Limpiar tokens antiguos del usuario
                $stmt = $this->db->prepare("DELETE FROM password_resets WHERE email = ?");
                $stmt->execute([$data['email']]);

                // Guardar token
                $stmt = $this->db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$data['email'], $token, $expiresAt]);

                // TODO: Enviar email con el token
                // En un entorno de producción, aquí enviarías un email con un link como:
                // https://tudominio.com/reset-password?token=$token

                error_log("Password reset token for {$data['email']}: $token");
            }

            $response->getBody()->write(json_encode([
                'message' => 'If your email is registered, you will receive password reset instructions'
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            error_log("Forgot password error: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Internal server error']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * POST /auth/reset-password
     * Resetear contraseña con token
     */
    public function resetPassword(Request $request, Response $response): Response {
        try {
            $data = $this->getRequestData($request);

            if (!isset($data['token']) || !isset($data['password'])) {
                $response->getBody()->write(json_encode(['error' => 'Token and new password are required']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            if (strlen($data['password']) < 8) {
                $response->getBody()->write(json_encode(['error' => 'Password must be at least 8 characters long']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Verificar token
            $stmt = $this->db->prepare("
                SELECT * FROM password_resets
                WHERE token = ? AND expires_at > NOW()
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$data['token']]);
            $resetRecord = $stmt->fetch();

            if (!$resetRecord) {
                $response->getBody()->write(json_encode(['error' => 'Invalid or expired token']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Actualizar contraseña
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE email = ?");
            $stmt->execute([$hashedPassword, $resetRecord['email']]);

            // Eliminar token usado
            $stmt = $this->db->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->execute([$data['token']]);

            $response->getBody()->write(json_encode(['message' => 'Password reset successfully']));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            error_log("Reset password error: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Internal server error']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * POST /auth/google
     * Login con Google OAuth
     */
    public function googleLogin(Request $request, Response $response): Response {
        try {
            $data = $this->getRequestData($request);

            // Validar que se envió el token de Google
            if (!isset($data['google_token'])) {
                $response->getBody()->write(json_encode(['error' => 'Google token is required']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // TODO: Verificar el token con Google API
            // En producción deberías verificar el token con:
            // https://www.googleapis.com/oauth2/v3/tokeninfo?id_token={token}

            // Por ahora, asumimos que recibes los datos del usuario desde el frontend
            if (!isset($data['google_id']) || !isset($data['email'])) {
                $response->getBody()->write(json_encode(['error' => 'Google ID and email are required']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $googleId = $data['google_id'];
            $email = $data['email'];
            $name = $data['name'] ?? 'Google User';
            $picture = $data['picture'] ?? null;

            // Verificar si el usuario ya existe con este provider
            $stmt = $this->db->prepare("
                SELECT u.* FROM users u
                JOIN oauth_providers op ON u.id = op.user_id
                WHERE op.provider = 'google' AND op.provider_user_id = ?
            ");
            $stmt->execute([$googleId]);
            $user = $stmt->fetch();

            if (!$user) {
                // Verificar si existe un usuario con ese email
                $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user) {
                    // Vincular cuenta existente con Google
                    $stmt = $this->db->prepare("
                        INSERT INTO oauth_providers (user_id, provider, provider_user_id, access_token)
                        VALUES (?, 'google', ?, ?)
                    ");
                    $stmt->execute([$user['id'], $googleId, $data['google_token']]);
                } else {
                    // Crear nuevo usuario
                    $stmt = $this->db->prepare("
                        INSERT INTO users (name, email, password, role, status)
                        VALUES (?, ?, ?, 'customer', 'active')
                    ");
                    // Usar un password aleatorio ya que no lo necesitan
                    $randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                    $stmt->execute([$name, $email, $randomPassword]);

                    $userId = $this->db->lastInsertId();

                    // Crear vínculo con Google
                    $stmt = $this->db->prepare("
                        INSERT INTO oauth_providers (user_id, provider, provider_user_id, access_token)
                        VALUES (?, 'google', ?, ?)
                    ");
                    $stmt->execute([$userId, $googleId, $data['google_token']]);

                    // Obtener usuario creado
                    $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch();
                }
            }

            // Generar JWT
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

        } catch (\Exception $e) {
            error_log("Google login error: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Internal server error']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}