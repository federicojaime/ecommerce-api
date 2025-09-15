<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use App\Models\Database;
use App\Controllers\AuthController;
use App\Controllers\ProductController;
use App\Controllers\CategoryController;
use App\Controllers\UserController;
use App\Controllers\OrderController;
use App\Controllers\DashboardController;
use App\Controllers\SettingsController;
use App\Middleware\AuthMiddleware;
use Slim\Routing\RouteCollectorProxy;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Cargar variables de entorno si existe el archivo
if (file_exists(__DIR__ . '/../.env')) {
    try {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->load();
    } catch (Exception $e) {
        // Si falla dotenv, usar valores por defecto
    }
}

// Configurar headers CORS
if (!headers_sent()) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Crear aplicación Slim
$app = AppFactory::create();

// Configurar base path para XAMPP
$app->setBasePath('/ecommerce-api/public');

// IMPORTANTE: Agregar middleware de routing ANTES que el de errores
$app->addRoutingMiddleware();

// Configurar middleware de errores
$app->addErrorMiddleware(true, true, true);

// Crear instancia de base de datos
try {
    $database = new Database();
} catch (Exception $e) {
    // Si falla la conexión a la base de datos, mostrar error
    $response = new \Slim\Psr7\Response();
    $response->getBody()->write(json_encode([
        'error' => 'Database connection failed: ' . $e->getMessage()
    ]));
    echo $response->getBody();
    exit();
}

// Ruta principal (ROOT)
$app->get('/', function (Request $request, Response $response) {
    $data = [
        'message' => 'Ecommerce API v1.0',
        'status' => 'running',
        'timestamp' => date('Y-m-d H:i:s'),
        'endpoints' => [
            'auth' => [
                'POST /api/auth/login',
                'POST /api/auth/register',
                'GET /api/auth/me',
                'PUT /api/auth/change-password',
                'PUT /api/auth/profile'
            ],
            'products' => [
                'GET /api/products',
                'GET /api/products/{id}',
                'POST /api/admin/products',
                'PUT /api/admin/products/{id}'
            ],
            'categories' => [
                'GET /api/categories',
                'GET /api/categories/{id}',
                'POST /api/admin/categories',
                'PUT /api/admin/categories/{id}'
            ],
            'settings' => [
                'GET /api/admin/settings',
                'PUT /api/admin/settings',
                'POST /api/admin/settings/validate',
                'GET /api/admin/settings/stats',
                'POST /api/admin/settings/logo',
                'GET /api/admin/settings/export',
                'POST /api/admin/settings/import',
                'POST /api/admin/settings/test-payment'
            ]
        ]
    ];

    $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json');
});

