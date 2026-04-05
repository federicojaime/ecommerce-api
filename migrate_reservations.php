<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Database;
use Dotenv\Dotenv;

// Cargar variables de entorno
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    // Conectar a la base de datos usando Singleton
    $database = Database::getInstance();
    $db = $database->getConnection();

    echo "=== EJECUTANDO MIGRACION DE TABLAS DE RESERVAS ===\n\n";

    // Leer archivo SQL
    $sqlFile = __DIR__ . '/database/reservations_table.sql';

    if (!file_exists($sqlFile)) {
        die("ERROR: No se encontró el archivo SQL: $sqlFile\n");
    }

    $sql = file_get_contents($sqlFile);

    // Remove comments
    $sql = preg_replace('/--.*?$/m', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

    // Split SQL statements by semicolon, but be smart about it
    // We need to handle strings that contain semicolons (like in INSERT statements)
    $statements = [];
    $buffer = '';
    $inString = false;
    $stringChar = null;

    for ($i = 0; $i < strlen($sql); $i++) {
        $char = $sql[$i];

        // Track if we're inside a string
        if (($char === "'" || $char === '"') && ($i === 0 || $sql[$i-1] !== '\\')) {
            if (!$inString) {
                $inString = true;
                $stringChar = $char;
            } elseif ($char === $stringChar) {
                $inString = false;
                $stringChar = null;
            }
        }

        // If we find a semicolon outside a string, it's a statement separator
        if ($char === ';' && !$inString) {
            $statement = trim($buffer);
            if (!empty($statement)) {
                $statements[] = $statement;
            }
            $buffer = '';
        } else {
            $buffer .= $char;
        }
    }

    // Add last statement if exists
    $statement = trim($buffer);
    if (!empty($statement)) {
        $statements[] = $statement;
    }

    $successCount = 0;
    $errorCount = 0;

    foreach ($statements as $statement) {
        try {
            $db->exec($statement);
            $successCount++;

            // Identificar qué se creó
            if (preg_match('/CREATE TABLE.*?(\w+)/i', $statement, $matches)) {
                echo "✓ Tabla creada: {$matches[1]}\n";
            } elseif (preg_match('/INSERT INTO (\w+)/i', $statement, $matches)) {
                echo "✓ Datos insertados en: {$matches[1]}\n";
            } else {
                echo "✓ Declaración ejecutada exitosamente\n";
            }

        } catch (PDOException $e) {
            $errorCount++;
            // Si es "tabla ya existe", no es un error crítico
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "⚠ Tabla ya existe (omitiendo)\n";
            } else {
                echo "✗ ERROR: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "\n=== RESULTADO ===\n";
    echo "Declaraciones exitosas: $successCount\n";
    echo "Errores: $errorCount\n";

    // Verificar tablas creadas
    echo "\n=== VERIFICANDO TABLAS ===\n";
    $tables = ['reservations', 'reservation_items', 'reservation_logs', 'email_templates'];

    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "✓ Tabla '$table' existe\n";

            // Contar registros
            $count = $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "  → Registros: $count\n";
        } else {
            echo "✗ Tabla '$table' NO existe\n";
        }
    }

    echo "\n=== MIGRACION COMPLETADA ===\n";

} catch (Exception $e) {
    echo "ERROR FATAL: " . $e->getMessage() . "\n";
    exit(1);
}
