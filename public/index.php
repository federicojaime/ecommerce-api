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
use App\Controllers\CartController;
use App\Controllers\CheckoutController;
use App\Controllers\CustomerOrderController;
use App\Controllers\WishlistController;
use App\Controllers\ReviewController;
use App\Controllers\AddressController;
use App\Controllers\CouponController;
use App\Controllers\NotificationController;
use App\Controllers\MercadoPagoController;
use App\Controllers\PaymentController;
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

// Obtener instancia SINGLETON de base de datos (reutiliza conexión)
try {
    $database = Database::getInstance();
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
$app->get('/', function (Request $request, Response $response) use ($database) {
    $data = [
        'message' => 'Ecommerce API v1.0',
        'status' => 'running',
        'timestamp' => date('Y-m-d H:i:s'),
        'db_connections_created' => Database::getConnectionCount(),
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

    $group->post('/auth/forgot-password', function (Request $request, Response $response) use ($database) {
        try {
            $controller = new AuthController($database);
            return $controller->forgotPassword($request, $response);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    $group->post('/auth/reset-password', function (Request $request, Response $response) use ($database) {
        try {
            $controller = new AuthController($database);
            return $controller->resetPassword($request, $response);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    $group->post('/auth/google', function (Request $request, Response $response) use ($database) {
        try {
            $controller = new AuthController($database);
            return $controller->googleLogin($request, $response);
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

    // Reseñas públicas de productos
    $group->get('/products/{product_id}/reviews', function (Request $request, Response $response, array $args) use ($database) {
        try {
            $controller = new ReviewController($database);
            return $controller->getProductReviews($request, $response, $args);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    // Validar cupón (público para preview antes de login)
    $group->post('/coupons/validate', function (Request $request, Response $response) use ($database) {
        try {
            $controller = new CouponController($database);
            return $controller->validate($request, $response);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    // ========== MERCADO PAGO WEBHOOKS (PÚBLICO) ==========
    $group->post('/webhooks/mercadopago', function (Request $request, Response $response) use ($database) {
        try {
            $controller = new MercadoPagoController($database);
            return $controller->handleWebhook($request, $response);
        } catch (Exception $e) {
            error_log('Webhook handler error: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    // Obtener public key de Mercado Pago (público para el frontend)
    $group->get('/mercadopago/public-key', function (Request $request, Response $response) use ($database) {
        try {
            $controller = new MercadoPagoController($database);
            return $controller->getPublicKey($request, $response);
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

        // Subir imágenes adicionales
        $adminGroup->post('/{id}/images', function (Request $request, Response $response, array $args) use ($database) {
            $controller = new ProductController($database);
            return $controller->uploadImages($request, $response, $args);
        });

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

    // ========== CARRITO DE COMPRAS ==========
    $group->get('/cart', function (Request $request, Response $response) use ($database) {
        $controller = new CartController($database);
        return $controller->getCart($request, $response);
    });

    $group->post('/cart', function (Request $request, Response $response) use ($database) {
        $controller = new CartController($database);
        return $controller->addItem($request, $response);
    });

    $group->put('/cart/items/{id}', function (Request $request, Response $response, array $args) use ($database) {
        $controller = new CartController($database);
        return $controller->updateItem($request, $response, $args);
    });

    $group->delete('/cart/items/{id}', function (Request $request, Response $response, array $args) use ($database) {
        $controller = new CartController($database);
        return $controller->removeItem($request, $response, $args);
    });

    $group->delete('/cart', function (Request $request, Response $response) use ($database) {
        $controller = new CartController($database);
        return $controller->clearCart($request, $response);
    });

    // ========== CHECKOUT ==========
    $group->post('/checkout/validate', function (Request $request, Response $response) use ($database) {
        $controller = new CheckoutController($database);
        return $controller->validate($request, $response);
    });

    $group->post('/checkout/calculate', function (Request $request, Response $response) use ($database) {
        $controller = new CheckoutController($database);
        return $controller->calculate($request, $response);
    });

    $group->post('/checkout/complete', function (Request $request, Response $response) use ($database) {
        $controller = new CheckoutController($database);
        return $controller->complete($request, $response);
    });

    // Alias para create-order (mismo que complete)
    $group->post('/checkout/create-order', function (Request $request, Response $response) use ($database) {
        $controller = new CheckoutController($database);
        return $controller->complete($request, $response);
    });

    // ========== MERCADO PAGO ==========
    $group->post('/checkout/mercadopago/create-preference', function (Request $request, Response $response) use ($database) {
        $controller = new MercadoPagoController($database);
        return $controller->createPreference($request, $response);
    });

    $group->get('/checkout/mercadopago/success', function (Request $request, Response $response) use ($database) {
        $controller = new MercadoPagoController($database);
        return $controller->success($request, $response);
    });

    $group->get('/checkout/mercadopago/failure', function (Request $request, Response $response) use ($database) {
        $controller = new MercadoPagoController($database);
        return $controller->failure($request, $response);
    });

    $group->get('/checkout/mercadopago/pending', function (Request $request, Response $response) use ($database) {
        $controller = new MercadoPagoController($database);
        return $controller->pending($request, $response);
    });

    // ========== PAYMENTS ==========
    $group->get('/payments', function (Request $request, Response $response) use ($database) {
        $controller = new PaymentController($database);
        return $controller->getAllPayments($request, $response);
    });

    $group->get('/payments/{orderId}', function (Request $request, Response $response, array $args) use ($database) {
        $controller = new PaymentController($database);
        return $controller->getPaymentByOrder($request, $response, $args);
    });

    $group->get('/payments/detail/{paymentId}', function (Request $request, Response $response, array $args) use ($database) {
        $controller = new PaymentController($database);
        return $controller->getPaymentDetail($request, $response, $args);
    });

    // ========== ÓRDENES DEL CLIENTE ==========
    $group->get('/orders', function (Request $request, Response $response) use ($database) {
        $controller = new CustomerOrderController($database);
        return $controller->getMyOrders($request, $response);
    });

    $group->get('/orders/{id}', function (Request $request, Response $response, array $args) use ($database) {
        $controller = new CustomerOrderController($database);
        return $controller->getMyOrder($request, $response, $args);
    });

    $group->post('/orders/{id}/cancel', function (Request $request, Response $response, array $args) use ($database) {
        $controller = new CustomerOrderController($database);
        return $controller->cancelOrder($request, $response, $args);
    });

    // ========== WISHLIST (LISTA DE DESEOS) ==========
    $group->get('/wishlist', function (Request $request, Response $response) use ($database) {
        $controller = new WishlistController($database);
        return $controller->getWishlist($request, $response);
    });

    $group->post('/wishlist', function (Request $request, Response $response) use ($database) {
        $controller = new WishlistController($database);
        return $controller->addItem($request, $response);
    });

    $group->delete('/wishlist/{product_id}', function (Request $request, Response $response, array $args) use ($database) {
        $controller = new WishlistController($database);
        return $controller->removeItem($request, $response, $args);
    });

    // ========== RESEÑAS DE PRODUCTOS ==========
    $group->post('/products/{product_id}/reviews', function (Request $request, Response $response, array $args) use ($database) {
        $controller = new ReviewController($database);
        return $controller->createReview($request, $response, $args);
    });

    $group->put('/reviews/{id}', function (Request $request, Response $response, array $args) use ($database) {
        $controller = new ReviewController($database);
        return $controller->updateReview($request, $response, $args);
    });

    $group->delete('/reviews/{id}', function (Request $request, Response $response, array $args) use ($database) {
        $controller = new ReviewController($database);
        return $controller->deleteReview($request, $response, $args);
    });

    // ========== DIRECCIONES ==========
    $group->get('/addresses', function (Request $request, Response $response) use ($database) {
        $controller = new AddressController($database);
        return $controller->getAll($request, $response);
    });

    $group->post('/addresses', function (Request $request, Response $response) use ($database) {
        $controller = new AddressController($database);
        return $controller->create($request, $response);
    });

    $group->put('/addresses/{id}', function (Request $request, Response $response, array $args) use ($database) {
        $controller = new AddressController($database);
        return $controller->update($request, $response, $args);
    });

    $group->delete('/addresses/{id}', function (Request $request, Response $response, array $args) use ($database) {
        $controller = new AddressController($database);
        return $controller->delete($request, $response, $args);
    });

    $group->put('/addresses/{id}/default', function (Request $request, Response $response, array $args) use ($database) {
        $controller = new AddressController($database);
        return $controller->setDefault($request, $response, $args);
    });

    // ========== NOTIFICACIONES ==========
    $group->get('/notifications', function (Request $request, Response $response) use ($database) {
        $controller = new NotificationController($database);
        return $controller->getAll($request, $response);
    });

    $group->put('/notifications/{id}/read', function (Request $request, Response $response, array $args) use ($database) {
        $controller = new NotificationController($database);
        return $controller->markAsRead($request, $response, $args);
    });

    $group->post('/notifications/read-all', function (Request $request, Response $response) use ($database) {
        $controller = new NotificationController($database);
        return $controller->markAllAsRead($request, $response);
    });

    $group->delete('/notifications/{id}', function (Request $request, Response $response, array $args) use ($database) {
        $controller = new NotificationController($database);
        return $controller->delete($request, $response, $args);
    });

    // ========== ADMIN: NOTIFICACIONES ==========
    $group->get('/admin/notifications', function (Request $request, Response $response) use ($database) {
        $controller = new NotificationController($database);
        return $controller->getAll($request, $response);
    });

    $group->put('/admin/notifications/{id}/read', function (Request $request, Response $response, array $args) use ($database) {
        $controller = new NotificationController($database);
        return $controller->markAsRead($request, $response, $args);
    });

    $group->post('/admin/notifications/read-all', function (Request $request, Response $response) use ($database) {
        $controller = new NotificationController($database);
        return $controller->markAllAsRead($request, $response);
    });

    $group->delete('/admin/notifications/{id}', function (Request $request, Response $response, array $args) use ($database) {
        $controller = new NotificationController($database);
        return $controller->delete($request, $response, $args);
    });

    // ========== ADMIN: RESEÑAS ==========
    $group->get('/admin/reviews', function (Request $request, Response $response) use ($database) {
        $controller = new ReviewController($database);
        return $controller->getAllReviews($request, $response);
    });

    $group->put('/admin/reviews/{id}/moderate', function (Request $request, Response $response, array $args) use ($database) {
        $controller = new ReviewController($database);
        return $controller->moderateReview($request, $response, $args);
    });

    // ========== ADMIN: CUPONES ==========
    $group->get('/admin/coupons', function (Request $request, Response $response) use ($database) {
        $controller = new CouponController($database);
        return $controller->getAll($request, $response);
    });

    $group->post('/admin/coupons', function (Request $request, Response $response) use ($database) {
        $controller = new CouponController($database);
        return $controller->create($request, $response);
    });

    $group->put('/admin/coupons/{id}', function (Request $request, Response $response, array $args) use ($database) {
        $controller = new CouponController($database);
        return $controller->update($request, $response, $args);
    });

    $group->delete('/admin/coupons/{id}', function (Request $request, Response $response, array $args) use ($database) {
        $controller = new CouponController($database);
        return $controller->delete($request, $response, $args);
    });
})->add(new AuthMiddleware());

// Debug de variables de entorno
error_log("DB_HOST: " . ($_ENV['DB_HOST'] ?? 'NOT SET'));
error_log("DB_NAME: " . ($_ENV['DB_NAME'] ?? 'NOT SET'));
error_log("DB_USER: " . ($_ENV['DB_USER'] ?? 'NOT SET'));
error_log("DB_PASS: " . (isset($_ENV['DB_PASS']) ? 'SET' : 'NOT SET'));
// Ejecutar la aplicación
$app->run();