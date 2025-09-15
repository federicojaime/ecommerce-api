<?php
require_once 'vendor/autoload.php';

// Cargar .env
if (file_exists('.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

echo "Testing database connection...\n";
echo "Host: " . ($_ENV['DB_HOST'] ?? 'NOT SET') . "\n";
echo "Database: " . ($_ENV['DB_NAME'] ?? 'NOT SET') . "\n";
echo "User: " . ($_ENV['DB_USER'] ?? 'NOT SET') . "\n";

try {
    $dsn = "mysql:host=" . $_ENV['DB_HOST'] . ";port=" . $_ENV['DB_PORT'] . ";dbname=" . $_ENV['DB_NAME'] . ";charset=utf8mb4";
    $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS']);
    echo "✅ Connection successful!\n";
    
    // Test query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    echo "Users in database: " . $result['count'] . "\n";
    
} catch (Exception $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
}