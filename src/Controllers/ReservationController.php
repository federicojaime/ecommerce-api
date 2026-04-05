<?php

namespace App\Controllers;

use App\Models\Database;
use App\Services\EmailService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

class ReservationController
{
    private $db;
    private $emailService;

    public function __construct(Database $database)
    {
        $this->db = $database->getConnection();
        $this->emailService = new EmailService($database);
    }

    /**
     * Crear nueva reserva (desde frontend cliente)
     * POST /api/reservations
     */
    public function create(Request $request, Response $response): Response
    {
        try {
            $this->db->beginTransaction();

            // Leer el body como JSON manualmente
            $body = $request->getBody()->getContents();
            $data = json_decode($body, true);

            if (!$data) {
                $data = $request->getParsedBody();
            }

            // Validar campos requeridos
            $requiredFields = ['customer_name', 'customer_email', 'customer_phone'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty(trim($data[$field]))) {
                    $this->db->rollBack();
                    $response->getBody()->write(json_encode([
                        'error' => ucfirst(str_replace('_', ' ', $field)) . ' is required'
                    ]));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }
            }

            // Validar que haya items (acepta 'items' o 'cart')
            $requestItems = $data['items'] ?? $data['cart'] ?? null;

            if (!$requestItems || !is_array($requestItems) || empty($requestItems)) {
                $this->db->rollBack();
                $response->getBody()->write(json_encode(['error' => 'No items provided']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Validar y obtener datos de productos
            $reservationItems = [];
            $subtotal = 0;

            foreach ($requestItems as $item) {
                if (!isset($item['product_id']) || !isset($item['quantity'])) {
                    $this->db->rollBack();
                    $response->getBody()->write(json_encode(['error' => 'Invalid item data']));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }

                // Obtener datos del producto desde BD
                $stmt = $this->db->prepare("SELECT * FROM products WHERE id = :id AND status = 'active'");
                $stmt->execute(['id' => $item['product_id']]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$product) {
                    $this->db->rollBack();
                    $response->getBody()->write(json_encode(['error' => 'Product not found: ' . $item['product_id']]));
                    return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
                }

                $price = $item['price'] ?? $product['price'];
                $quantity = intval($item['quantity']);
                $itemTotal = floatval($price) * $quantity;

                $reservationItems[] = [
                    'product_id' => $product['id'],
                    'product_name' => $product['name'],
                    'product_sku' => $product['sku'],
                    'price' => $price,
                    'quantity' => $quantity,
                    'total' => $itemTotal
                ];

                $subtotal += $itemTotal;
            }

            // Calcular totales
            $taxRate = 0.00; // Sin impuestos en reservas por ahora
            $shippingAmount = $data['shipping_amount'] ?? 0.00;
            $discountAmount = 0.00;

            $taxAmount = ($subtotal * $taxRate) / 100;
            $totalAmount = $subtotal + $taxAmount + $shippingAmount - $discountAmount;

            // Generar número de reserva
            $reservationNumber = 'RES' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

            // Crear reserva
            $stmt = $this->db->prepare("
                INSERT INTO reservations (
                    reservation_number, customer_name, customer_email, customer_phone,
                    shipping_address, shipping_city, shipping_state, shipping_zip_code,
                    status, subtotal, tax_amount, shipping_amount, discount_amount, total_amount, notes
                ) VALUES (
                    :reservation_number, :customer_name, :customer_email, :customer_phone,
                    :shipping_address, :shipping_city, :shipping_state, :shipping_zip_code,
                    'pending', :subtotal, :tax_amount, :shipping_amount, :discount_amount, :total_amount, :notes
                )
            ");

            $stmt->execute([
                'reservation_number' => $reservationNumber,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'],
                'shipping_address' => $data['shipping_address'] ?? null,
                'shipping_city' => $data['shipping_city'] ?? null,
                'shipping_state' => $data['shipping_state'] ?? null,
                'shipping_zip_code' => $data['shipping_zip_code'] ?? null,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'shipping_amount' => $shippingAmount,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,
                'notes' => $data['notes'] ?? null
            ]);

            $reservationId = $this->db->lastInsertId();

            // Crear items de la reserva
            foreach ($reservationItems as $item) {
                $stmt = $this->db->prepare("
                    INSERT INTO reservation_items (reservation_id, product_id, product_name, product_sku, quantity, price, total)
                    VALUES (:reservation_id, :product_id, :product_name, :product_sku, :quantity, :price, :total)
                ");
                $stmt->execute([
                    'reservation_id' => $reservationId,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'product_sku' => $item['product_sku'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $item['total']
                ]);
            }

            // Log de creación
            $this->logReservationAction($reservationId, 'created', null, 'Reserva creada desde frontend');

            $this->db->commit();

            // Enviar emails (en background para no bloquear respuesta)
            try {
                // Email al cliente
                $this->emailService->sendReservationCreated($reservationId);

                // Email al admin
                $this->emailService->sendAdminNewReservation($reservationId);

                $this->logReservationAction($reservationId, 'email_sent', null, 'Emails enviados exitosamente');
            } catch (\Exception $e) {
                error_log('Error sending reservation emails: ' . $e->getMessage());
                // No fallar la reserva si el email falla
            }

            // Respuesta
            $result = [
                'success' => true,
                'message' => 'Reservation created successfully',
                'reservation_id' => $reservationId,
                'reservation_number' => $reservationNumber,
                'total_amount' => number_format($totalAmount, 2, '.', ''),
                'status' => 'pending'
            ];

            $response->getBody()->write(json_encode($result));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Create reservation error: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Confirmar reserva (desde panel admin)
     * POST /api/admin/reservations/{id}/confirm
     */
    public function confirm(Request $request, Response $response, array $args): Response
    {
        try {
            $this->db->beginTransaction();

            $reservationId = intval($args['id']);

            // Leer body para notas del admin
            $body = $request->getBody()->getContents();
            $data = json_decode($body, true);
            if (!$data) {
                $data = $request->getParsedBody();
            }

            // Obtener user_id del admin
            $user = $request->getAttribute('user');
            $adminId = $user->user_id ?? null;

            // Obtener reserva
            $stmt = $this->db->prepare("SELECT * FROM reservations WHERE id = :id");
            $stmt->execute(['id' => $reservationId]);
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reservation) {
                $this->db->rollBack();
                $response->getBody()->write(json_encode(['error' => 'Reservation not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            if ($reservation['status'] !== 'pending') {
                $this->db->rollBack();
                $response->getBody()->write(json_encode(['error' => 'Reservation already processed']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Obtener items
            $stmt = $this->db->prepare("SELECT * FROM reservation_items WHERE reservation_id = :id");
            $stmt->execute(['id' => $reservationId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Verificar y descontar stock
            foreach ($items as $item) {
                // Verificar stock disponible
                $stmt = $this->db->prepare("SELECT stock FROM products WHERE id = :id");
                $stmt->execute(['id' => $item['product_id']]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$product || $product['stock'] < $item['quantity']) {
                    $this->db->rollBack();
                    $response->getBody()->write(json_encode([
                        'error' => "Insufficient stock for {$item['product_name']}",
                        'product_id' => $item['product_id']
                    ]));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }

                // DESCONTAR STOCK
                $stmt = $this->db->prepare("UPDATE products SET stock = stock - :quantity WHERE id = :id");
                $stmt->execute(['quantity' => $item['quantity'], 'id' => $item['product_id']]);
            }

            // Actualizar reserva
            $stmt = $this->db->prepare("
                UPDATE reservations
                SET status = 'confirmed',
                    confirmed_at = NOW(),
                    confirmed_by = :confirmed_by,
                    admin_notes = :admin_notes
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $reservationId,
                'confirmed_by' => $adminId,
                'admin_notes' => $data['admin_notes'] ?? null
            ]);

            // Log de confirmación
            $this->logReservationAction($reservationId, 'confirmed', $adminId, 'Reserva confirmada por admin');

            $this->db->commit();

            // Enviar email de confirmación al cliente
            try {
                $this->emailService->sendReservationConfirmed($reservationId);
                $this->logReservationAction($reservationId, 'email_sent', $adminId, 'Email de confirmación enviado');
            } catch (\Exception $e) {
                error_log('Error sending confirmation email: ' . $e->getMessage());
            }

            $result = [
                'success' => true,
                'message' => 'Reservation confirmed successfully',
                'reservation_id' => $reservationId,
                'status' => 'confirmed'
            ];

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Confirm reservation error: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Rechazar reserva (desde panel admin)
     * POST /api/admin/reservations/{id}/reject
     */
    public function reject(Request $request, Response $response, array $args): Response
    {
        try {
            $this->db->beginTransaction();

            $reservationId = intval($args['id']);

            // Leer body para notas del admin
            $body = $request->getBody()->getContents();
            $data = json_decode($body, true);
            if (!$data) {
                $data = $request->getParsedBody();
            }

            // Obtener user_id del admin
            $user = $request->getAttribute('user');
            $adminId = $user->user_id ?? null;

            // Obtener reserva
            $stmt = $this->db->prepare("SELECT * FROM reservations WHERE id = :id");
            $stmt->execute(['id' => $reservationId]);
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reservation) {
                $this->db->rollBack();
                $response->getBody()->write(json_encode(['error' => 'Reservation not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            if ($reservation['status'] !== 'pending') {
                $this->db->rollBack();
                $response->getBody()->write(json_encode(['error' => 'Reservation already processed']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Actualizar reserva
            $stmt = $this->db->prepare("
                UPDATE reservations
                SET status = 'rejected',
                    confirmed_at = NOW(),
                    confirmed_by = :confirmed_by,
                    admin_notes = :admin_notes
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $reservationId,
                'confirmed_by' => $adminId,
                'admin_notes' => $data['admin_notes'] ?? 'Reserva rechazada'
            ]);

            // Log de rechazo
            $this->logReservationAction($reservationId, 'rejected', $adminId, 'Reserva rechazada por admin');

            $this->db->commit();

            $result = [
                'success' => true,
                'message' => 'Reservation rejected successfully',
                'reservation_id' => $reservationId,
                'status' => 'rejected'
            ];

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Reject reservation error: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Listar todas las reservas (admin)
     * GET /api/admin/reservations
     */
    public function getAll(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();

            $page = isset($params['page']) ? max(1, intval($params['page'])) : 1;
            $limit = isset($params['limit']) ? min(100, max(1, intval($params['limit']))) : 20;
            $offset = ($page - 1) * $limit;
            $status = $params['status'] ?? null;

            // Construir query
            $whereClause = $status ? "WHERE status = :status" : "";

            // Contar total
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM reservations $whereClause");
            if ($status) {
                $stmt->execute(['status' => $status]);
            } else {
                $stmt->execute();
            }
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Obtener reservas
            $stmt = $this->db->prepare("
                SELECT
                    r.*,
                    (SELECT COUNT(*) FROM reservation_items WHERE reservation_id = r.id) as items_count
                FROM reservations r
                $whereClause
                ORDER BY r.created_at DESC
                LIMIT :limit OFFSET :offset
            ");

            if ($status) {
                $stmt->bindValue(':status', $status, PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $data = [
                'data' => $reservations,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => intval($total),
                    'pages' => ceil($total / $limit)
                ]
            ];

            $response->getBody()->write(json_encode($data));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Obtener detalle de una reserva (admin)
     * GET /api/admin/reservations/{id}
     */
    public function getOne(Request $request, Response $response, array $args): Response
    {
        try {
            $reservationId = intval($args['id']);

            // Obtener reserva
            $stmt = $this->db->prepare("SELECT * FROM reservations WHERE id = :id");
            $stmt->execute(['id' => $reservationId]);
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reservation) {
                $response->getBody()->write(json_encode(['error' => 'Reservation not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Obtener items
            $stmt = $this->db->prepare("SELECT * FROM reservation_items WHERE reservation_id = :id");
            $stmt->execute(['id' => $reservationId]);
            $reservation['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Obtener logs
            $stmt = $this->db->prepare("
                SELECT rl.*, u.name as user_name
                FROM reservation_logs rl
                LEFT JOIN users u ON rl.user_id = u.id
                WHERE rl.reservation_id = :id
                ORDER BY rl.created_at DESC
            ");
            $stmt->execute(['id' => $reservationId]);
            $reservation['logs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode($reservation));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Generar recibo en PDF/HTML
     * GET /api/admin/reservations/{id}/receipt
     */
    public function generateReceipt(Request $request, Response $response, array $args): Response
    {
        try {
            $reservationId = intval($args['id']);
            $params = $request->getQueryParams();
            $format = $params['format'] ?? 'pdf'; // 'pdf' o 'html'

            // Obtener reserva
            $stmt = $this->db->prepare("SELECT * FROM reservations WHERE id = :id");
            $stmt->execute(['id' => $reservationId]);
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reservation) {
                $response->getBody()->write(json_encode(['error' => 'Reservation not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Obtener items
            $stmt = $this->db->prepare("SELECT * FROM reservation_items WHERE reservation_id = :id");
            $stmt->execute(['id' => $reservationId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Generar HTML del recibo
            $html = $this->generateReceiptHTML($reservation, $items);

            if ($format === 'html') {
                $response->getBody()->write($html);
                return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
            }

            // Para PDF, retornar HTML con headers para impresión
            $response->getBody()->write($html);
            return $response
                ->withHeader('Content-Type', 'text/html; charset=utf-8')
                ->withHeader('Content-Disposition', 'inline; filename="recibo-' . $reservation['reservation_number'] . '.html"');

        } catch (\Exception $e) {
            error_log('Generate receipt error: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Generar HTML del recibo
     */
    private function generateReceiptHTML($reservation, $items): string
    {
        $date = date('d/m/Y H:i', strtotime($reservation['confirmed_at'] ?? $reservation['created_at']));
        $statusText = [
            'pending' => 'PENDIENTE',
            'confirmed' => 'CONFIRMADA',
            'rejected' => 'RECHAZADA',
            'expired' => 'EXPIRADA'
        ];
        $status = $statusText[$reservation['status']] ?? strtoupper($reservation['status']);

        $html = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo - ' . htmlspecialchars($reservation['reservation_number']) . '</title>
    <style>
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            padding: 40px;
            background: #f5f5f5;
        }
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #2d3c5d;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #2d3c5d;
            font-size: 28px;
            margin-bottom: 5px;
        }
        .header p {
            color: #666;
            font-size: 14px;
        }
        .receipt-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        .info-section h3 {
            color: #2d3c5d;
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .info-section p {
            color: #333;
            font-size: 13px;
            line-height: 1.6;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-top: 5px;
        }
        .status-confirmed {
            background: #dcfce7;
            color: #166534;
        }
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
        }
        thead {
            background: #2d3c5d;
            color: white;
        }
        th {
            padding: 12px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 13px;
            color: #333;
        }
        tbody tr:hover {
            background: #f9fafb;
        }
        .text-right {
            text-align: right;
        }
        .totals {
            margin-top: 20px;
            border-top: 2px solid #2d3c5d;
            padding-top: 15px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .total-row.grand-total {
            font-size: 18px;
            font-weight: bold;
            color: #2d3c5d;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }
        .notes {
            margin-top: 30px;
            padding: 15px;
            background: #f9fafb;
            border-left: 3px solid #eddacb;
        }
        .notes h4 {
            color: #2d3c5d;
            font-size: 14px;
            margin-bottom: 8px;
        }
        .notes p {
            color: #555;
            font-size: 13px;
            line-height: 1.6;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
        .print-button {
            display: block;
            margin: 20px auto;
            padding: 12px 30px;
            background: #2d3c5d;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        .print-button:hover {
            background: #1e2a3d;
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="header">
            <h1>RECIBO DE RESERVA</h1>
            <p>Comprobante de reserva confirmada</p>
        </div>

        <div class="receipt-info">
            <div class="info-section">
                <h3>Información de la Reserva</h3>
                <p><strong>Número:</strong> ' . htmlspecialchars($reservation['reservation_number']) . '</p>
                <p><strong>Fecha:</strong> ' . $date . '</p>
                <p><strong>Estado:</strong> <span class="status-badge status-' . $reservation['status'] . '">' . $status . '</span></p>
            </div>
            <div class="info-section">
                <h3>Información del Cliente</h3>
                <p><strong>Nombre:</strong> ' . htmlspecialchars($reservation['customer_name']) . '</p>
                <p><strong>Email:</strong> ' . htmlspecialchars($reservation['customer_email']) . '</p>
                <p><strong>Teléfono:</strong> ' . htmlspecialchars($reservation['customer_phone']) . '</p>
            </div>
        </div>';

        // Dirección de envío si existe
        if ($reservation['shipping_address']) {
            $html .= '
        <div class="info-section" style="margin-bottom: 20px;">
            <h3>Dirección de Entrega</h3>
            <p>' . htmlspecialchars($reservation['shipping_address']) . '</p>';
            if ($reservation['shipping_city']) {
                $html .= '<p>' . htmlspecialchars($reservation['shipping_city']);
                if ($reservation['shipping_state']) {
                    $html .= ', ' . htmlspecialchars($reservation['shipping_state']);
                }
                if ($reservation['shipping_zip_code']) {
                    $html .= ' - ' . htmlspecialchars($reservation['shipping_zip_code']);
                }
                $html .= '</p>';
            }
            $html .= '</div>';
        }

        // Tabla de productos
        $html .= '
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>SKU</th>
                    <th class="text-right">Cantidad</th>
                    <th class="text-right">Precio Unit.</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($items as $item) {
            $html .= '
                <tr>
                    <td>' . htmlspecialchars($item['product_name']) . '</td>
                    <td>' . htmlspecialchars($item['product_sku']) . '</td>
                    <td class="text-right">' . intval($item['quantity']) . '</td>
                    <td class="text-right">$' . number_format($item['price'], 2, ',', '.') . '</td>
                    <td class="text-right">$' . number_format($item['total'], 2, ',', '.') . '</td>
                </tr>';
        }

        $html .= '
            </tbody>
        </table>

        <div class="totals">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>$' . number_format($reservation['subtotal'], 2, ',', '.') . '</span>
            </div>';

        if ($reservation['tax_amount'] > 0) {
            $html .= '
            <div class="total-row">
                <span>Impuestos:</span>
                <span>$' . number_format($reservation['tax_amount'], 2, ',', '.') . '</span>
            </div>';
        }

        if ($reservation['shipping_amount'] > 0) {
            $html .= '
            <div class="total-row">
                <span>Envío:</span>
                <span>$' . number_format($reservation['shipping_amount'], 2, ',', '.') . '</span>
            </div>';
        }

        if ($reservation['discount_amount'] > 0) {
            $html .= '
            <div class="total-row">
                <span>Descuento:</span>
                <span>-$' . number_format($reservation['discount_amount'], 2, ',', '.') . '</span>
            </div>';
        }

        $html .= '
            <div class="total-row grand-total">
                <span>TOTAL:</span>
                <span>$' . number_format($reservation['total_amount'], 2, ',', '.') . '</span>
            </div>
        </div>';

        // Notas del cliente
        if ($reservation['notes']) {
            $html .= '
        <div class="notes">
            <h4>Comentarios del Cliente</h4>
            <p>' . nl2br(htmlspecialchars($reservation['notes'])) . '</p>
        </div>';
        }

        // Notas del admin
        if ($reservation['admin_notes']) {
            $html .= '
        <div class="notes">
            <h4>Instrucciones de Pago y Entrega</h4>
            <p>' . nl2br(htmlspecialchars($reservation['admin_notes'])) . '</p>
        </div>';
        }

        $html .= '
        <div class="footer">
            <p>Este es un comprobante de reserva confirmada.</p>
            <p>Guarde este recibo para su referencia.</p>
            <p>Generado el ' . date('d/m/Y H:i') . '</p>
        </div>
    </div>

    <button class="print-button no-print" onclick="window.print()">Imprimir Recibo</button>
</body>
</html>';

        return $html;
    }

    /**
     * Log de acciones en reserva
     */
    private function logReservationAction($reservationId, $action, $userId = null, $details = null)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO reservation_logs (reservation_id, action, user_id, details)
                VALUES (:reservation_id, :action, :user_id, :details)
            ");
            $stmt->execute([
                'reservation_id' => $reservationId,
                'action' => $action,
                'user_id' => $userId,
                'details' => $details
            ]);
        } catch (\Exception $e) {
            error_log('Error logging reservation action: ' . $e->getMessage());
        }
    }
}
