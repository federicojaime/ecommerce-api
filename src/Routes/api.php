<?php
use App\Controllers\AuthController;
use App\Controllers\ProductController;
use App\Controllers\CategoryController;
use App\Controllers\UserController;
use App\Controllers\OrderController;
use App\Controllers\DashboardController;
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
            $adminGroup->get('/{id}', [ProductController::class, 'getOne']);
            $adminGroup->post('', [ProductController::class, 'create']);
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
            $adminGroup->get('', [OrderController::class, 'getAll']);
            $adminGroup->get('/{id}', [OrderController::class, 'getOne']);
            $adminGroup->post('', [OrderController::class, 'create']);
            $adminGroup->put('/{id}/status', [OrderController::class, 'updateStatus']);
            $adminGroup->delete('/{id}', [OrderController::class, 'delete']);
        });
        
    })->add(new AuthMiddleware());
    
};