// Rutas públicas (sin autenticación)
$app->group('/api', function (RouteCollectorProxy $group) use ($database) {

    // Autenticación
    $group->post('/auth/login', function (Request $request, Response $response) use ($database) {
        try {
            $controller = new AuthController($database);
            return $controller->login($request, $response);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    $group->post('/auth/register', function (Request $request, Response $response) use ($database) {
        try {
            $controller = new AuthController($database);
            return $controller->register($request, $response);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    // Productos públicos
    $group->get('/products', function (Request $request, Response $response) use ($database) {
        try {
            $controller = new ProductController($database);
            return $controller->getAll($request, $response);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    $group->get('/products/{id}', function (Request $request, Response $response, array $args) use ($database) {
        try {
            $controller = new ProductController($database);
            return $controller->getOne($request, $response, $args);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    // Categorías públicas
    $group->get('/categories', function (Request $request, Response $response) use ($database) {
        try {
            $controller = new CategoryController($database);
            return $controller->getAll($request, $response);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    $group->get('/categories/{id}', function (Request $request, Response $response, array $args) use ($database) {
        try {
            $controller = new CategoryController($database);
            return $controller->getOne($request, $response, $args);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });
});

// Rutas protegidas (requieren autenticación)
$app->group('/api', function (RouteCollectorProxy $group) use ($database) {

    // ========== AUTENTICACIÓN Y PERFIL ==========
    $group->get('/auth/me', function (Request $request, Response $response) use ($database) {
        $controller = new AuthController($database);
        return $controller->me($request, $response);
    });

    $group->put('/auth/change-password', function (Request $request, Response $response) use ($database) {
        $controller = new AuthController($database);
        return $controller->changePassword($request, $response);
    });

    $group->put('/auth/profile', function (Request $request, Response $response) use ($database) {
        $controller = new AuthController($database);
        return $controller->updateProfile($request, $response);
    });

    $group->post('/auth/logout', function (Request $request, Response $response) use ($database) {
        $controller = new AuthController($database);
        return $controller->logout($request, $response);
    });

    $group->get('/auth/validate-token', function (Request $request, Response $response) use ($database) {
        $controller = new AuthController($database);
        return $controller->validateToken($request, $response);
    });

    // ========== DASHBOARD ==========
    $group->get('/dashboard/stats', function (Request $request, Response $response) use ($database) {
        $controller = new DashboardController($database);
        return $controller->getStats($request, $response);
    });

    // ========== CONFIGURACIONES (SETTINGS) ==========
    $group->group('/admin/settings', function (RouteCollectorProxy $settingsGroup) use ($database) {
        
        // Configuraciones básicas
        $settingsGroup->get('', function (Request $request, Response $response) use ($database) {
            $controller = new SettingsController($database);
            return $controller->getSettings($request, $response);
        });

        $settingsGroup->put('', function (Request $request, Response $response) use ($database) {
            $controller = new SettingsController($database);
            return $controller->updateSettings($request, $response);
        });

        // Validación
        $settingsGroup->post('/validate', function (Request $request, Response $response) use ($database) {
            $controller = new SettingsController($database);
            return $controller->validateSettings($request, $response);
        });

        // Estadísticas
        $settingsGroup->get('/stats', function (Request $request, Response $response) use ($database) {
            $controller = new SettingsController($database);
            return $controller->getStats($request, $response);
        });

        // Gestión de archivos
        $settingsGroup->post('/logo', function (Request $request, Response $response) use ($database) {
            $controller = new SettingsController($database);
            return $controller->uploadLogo($request, $response);
        });

        // Exportar/Importar
        $settingsGroup->get('/export', function (Request $request, Response $response) use ($database) {
            $controller = new SettingsController($database);
            return $controller->exportSettings($request, $response);
        });

        $settingsGroup->post('/import', function (Request $request, Response $response) use ($database) {
            $controller = new SettingsController($database);
            return $controller->importSettings($request, $response);
        });

        // Testing de métodos de pago
        $settingsGroup->post('/test-payment', function (Request $request, Response $response) use ($database) {
            $controller = new SettingsController($database);
            return $controller->testPaymentConnection($request, $response);
        });
    });

    // ========== ADMIN PRODUCTS ==========
    $group->group('/admin/products', function (RouteCollectorProxy $adminGroup) use ($database) {
        $adminGroup->get('', function (Request $request, Response $response) use ($database) {
            $controller = new ProductController($database);
            return $controller->getAll($request, $response);
        });

        $adminGroup->get('/{id}', function (Request $request, Response $response, array $args) use ($database) {
            $controller = new ProductController($database);
            return $controller->getOne($request, $response, $args);
        });

        $adminGroup->post('', function (Request $request, Response $response) use ($database) {
            $controller = new ProductController($database);
            return $controller->create($request, $response);
        });

        $adminGroup->put('/{id}', function (Request $request, Response $response, array $args) use ($database) {
            $controller = new ProductController($database);
            return $controller->update($request, $response, $args);
        });

        // NUEVA RUTA: POST para updates con archivos
        $adminGroup->post('/{id}', function (Request $request, Response $response, array $args) use ($database) {
            $controller = new ProductController($database);
            return $controller->update($request, $response, $args);
        });

        $adminGroup->delete('/{id}', function (Request $request, Response $response, array $args) use ($database) {
            $controller = new ProductController($database);
            return $controller->delete($request, $response, $args);
        });

        // ========== GESTIÓN DE IMÁGENES ==========
        
        // Eliminar imagen específica
        $adminGroup->delete('/{product_id}/images/{image_id}', function (Request $request, Response $response, array $args) use ($database) {
            $controller = new ProductController($database);
            return $controller->deleteImage($request, $response, $args);
        });

        // Reordenar imágenes
        $adminGroup->put('/{product_id}/images/reorder', function (Request $request, Response $response, array $args) use ($database) {
            $controller = new ProductController($database);
            return $controller->reorderImages($request, $response, $args);
        });

        // Establecer imagen primaria
        $adminGroup->put('/{product_id}/images/{image_id}/primary', function (Request $request, Response $response, array $args) use ($database) {
            $controller = new ProductController($database);
            return $controller->setPrimaryImage($request, $response, $args);
        });
    });

    // ========== ADMIN CATEGORIES ==========
    $group->group('/admin/categories', function (RouteCollectorProxy $adminGroup) use ($database) {
        $adminGroup->get('', function (Request $request, Response $response) use ($database) {
            $controller = new CategoryController($database);
            return $controller->getAll($request, $response);
        });

        $adminGroup->get('/{id}', function (Request $request, Response $response, array $args) use ($database) {
            $controller = new CategoryController($database);
            return $controller->getOne($request, $response, $args);
        });

        $adminGroup->post('', function (Request $request, Response $response) use ($database) {
            $controller = new CategoryController($database);
            return $controller->create($request, $response);
        });

        $adminGroup->put('/{id}', function (Request $request, Response $response, array $args) use ($database) {
            $controller = new CategoryController($database);
            return $controller->update($request, $response, $args);
        });

        $adminGroup->delete('/{id}', function (Request $request, Response $response, array $args) use ($database) {
            $controller = new CategoryController($database);
            return $controller->delete($request, $response, $args);
        });
    });

    // ========== ADMIN USERS ==========
    $group->group('/admin/users', function (RouteCollectorProxy $adminGroup) use ($database) {
        $adminGroup->get('', function (Request $request, Response $response) use ($database) {
            $controller = new UserController($database);
            return $controller->getAll($request, $response);
        });

        $adminGroup->get('/{id}', function (Request $request, Response $response, array $args) use ($database) {
            $controller = new UserController($database);
            return $controller->getOne($request, $response, $args);
        });

        $adminGroup->post('', function (Request $request, Response $response) use ($database) {
            $controller = new UserController($database);
            return $controller->create($request, $response);
        });

        $adminGroup->put('/{id}', function (Request $request, Response $response, array $args) use ($database) {
            $controller = new UserController($database);
            return $controller->update($request, $response, $args);
        });

        $adminGroup->delete('/{id}', function (Request $request, Response $response, array $args) use ($database) {
            $controller = new UserController($database);
            return $controller->delete($request, $response, $args);
        });
    });

    // ========== ADMIN ORDERS ==========
    $group->group('/admin/orders', function (RouteCollectorProxy $adminGroup) use ($database) {
        $adminGroup->get('', function (Request $request, Response $response) use ($database) {
            $controller = new OrderController($database);
            return $controller->getAll($request, $response);
        });

        $adminGroup->get('/{id}', function (Request $request, Response $response, array $args) use ($database) {
            $controller = new OrderController($database);
            return $controller->getOne($request, $response, $args);
        });

        $adminGroup->post('', function (Request $request, Response $response) use ($database) {
            $controller = new OrderController($database);
            return $controller->create($request, $response);
        });

        $adminGroup->put('/{id}/status', function (Request $request, Response $response, array $args) use ($database) {
            $controller = new OrderController($database);
            return $controller->updateStatus($request, $response, $args);
        });

        $adminGroup->delete('/{id}', function (Request $request, Response $response, array $args) use ($database) {
            $controller = new OrderController($database);
            return $controller->delete($request, $response, $args);
        });
    });
})->add(new AuthMiddleware());

// Ejecutar la aplicación
$app->run();