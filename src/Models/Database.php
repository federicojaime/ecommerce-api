<?php
namespace App\Models;

use PDO;
use PDOException;

class Database
{
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    private $conn;

    // Singleton instance
    private static $instance = null;

    // Connection counter para monitoreo
    private static $connectionCount = 0;

    private function __construct()
    {
        $this->host = $_ENV['DB_HOST'] ?? 'localhost';
        $this->db_name = $_ENV['DB_NAME'] ?? 'ecommerce_db';
        $this->username = $_ENV['DB_USER'] ?? 'root';
        $this->password = $_ENV['DB_PASS'] ?? '';
        $this->port = $_ENV['DB_PORT'] ?? '3306';
    }

    // Singleton pattern - solo una instancia de Database
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Prevenir clonación
    private function __clone() {}

    // Prevenir deserialización
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }

    public function getConnection()
    {
        if ($this->conn !== null) {
            // Verificar que la conexión sigue viva
            try {
                $this->conn->query('SELECT 1');
                return $this->conn;
            } catch (PDOException $e) {
                // Conexión muerta, reconectar
                error_log("Dead connection detected, reconnecting...");
                $this->conn = null;
            }
        }

        try {
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=utf8mb4";

            // Opciones optimizadas para reducir conexiones
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                // IMPORTANTE: Usar conexiones persistentes para reutilizar
                PDO::ATTR_PERSISTENT => true,
                // Reducir timeout para liberar conexiones muertas rápido
                PDO::ATTR_TIMEOUT => 5,
                // Comprimir datos entre PHP y MySQL
                PDO::MYSQL_ATTR_COMPRESS => true,
                // Usar buffered queries
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);

            // Incrementar contador
            self::$connectionCount++;

            // Log de conexión (solo cada 10 conexiones para no saturar logs)
            if (self::$connectionCount % 10 === 0) {
                error_log("DB: Connection #" . self::$connectionCount . " created (persistent mode)");
            }

        } catch (PDOException $exception) {
            error_log("Database connection failed: " . $exception->getMessage());
            error_log("Host: " . $this->host . ", DB: " . $this->db_name . ", User: " . $this->username);

            // Lanzar excepción en lugar de solo imprimir
            throw new \Exception("Database connection failed: " . $exception->getMessage());
        }

        return $this->conn;
    }

    // Método para cerrar la conexión manualmente si es necesario
    public function closeConnection()
    {
        $this->conn = null;
    }

    // Obtener estadísticas de conexión
    public static function getConnectionCount()
    {
        return self::$connectionCount;
    }
}