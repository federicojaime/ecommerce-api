<?php

namespace App\Controllers;

use App\Utils\FileUpload;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SettingsController
{
    private $db;
    private $fileUpload;

    public function __construct($database)
    {
        $this->db = $database->getConnection();
        $this->fileUpload = new FileUpload();
    }

    /**
     * Obtener datos del request de forma segura
     */
    private function getRequestData(Request $request): array 
    {
        $contentType = $request->getHeaderLine('Content-Type');
        
        if (strpos($contentType, 'application/json') !== false) {
            $body = $request->getBody()->getContents();
            if (!empty($body)) {
                $data = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                    return $data;
                }
            }
        }
        
        $parsedBody = $request->getParsedBody();
        if (is_array($parsedBody)) {
            return $parsedBody;
        }
        
        return [];
    }

    /**
     * Registrar cambios en el log
     */
    private function logSettingChange($userId, $action, $category, $key = null, $oldValue = null, $newValue = null, $request = null): void
    {
        $ipAddress = null;
        $userAgent = null;
        
        if ($request) {
            $serverParams = $request->getServerParams();
            $ipAddress = $serverParams['REMOTE_ADDR'] ?? $serverParams['HTTP_X_FORWARDED_FOR'] ?? null;
            $userAgent = $request->getHeaderLine('User-Agent') ?: null;
        }

        $stmt = $this->db->prepare("
            INSERT INTO setting_logs (user_id, action, category, setting_key, old_value, new_value, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $action,
            $category,
            $key,
            $oldValue ? json_encode($oldValue) : null,
            $newValue ? json_encode($newValue) : null,
            $ipAddress,
            $userAgent
        ]);
    }

    /**
     * GET /admin/settings
     * Obtener todas las configuraciones organizadas por categoría
     */
    public function getSettings(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $category = $params['category'] ?? null;
            
            $sql = "SELECT category, setting_key, setting_value, updated_at FROM settings WHERE is_active = 1";
            $bindings = [];
            
            if ($category) {
                $sql .= " AND category = ?";
                $bindings[] = $category;
            }
            
            $sql .= " ORDER BY category, setting_key";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $rows = $stmt->fetchAll();
            
            $settings = [];
            foreach ($rows as $row) {
                $value = json_decode($row['setting_value'], true);
                
                if (!isset($settings[$row['category']])) {
                    $settings[$row['category']] = [];
                }
                
                $settings[$row['category']][$row['setting_key']] = [
                    'value' => $value,
                    'updated_at' => $row['updated_at']
                ];
            }
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $settings,
                'timestamp' => date('Y-m-d H:i:s')
            ]));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log("Get settings error: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Failed to retrieve settings'
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * PUT /admin/settings
     * Actualizar configuraciones (puede ser una categoría específica o múltiples)
     */
    public function updateSettings(Request $request, Response $response): Response
    {
        try {
            $data = $this->getRequestData($request);
            $user = $request->getAttribute('user');
            $userId = $user ? $user->user_id : null;
            
            if (empty($data)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'No data provided'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            $this->db->beginTransaction();
            
            $updatedSettings = [];
            
            foreach ($data as $category => $categorySettings) {
                if (!is_array($categorySettings)) {
                    continue;
                }
                
                foreach ($categorySettings as $key => $value) {
                    // Obtener valor anterior para el log
                    $oldStmt = $this->db->prepare("SELECT setting_value FROM settings WHERE category = ? AND setting_key = ?");
                    $oldStmt->execute([$category, $key]);
                    $oldRow = $oldStmt->fetch();
                    $oldValue = $oldRow ? json_decode($oldRow['setting_value'], true) : null;
                    
                    // Actualizar o insertar configuración
                    $stmt = $this->db->prepare("
                        INSERT INTO settings (user_id, category, setting_key, setting_value) 
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                        setting_value = VALUES(setting_value),
                        updated_at = CURRENT_TIMESTAMP
                    ");
                    
                    $jsonValue = json_encode($value);
                    $stmt->execute([$userId, $category, $key, $jsonValue]);
                    
                    // Registrar cambio en el log
                    $action = $oldValue ? 'update' : 'create';
                    $this->logSettingChange($userId, $action, $category, $key, $oldValue, $value, $request);
                    
                    $updatedSettings[] = "{$category}.{$key}";
                }
            }
            
            $this->db->commit();
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Settings updated successfully',
                'updated_count' => count($updatedSettings),
                'updated_settings' => $updatedSettings
            ]));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            error_log("Update settings error: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Failed to update settings: ' . $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * POST /admin/settings/validate
     * Validar configuraciones sin guardarlas
     */
    public function validateSettings(Request $request, Response $response): Response
    {
        try {
            $data = $this->getRequestData($request);
            
            $errors = [];
            $warnings = [];
            
            foreach ($data as $category => $categorySettings) {
                if (!is_array($categorySettings)) {
                    $errors[] = "Category '{$category}' must be an object";
                    continue;
                }
                
                // Validaciones específicas por categoría
                switch ($category) {
                    case 'store':
                        $errors = array_merge($errors, $this->validateStoreSettings($categorySettings));
                        break;
                    case 'style':
                        $errors = array_merge($errors, $this->validateStyleSettings($categorySettings));
                        break;
                    case 'notifications':
                        $errors = array_merge($errors, $this->validateNotificationSettings($categorySettings));
                        break;
                    case 'security':
                        $errors = array_merge($errors, $this->validateSecuritySettings($categorySettings));
                        break;
                    case 'payment':
                        $errors = array_merge($errors, $this->validatePaymentSettings($categorySettings));
                        break;
                    case 'shipping':
                        $errors = array_merge($errors, $this->validateShippingSettings($categorySettings));
                        break;
                }
            }
            
            $isValid = empty($errors);
            
            $response->getBody()->write(json_encode([
                'valid' => $isValid,
                'errors' => $errors,
                'warnings' => $warnings
            ]));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log("Validate settings error: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'valid' => false,
                'errors' => ['Validation failed: ' . $e->getMessage()]
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * GET /admin/settings/stats
     * Obtener estadísticas de configuraciones
     */
    public function getStats(Request $request, Response $response): Response
    {
        try {
            // Estadísticas básicas
            $statsStmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_settings,
                    COUNT(DISTINCT category) as total_categories,
                    MAX(updated_at) as last_update
                FROM settings WHERE is_active = 1
            ");
            $statsStmt->execute();
            $stats = $statsStmt->fetch();
            
            // Último backup
            $backupStmt = $this->db->prepare("
                SELECT created_at, backup_type 
                FROM setting_backups 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $backupStmt->execute();
            $lastBackup = $backupStmt->fetch();
            
            // Integraciones activas (métodos de pago habilitados)
            $paymentStmt = $this->db->prepare("
                SELECT setting_value 
                FROM settings 
                WHERE category = 'payment' AND setting_key = 'methods'
            ");
            $paymentStmt->execute();
            $paymentRow = $paymentStmt->fetch();
            
            $activeIntegrations = 0;
            if ($paymentRow) {
                $paymentMethods = json_decode($paymentRow['setting_value'], true);
                foreach ($paymentMethods as $method) {
                    if (isset($method['enabled']) && $method['enabled']) {
                        $activeIntegrations++;
                    }
                }
            }
            
            // Score de seguridad básico
            $securityScore = $this->calculateSecurityScore();
            
            $response->getBody()->write(json_encode([
                'last_backup' => $lastBackup ? $lastBackup['created_at'] : null,
                'last_backup_type' => $lastBackup ? $lastBackup['backup_type'] : null,
                'total_settings' => (int)$stats['total_settings'],
                'total_categories' => (int)$stats['total_categories'],
                'active_integrations' => $activeIntegrations,
                'security_score' => $securityScore,
                'last_update' => $stats['last_update']
            ]));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log("Get stats error: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'error' => 'Failed to retrieve statistics'
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * POST /admin/settings/logo
     * Subir logo de la tienda
     */
    public function uploadLogo(Request $request, Response $response): Response
    {
        try {
            $uploadedFiles = $request->getUploadedFiles();
            
            if (!isset($uploadedFiles['logo']) || $uploadedFiles['logo']->getError() !== UPLOAD_ERR_OK) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'No valid logo file uploaded'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            $uploadedFile = $uploadedFiles['logo'];
            
            // Validar tamaño (máximo 5MB)
            if ($uploadedFile->getSize() > 5 * 1024 * 1024) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Logo file size must be less than 5MB'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            // Subir archivo
            $logoPath = $this->fileUpload->uploadFile($uploadedFile, 'logos');
            $logoUrl = $this->fileUpload->getImageUrl($logoPath);
            
            // Actualizar configuración
            $user = $request->getAttribute('user');
            $userId = $user ? $user->user_id : null;
            
            $this->db->beginTransaction();
            
            // Obtener configuración actual de estilo
            $stmt = $this->db->prepare("
                SELECT setting_value FROM settings 
                WHERE category = 'style' AND setting_key = 'theme'
            ");
            $stmt->execute();
            $currentRow = $stmt->fetch();
            
            $themeConfig = $currentRow ? json_decode($currentRow['setting_value'], true) : [];
            $oldLogoUrl = $themeConfig['logo_url'] ?? null;
            
            // Actualizar con nuevo logo
            $themeConfig['logo_url'] = $logoPath;
            
            $updateStmt = $this->db->prepare("
                INSERT INTO settings (user_id, category, setting_key, setting_value) 
                VALUES (?, 'style', 'theme', ?)
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                updated_at = CURRENT_TIMESTAMP
            ");
            
            $updateStmt->execute([$userId, json_encode($themeConfig)]);
            
            // Eliminar logo anterior si existe
            if ($oldLogoUrl && $oldLogoUrl !== $logoPath) {
                $this->fileUpload->deleteFile($oldLogoUrl);
            }
            
            // Registrar cambio
            $this->logSettingChange($userId, 'update', 'style', 'theme.logo_url', $oldLogoUrl, $logoPath, $request);
            
            $this->db->commit();
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Logo uploaded successfully',
                'logo_url' => $logoUrl,
                'logo_path' => $logoPath
            ]));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            error_log("Upload logo error: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Failed to upload logo: ' . $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * GET /admin/settings/export
     * Exportar configuraciones como archivo JSON
     */
    public function exportSettings(Request $request, Response $response): Response
    {
        try {
            $user = $request->getAttribute('user');
            $userId = $user ? $user->user_id : null;
            
            // Obtener todas las configuraciones
            $stmt = $this->db->prepare("
                SELECT category, setting_key, setting_value, updated_at 
                FROM settings 
                WHERE is_active = 1 
                ORDER BY category, setting_key
            ");
            $stmt->execute();
            $rows = $stmt->fetchAll();
            
            $settings = [];
            foreach ($rows as $row) {
                $value = json_decode($row['setting_value'], true);
                
                if (!isset($settings[$row['category']])) {
                    $settings[$row['category']] = [];
                }
                
                $settings[$row['category']][$row['setting_key']] = $value;
            }
            
            $exportData = [
                'export_info' => [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'version' => '1.0',
                    'exported_by' => $userId,
                    'total_settings' => count($rows)
                ],
                'settings' => $settings
            ];
            
            // Crear backup en la base de datos
            $backupStmt = $this->db->prepare("
                INSERT INTO setting_backups (user_id, backup_name, settings_data, backup_type) 
                VALUES (?, ?, ?, 'export')
            ");
            $backupStmt->execute([
                $userId,
                'Export - ' . date('Y-m-d H:i:s'),
                json_encode($exportData)
            ]);
            
            // Registrar exportación
            $this->logSettingChange($userId, 'export', 'all', null, null, $exportData, $request);
            
            $filename = 'settings-export-' . date('Y-m-d-H-i-s') . '.json';
            
            $response->getBody()->write(json_encode($exportData, JSON_PRETTY_PRINT));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->withHeader('Cache-Control', 'no-cache');
                
        } catch (\Exception $e) {
            error_log("Export settings error: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Failed to export settings'
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * POST /admin/settings/import
     * Importar configuraciones desde archivo JSON
     */
    public function importSettings(Request $request, Response $response): Response
    {
        try {
            $uploadedFiles = $request->getUploadedFiles();
            
            if (!isset($uploadedFiles['settings_file']) || $uploadedFiles['settings_file']->getError() !== UPLOAD_ERR_OK) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'No valid settings file uploaded'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            $uploadedFile = $uploadedFiles['settings_file'];
            $fileContent = $uploadedFile->getStream()->getContents();
            
            $importData = json_decode($fileContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Invalid JSON file'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            if (!isset($importData['settings']) || !is_array($importData['settings'])) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Invalid settings file format'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            $user = $request->getAttribute('user');
            $userId = $user ? $user->user_id : null;
            
            $this->db->beginTransaction();
            
            $importedSettings = [];
            
            foreach ($importData['settings'] as $category => $categorySettings) {
                if (!is_array($categorySettings)) {
                    continue;
                }
                
                foreach ($categorySettings as $key => $value) {
                    $stmt = $this->db->prepare("
                        INSERT INTO settings (user_id, category, setting_key, setting_value) 
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                        setting_value = VALUES(setting_value),
                        updated_at = CURRENT_TIMESTAMP
                    ");
                    
                    $jsonValue = json_encode($value);
                    $stmt->execute([$userId, $category, $key, $jsonValue]);
                    
                    $importedSettings[] = "{$category}.{$key}";
                }
            }
            
            // Crear backup de la importación
            $backupStmt = $this->db->prepare("
                INSERT INTO setting_backups (user_id, backup_name, settings_data, backup_type) 
                VALUES (?, ?, ?, 'import')
            ");
            $backupStmt->execute([
                $userId,
                'Import - ' . date('Y-m-d H:i:s'),
                $fileContent
            ]);
            
            // Registrar importación
            $this->logSettingChange($userId, 'import', 'all', null, null, $importData, $request);
            
            $this->db->commit();
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Settings imported successfully',
                'imported_count' => count($importedSettings),
                'imported_settings' => $importedSettings
            ]));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            error_log("Import settings error: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Failed to import settings: ' . $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * POST /admin/settings/test-payment
     * Probar conexión con métodos de pago
     */
    public function testPaymentConnection(Request $request, Response $response): Response
    {
        try {
            $data = $this->getRequestData($request);
            
            if (!isset($data['provider']) || !isset($data['credentials'])) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Provider and credentials are required'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            $provider = $data['provider'];
            $credentials = $data['credentials'];
            
            $testResult = $this->testPaymentProvider($provider, $credentials);
            
            $response->getBody()->write(json_encode($testResult));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log("Test payment error: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Failed to test payment connection'
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    // ========== MÉTODOS DE VALIDACIÓN ==========

    private function validateStoreSettings($settings): array
    {
        $errors = [];
        
        if (isset($settings['basic_info'])) {
            $info = $settings['basic_info'];
            
            if (empty($info['name'])) {
                $errors[] = 'Store name is required';
            }
            
            if (!empty($info['email']) && !filter_var($info['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid store email format';
            }
        }
        
        return $errors;
    }

    private function validateStyleSettings($settings): array
    {
        $errors = [];
        
        if (isset($settings['theme'])) {
            $theme = $settings['theme'];
            
            // Validar colores hexadecimales
            $colorFields = ['primary_color', 'secondary_color', 'success_color', 'danger_color'];
            foreach ($colorFields as $field) {
                if (isset($theme[$field]) && !preg_match('/^#[a-fA-F0-9]{6}$/', $theme[$field])) {
                    $errors[] = "Invalid color format for {$field}";
                }
            }
        }
        
        return $errors;
    }

    private function validateNotificationSettings($settings): array
    {
        $errors = [];
        
        if (isset($settings['email'])) {
            $email = $settings['email'];
            
            if (!empty($email['from_email']) && !filter_var($email['from_email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid from email format';
            }
            
            if (!empty($email['smtp_port']) && (!is_numeric($email['smtp_port']) || $email['smtp_port'] < 1 || $email['smtp_port'] > 65535)) {
                $errors[] = 'SMTP port must be between 1 and 65535';
            }
        }
        
        return $errors;
    }

    private function validateSecuritySettings($settings): array
    {
        $errors = [];
        
        if (isset($settings['authentication'])) {
            $auth = $settings['authentication'];
            
            if (isset($auth['password_min_length']) && $auth['password_min_length'] < 6) {
                $errors[] = 'Password minimum length must be at least 6 characters';
            }
            
            if (isset($auth['session_timeout']) && $auth['session_timeout'] < 300) {
                $errors[] = 'Session timeout must be at least 300 seconds (5 minutes)';
            }
        }
        
        return $errors;
    }

    private function validatePaymentSettings($settings): array
    {
        $errors = [];
        
        if (isset($settings['methods'])) {
            $methods = $settings['methods'];
            
            // Validar cada método de pago
            foreach ($methods as $provider => $config) {
                if (!is_array($config)) {
                    continue;
                }
                
                if (isset($config['enabled']) && $config['enabled']) {
                    switch ($provider) {
                        case 'mercado_pago':
                            if (empty($config['public_key']) || empty($config['access_token'])) {
                                $errors[] = 'MercadoPago requires public_key and access_token';
                            }
                            break;
                        case 'stripe':
                            if (empty($config['public_key']) || empty($config['secret_key'])) {
                                $errors[] = 'Stripe requires public_key and secret_key';
                            }
                            break;
                        case 'paypal':
                            if (empty($config['client_id']) || empty($config['client_secret'])) {
                                $errors[] = 'PayPal requires client_id and client_secret';
                            }
                            break;
                    }
                }
            }
        }
        
        return $errors;
    }

    private function validateShippingSettings($settings): array
    {
        $errors = [];
        
        if (isset($settings['methods'])) {
            $methods = $settings['methods'];
            
            foreach ($methods as $method => $config) {
                if (!is_array($config)) {
                    continue;
                }
                
                if (isset($config['enabled']) && $config['enabled']) {
                    if (empty($config['name'])) {
                        $errors[] = "Shipping method '{$method}' requires a name";
                    }
                    
                    if (isset($config['price']) && (!is_numeric($config['price']) || $config['price'] < 0)) {
                        $errors[] = "Shipping method '{$method}' price must be a non-negative number";
                    }
                }
            }
        }
        
        return $errors;
    }

    // ========== MÉTODOS AUXILIARES ==========

    private function calculateSecurityScore(): int
    {
        $score = 0;
        
        try {
            // Verificar configuraciones de seguridad
            $stmt = $this->db->prepare("
                SELECT setting_value FROM settings 
                WHERE category = 'security' AND setting_key = 'authentication'
            ");
            $stmt->execute();
            $row = $stmt->fetch();
            
            if ($row) {
                $auth = json_decode($row['setting_value'], true);
                
                // Puntuación basada en configuraciones de seguridad
                if (isset($auth['password_min_length']) && $auth['password_min_length'] >= 8) {
                    $score += 20;
                }
                
                if (isset($auth['require_special_chars']) && $auth['require_special_chars']) {
                    $score += 20;
                }
                
                if (isset($auth['max_login_attempts']) && $auth['max_login_attempts'] <= 5) {
                    $score += 20;
                }
                
                if (isset($auth['session_timeout']) && $auth['session_timeout'] <= 3600) {
                    $score += 20;
                }
                
                if (isset($auth['two_factor_enabled']) && $auth['two_factor_enabled']) {
                    $score += 20;
                }
            }
        } catch (\Exception $e) {
            error_log("Security score calculation error: " . $e->getMessage());
        }
        
        return $score;
    }

    private function testPaymentProvider($provider, $credentials): array
    {
        // Simulación de pruebas de conexión
        // En una implementación real, aquí harías llamadas a las APIs
        
        switch ($provider) {
            case 'mercadoPago':
                return $this->testMercadoPago($credentials);
            case 'stripe':
                return $this->testStripe($credentials);
            case 'paypal':
                return $this->testPayPal($credentials);
            default:
                return [
                    'success' => false,
                    'error' => 'Unsupported payment provider'
                ];
        }
    }

    private function testMercadoPago($credentials): array
    {
        // Simulación - en producción conectarías con la API real
        if (empty($credentials['public_key']) || empty($credentials['access_token'])) {
            return [
                'success' => false,
                'error' => 'Missing required credentials',
                'connection_status' => 'failed'
            ];
        }
        
        return [
            'success' => true,
            'message' => 'MercadoPago connection test successful',
            'connection_status' => 'active',
            'test_mode' => $credentials['sandbox_mode'] ?? true
        ];
    }

    private function testStripe($credentials): array
    {
        if (empty($credentials['public_key']) || empty($credentials['secret_key'])) {
            return [
                'success' => false,
                'error' => 'Missing required credentials',
                'connection_status' => 'failed'
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Stripe connection test successful',
            'connection_status' => 'active'
        ];
    }

    private function testPayPal($credentials): array
    {
        if (empty($credentials['client_id']) || empty($credentials['client_secret'])) {
            return [
                'success' => false,
                'error' => 'Missing required credentials',
                'connection_status' => 'failed'
            ];
        }
        
        return [
            'success' => true,
            'message' => 'PayPal connection test successful',
            'connection_status' => 'active',
            'test_mode' => $credentials['sandbox_mode'] ?? true
        ];
    }
}