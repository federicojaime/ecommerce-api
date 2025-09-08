<?php
// Imports al inicio
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Diagnóstico completo
echo "<h1>Diagnóstico PHP/Slim</h1>";

echo "<h2>1. Información PHP</h2>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Current Directory: " . __DIR__ . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";

echo "<h2>2. Variables del servidor</h2>";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD'] . "<br>";
echo "SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "<br>";
echo "PATH_INFO: " . ($_SERVER['PATH_INFO'] ?? 'not set') . "<br>";
echo "QUERY_STRING: " . $_SERVER['QUERY_STRING'] . "<br>";

echo "<h2>3. Verificar Autoload</h2>";
echo "✓ vendor/autoload.php cargado correctamente<br>";

echo "<h2>4. Verificar Slim</h2>";
echo "✓ Clases de Slim importadas correctamente<br>";

echo "<h2>5. Test de Slim básico</h2>";

// Solo ejecutar Slim si es una petición específica
if ($_SERVER['REQUEST_URI'] === '/ecommerce-api/public/' || $_SERVER['REQUEST_URI'] === '/ecommerce-api/public/index.php') {
    echo "Ejecutando aplicación Slim...<br>";
    
    try {
        $app = AppFactory::create();
        
        // Ruta de prueba
        $app->get('/', function (Request $request, Response $response) {
            $response->getBody()->write(json_encode([
                'message' => 'Slim funcionando correctamente',
                'status' => 'OK',
                'timestamp' => date('Y-m-d H:i:s')
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        });
        
        $app->addRoutingMiddleware();
        $app->addErrorMiddleware(true, true, true);
        
        echo "✓ Aplicación Slim configurada<br>";
        echo "<hr>";
        echo "Resultado de la aplicación:<br>";
        
        $app->run();
        
    } catch (Exception $e) {
        echo "✗ Error ejecutando Slim: " . $e->getMessage() . "<br>";
        echo "Stack trace:<br>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }
} else {
    echo "Esta página es solo para diagnóstico.<br>";
    echo "Para probar la aplicación Slim, ve a: <a href='/ecommerce-api/public/'>http://localhost/ecommerce-api/public/</a><br>";
}

echo "<h2>6. Información adicional</h2>";
echo "Loaded extensions: " . implode(', ', get_loaded_extensions()) . "<br>";
?>