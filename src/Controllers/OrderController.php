<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class OrderController {
    private $db;

    public function __construct($database) {
        $this->db = $database->getConnection();
    }

    public function getAll(Request $request, Response $response): Response {
        $params = $request->getQueryParams();
        
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = min(100, max(1, (int)($params['limit'] ?? 10)));
        $offset = ($page - 1) * $limit;
        
        $status = $params['status'] ?? '';
        $search = $params['search'] ?? '';
        
        $where = [];
        $bindings = [];
        
        if ($status) {
            $where[] = "o.status = ?";
            $bindings[] = $status;
        }
        
        if ($search) {
            $where[] = "(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_email LIKE ?)";
            $searchTerm = "%{$search}%";
            $bindings = array_merge($bindings, [$searchTerm, $searchTerm, $searchTerm]);
        }
        
        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $countSql = "SELECT COUNT(*) as total FROM orders o {$whereClause}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($bindings);
        $total = $countStmt->fetch()['total'];
        
        $sql = "SELECT o.*, u.name as customer_user_name,
                       (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count
                FROM orders o 
                LEFT JOIN users u ON o.customer_id = u.id 
                {$whereClause}
                ORDER BY o.id DESC 
                LIMIT ? OFFSET ?";
        
        $bindings[] = $limit;
        $bindings[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        $orders = $stmt->fetchAll();
        
        $result = [
            'data' => $orders,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ];
        
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getOne(Request $request, Response $response, array $args): Response {
        $id = $args['id'];
        
        $sql = "SELECT o.*, u.name as customer_user_name FROM orders o 
                LEFT JOIN users u ON o.customer_id = u.id 
                WHERE o.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        
        if (!$order) {
            $response->getBody()->write(json_encode(['error' => 'Order not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        
        $itemsStmt = $this->db->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id");
        $itemsStmt->execute([$id]);
        $order['items'] = $itemsStmt->fetchAll();
        
        $response->getBody()->write(json_encode($order));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response {
        $data = json_decode($request->getBody()->getContents(), true);
        
        $required = ['customer_name', 'customer_email', 'items'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $response->getBody()->write(json_encode(['error' => $field . ' is required']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
        }
        
        try {
            $this->db->beginTransaction();
            
            $orderNumber = $this->generateOrderNumber();
            
            $subtotal = 0;
            $validatedItems = [];
            
            foreach ($data['items'] as $item) {
                if (empty($item['product_id']) || empty($item['quantity']) || $item['quantity'] <= 0) {
                    throw new \Exception('Invalid item data');
                }
                
                $productStmt = $this->db->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
                $productStmt->execute([$item['product_id']]);
                $product = $productStmt->fetch();
                
                if (!$product) {
                    throw new \Exception('Product not found: ' . $item['product_id']);
                }
                
                if ($product['stock'] < $item['quantity']) {
                    throw new \Exception('Insufficient stock for product: ' . $product['name']);
                }
                
                $price = $product['sale_price'] ?? $product['price'];
                $itemTotal = $price * $item['quantity'];
                $subtotal += $itemTotal;
                
                $validatedItems[] = [
                    'product_id' => $product['id'],
                    'product_name' => $product['name'],
                    'product_sku' => $product['sku'],
                    'quantity' => $item['quantity'],
                    'price' => $price,
                    'total' => $itemTotal
                ];
            }
            
            $taxAmount = $data['tax_amount'] ?? 0;
            $shippingAmount = $data['shipping_amount'] ?? 0;
            $totalAmount = $subtotal + $taxAmount + $shippingAmount;
            
            $orderSql = "INSERT INTO orders (order_number, customer_id, customer_name, customer_email, customer_phone, 
                                           status, subtotal, tax_amount, shipping_amount, total_amount, payment_method, 
                                           shipping_address, billing_address, notes) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $orderParams = [
                $orderNumber,
                $data['customer_id'] ?? null,
                $data['customer_name'],
                $data['customer_email'],
                $data['customer_phone'] ?? null,
                $data['status'] ?? 'pending',
                $subtotal,
                $taxAmount,
                $shippingAmount,
                $totalAmount,
                $data['payment_method'] ?? null,
                $data['shipping_address'] ?? null,
                $data['billing_address'] ?? null,
                $data['notes'] ?? null
            ];
            
            $orderStmt = $this->db->prepare($orderSql);
            $orderStmt->execute($orderParams);
            $orderId = $this->db->lastInsertId();
            
            $itemSql = "INSERT INTO order_items (order_id, product_id, product_name, product_sku, quantity, price, total) 
                       VALUES (?, ?, ?, ?, ?, ?, ?)";
            $itemStmt = $this->db->prepare($itemSql);
            
            foreach ($validatedItems as $item) {
                $itemStmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['product_name'],
                    $item['product_sku'],
                    $item['quantity'],
                    $item['price'],
                    $item['total']
                ]);
                
                $updateStockStmt = $this->db->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                $updateStockStmt->execute([$item['quantity'], $item['product_id']]);
            }
            
            $this->db->commit();
            
            $response->getBody()->write(json_encode([
                'message' => 'Order created successfully',
                'order_id' => $orderId,
                'order_number' => $orderNumber
            ]));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $this->db->rollback();
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
    }

    public function updateStatus(Request $request, Response $response, array $args): Response {
        $id = $args['id'];
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (empty($data['status'])) {
            $response->getBody()->write(json_encode(['error' => 'Status is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
        $allowedStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
        if (!in_array($data['status'], $allowedStatuses)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid status']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
        $checkStmt = $this->db->prepare("SELECT id, status FROM orders WHERE id = ?");
        $checkStmt->execute([$id]);
        $order = $checkStmt->fetch();
        
        if (!$order) {
            $response->getBody()->write(json_encode(['error' => 'Order not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        
        $stmt = $this->db->prepare("UPDATE orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$data['status'], $id]);
        
        $response->getBody()->write(json_encode(['message' => 'Order status updated successfully']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function delete(Request $request, Response $response, array $args): Response {
        $id = $args['id'];
        
        $checkStmt = $this->db->prepare("SELECT id, status FROM orders WHERE id = ?");
        $checkStmt->execute([$id]);
        $order = $checkStmt->fetch();
        
        if (!$order) {
            $response->getBody()->write(json_encode(['error' => 'Order not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        
        if (!in_array($order['status'], ['cancelled', 'pending'])) {
            $response->getBody()->write(json_encode(['error' => 'Cannot delete processed orders']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
        try {
            $this->db->beginTransaction();
            
            if ($order['status'] !== 'cancelled') {
                $itemsStmt = $this->db->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
                $itemsStmt->execute([$id]);
                $items = $itemsStmt->fetchAll();
                
                $updateStockStmt = $this->db->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                foreach ($items as $item) {
                    $updateStockStmt->execute([$item['quantity'], $item['product_id']]);
                }
            }
            
            $stmt = $this->db->prepare("DELETE FROM orders WHERE id = ?");
            $stmt->execute([$id]);
            
            $this->db->commit();
            
            $response->getBody()->write(json_encode(['message' => 'Order deleted successfully']));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $this->db->rollback();
            $response->getBody()->write(json_encode(['error' => 'Failed to delete order']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    private function generateOrderNumber() {
        $prefix = 'ORD';
        $timestamp = date('Ymd');
        
        $stmt = $this->db->prepare("SELECT order_number FROM orders WHERE order_number LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt->execute(["{$prefix}{$timestamp}%"]);
        $lastOrder = $stmt->fetch();
        
        if ($lastOrder) {
            $lastNumber = (int)substr($lastOrder['order_number'], -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }
        
        return $prefix . $timestamp . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
