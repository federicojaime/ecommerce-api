<?php
use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Crear contenedor DI
$container = new Container();

// Configurar base de datos en el contenedor
$container->set('database', function () {
    return new App\Models\Database();
});

// Registrar controladores en el contenedor
$container->set(App\Controllers\AuthController::class, function ($container) {
    return new App\Controllers\AuthController($container->get('database'));
});

$container->set(App\Controllers\ProductController::class, function ($container) {
    return new App\Controllers\ProductController($container->get('database'));
});

$container->set(App\Controllers\CategoryController::class, function ($container) {
    return new App\Controllers\CategoryController($container->get('database'));
});

$container->set(App\Controllers\UserController::class, function ($container) {
    return new App\Controllers\UserController($container->get('database'));
});

$container->set(App\Controllers\OrderController::class, function ($container) {
    return new App\Controllers\OrderController($container->get('database'));
});

$container->set(App\Controllers\DashboardController::class, function ($container) {
    return new App\Controllers\DashboardController($container->get('database'));
});

AppFactory::setContainer($container);
$app = AppFactory::create();

// Middleware de CORS
$app->add(function (Request $request, $handler): Response {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

// Manejar preflight requests
$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response;
});

// Middleware para parsing del body
$app->addBodyParsingMiddleware();

// Middleware de routing
$app->addRoutingMiddleware();

// Middleware de errores
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

// Cargar rutas
$routes = require __DIR__ . '/../src/Routes/api.php';
$routes($app);

// Ruta de prueba
$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write(json_encode([
        'message' => 'Ecommerce API v1.0',
        'status' => 'running',
        'timestamp' => date('Y-m-d H:i:s')
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();
