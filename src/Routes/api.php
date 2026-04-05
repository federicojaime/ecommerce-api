<?php
use App\Controllers\AuthController;
use App\Controllers\ProductController;
use App\Controllers\CategoryController;
use App\Controllers\UserController;
use App\Controllers\OrderController;
use App\Controllers\DashboardController;
use App\Controllers\ReservationController;
use App\Middleware\AuthMiddleware;
use Slim\Routing\RouteCollectorProxy;

return function ($app) {
    
    // Rutas públicas (sin autenticación)
    $app->group('/api', function (RouteCollectorProxy $group) {
        
        // Autenticación
        $group->post('/auth/login', [AuthController::class, 'login']);
        $group->post('/auth/register', [AuthController::class, 'register']);
        
        // Productos públicos
        $group->get('/products', [ProductController::class, 'getAll']);
        $group->get('/products/{id}', [ProductController::class, 'getOne']);
        
        // Categorías públicas
        $group->get('/categories', [CategoryController::class, 'getAll']);
        $group->get('/categories/{id}', [CategoryController::class, 'getOne']);
        
    });

    // Rutas protegidas (requieren autenticación)
    $app->group('/api', function (RouteCollectorProxy $group) {
        
        // Perfil de usuario
        $group->get('/auth/me', [AuthController::class, 'me']);
        
        // Dashboard
        $group->get('/dashboard/stats', [DashboardController::class, 'getStats']);
        
        // Admin Products
        $group->group('/admin/products', function (RouteCollectorProxy $adminGroup) {
            $adminGroup->get('', [ProductController::class, 'getAll']);
            $adminGroup->post('', [ProductController::class, 'create']);

            // Rutas de ordenamiento (deben ir ANTES de /{id})
            $adminGroup->get('/sorted', [ProductController::class, 'getSortedProducts']);
            $adminGroup->post('/reorder', [ProductController::class, 'reorderProducts']);

            // Rutas de imágenes (deben ir ANTES de /{id})
            $adminGroup->post('/{id}/images', [ProductController::class, 'uploadImages']);
            $adminGroup->delete('/{product_id}/images/{image_id}', [ProductController::class, 'deleteImage']);
            $adminGroup->put('/{product_id}/images/reorder', [ProductController::class, 'reorderImages']);
            $adminGroup->put('/{product_id}/images/{image_id}/primary', [ProductController::class, 'setPrimaryImage']);

            $adminGroup->get('/{id}', [ProductController::class, 'getOne']);
            $adminGroup->delete('/{id}', [ProductController::class, 'delete']);

            // Manejar tanto PUT como POST para updates
            $adminGroup->map(['PUT', 'POST'], '/{id}', [ProductController::class, 'update']);
        });
        
        // Admin Categories
        $group->group('/admin/categories', function (RouteCollectorProxy $adminGroup) {
            $adminGroup->get('', [CategoryController::class, 'getAll']);
            $adminGroup->get('/{id}', [CategoryController::class, 'getOne']);
            $adminGroup->post('', [CategoryController::class, 'create']);
            $adminGroup->put('/{id}', [CategoryController::class, 'update']);
            $adminGroup->delete('/{id}', [CategoryController::class, 'delete']);
        });
        
        // Admin Users
        $group->group('/admin/users', function (RouteCollectorProxy $adminGroup) {
            $adminGroup->get('', [UserController::class, 'getAll']);
            $adminGroup->get('/{id}', [UserController::class, 'getOne']);
            $adminGroup->post('', [UserController::class, 'create']);
            $adminGroup->put('/{id}', [UserController::class, 'update']);
            $adminGroup->delete('/{id}', [UserController::class, 'delete']);
        });
        
        // Admin Orders
        $group->group('/admin/orders', function (RouteCollectorProxy $adminGroup) {
            $adminGroup->get('/export', [OrderController::class, 'export']);
            $adminGroup->get('', [OrderController::class, 'getAll']);
            $adminGroup->get('/{id}', [OrderController::class, 'getOne']);
            $adminGroup->post('', [OrderController::class, 'create']);
            $adminGroup->put('/{id}/status', [OrderController::class, 'updateStatus']);
            $adminGroup->delete('/{id}', [OrderController::class, 'delete']);
        });

        // Admin Reservations
        $group->group('/admin/reservations', function (RouteCollectorProxy $adminGroup) {
            $adminGroup->get('/export', [ReservationController::class, 'export']); // Si existiera
            $adminGroup->get('', [ReservationController::class, 'getAll']);
            $adminGroup->get('/{id}/receipt', [ReservationController::class, 'generateReceipt']);
            $adminGroup->get('/{id}', [ReservationController::class, 'getOne']);
            $adminGroup->post('/{id}/confirm', [ReservationController::class, 'confirm']);
            $adminGroup->post('/{id}/reject', [ReservationController::class, 'reject']);
        });

    })->add(new AuthMiddleware());

    // Rutas públicas de reservas (sin autenticación)
    $app->group('/api', function (RouteCollectorProxy $group) {
        $group->post('/reservations', [ReservationController::class, 'create']);
    });

};