<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class DashboardController {
    private $db;

    public function __construct($database) {
        $this->db = $database->getConnection();
    }

    public function getStats(Request $request, Response $response): Response {
        try {
            $stats = [];
            
            // Estadísticas generales
            $stats['totals'] = $this->getTotalStats();
            
            // Ventas por mes (últimos 12 meses)
            $stats['monthly_sales'] = $this->getMonthlySales();
            
            // Productos más vendidos
            $stats['top_products'] = $this->getTopProducts();
            
            // Órdenes recientes
            $stats['recent_orders'] = $this->getRecentOrders();
            
            // Productos con stock bajo
            $stats['low_stock'] = $this->getLowStockProducts();
            
            $response->getBody()->write(json_encode($stats));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Failed to fetch dashboard stats']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    private function getTotalStats() {
        $stats = [];
        
        // Total de productos
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM products WHERE status = 'active'");
        $stmt->execute();
        $stats['total_products'] = $stmt->fetch()['count'];
        
        // Total de órdenes
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM orders");
        $stmt->execute();
        $stats['total_orders'] = $stmt->fetch()['count'];
        
        // Total de usuarios
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
        $stmt->execute();
        $stats['total_users'] = $stmt->fetch()['count'];
        
        // Ingresos totales
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE payment_status = 'paid'");
        $stmt->execute();
        $stats['total_revenue'] = $stmt->fetch()['total'];
        
        // Ingresos del mes actual
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders 
                                   WHERE payment_status = 'paid' AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
        $stmt->execute();
        $stats['monthly_revenue'] = $stmt->fetch()['total'];
        
        // Órdenes pendientes
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
        $stmt->execute();
        $stats['pending_orders'] = $stmt->fetch()['count'];
        
        return $stats;
    }

    private function getMonthlySales() {
        $stmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as orders,
                COALESCE(SUM(total_amount), 0) as revenue
            FROM orders 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function getTopProducts() {
        $stmt = $this->db->prepare("
            SELECT 
                p.name,
                p.price,
                SUM(oi.quantity) as total_sold,
                SUM(oi.total) as total_revenue
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            JOIN orders o ON oi.order_id = o.id
            WHERE o.status NOT IN ('cancelled')
            GROUP BY p.id, p.name, p.price
            ORDER BY total_sold DESC
            LIMIT 10
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function getRecentOrders() {
        $stmt = $this->db->prepare("
            SELECT 
                id,
                order_number,
                customer_name,
                total_amount,
                status,
                payment_status,
                created_at
            FROM orders
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function getLowStockProducts() {
        $stmt = $this->db->prepare("
            SELECT 
                id,
                name,
                sku,
                stock,
                min_stock
            FROM products
            WHERE status = 'active' AND stock <= min_stock AND min_stock > 0
            ORDER BY stock ASC
            LIMIT 10
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
}