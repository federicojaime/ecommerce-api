<?php

namespace App\Services;

use App\Models\Database;
use PDO;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private $db;
    private $mailer;

    public function __construct(Database $database)
    {
        $this->db = $database->getConnection();
        $this->mailer = new PHPMailer(true);
        $this->configureMailer();
    }

    private function configureMailer()
    {
        // Configuración SMTP
        $this->mailer->isSMTP();
        $this->mailer->Host = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $_ENV['SMTP_USER'] ?? '';
        $this->mailer->Password = $_ENV['SMTP_PASS'] ?? '';
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = $_ENV['SMTP_PORT'] ?? 587;
        $this->mailer->CharSet = 'UTF-8';

        // Remitente por defecto
        $this->mailer->setFrom(
            $_ENV['MAIL_FROM'] ?? 'noreply@decohomesinrival.com.ar',
            $_ENV['MAIL_FROM_NAME'] ?? 'DecoHomes Sin Rival'
        );
    }

    /**
     * Enviar email de reserva creada al cliente
     */
    public function sendReservationCreated($reservationId)
    {
        $reservation = $this->getReservationData($reservationId);

        if (!$reservation) {
            throw new \Exception('Reservation not found');
        }

        $template = $this->getTemplate('reservation_created');
        $subject = $this->replaceVariables($template['subject'], $reservation);
        $body = $this->replaceVariables($template['body'], $reservation);

        return $this->send($reservation['customer_email'], $subject, $body);
    }

    /**
     * Enviar email de reserva confirmada al cliente
     */
    public function sendReservationConfirmed($reservationId)
    {
        $reservation = $this->getReservationData($reservationId);

        if (!$reservation) {
            throw new \Exception('Reservation not found');
        }

        $template = $this->getTemplate('reservation_confirmed');
        $subject = $this->replaceVariables($template['subject'], $reservation);
        $body = $this->replaceVariables($template['body'], $reservation);

        // Generar recibo HTML
        $receiptHtml = $this->generateReceiptHTML($reservation);
        $receiptFilename = 'recibo-' . $reservation['reservation_number'] . '.html';

        return $this->send($reservation['customer_email'], $subject, $body, $receiptHtml, $receiptFilename);
    }

    /**
     * Generar HTML del recibo para adjuntar
     */
    private function generateReceiptHTML($reservation): string
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

        foreach ($reservation['items'] as $item) {
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
        if ($reservation['notes'] && $reservation['notes'] !== 'Sin notas') {
            $html .= '
        <div class="notes">
            <h4>Comentarios del Cliente</h4>
            <p>' . nl2br(htmlspecialchars($reservation['notes'])) . '</p>
        </div>';
        }

        // Notas del admin
        if ($reservation['admin_notes'] && $reservation['admin_notes'] !== 'Nos comunicaremos contigo pronto.') {
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
     * Enviar email de nueva reserva al admin (y copias)
     */
    public function sendAdminNewReservation($reservationId)
    {
        $reservation = $this->getReservationData($reservationId);

        if (!$reservation) {
            throw new \Exception('Reservation not found');
        }

        // Agregar URL del panel de admin
        $reservation['admin_url'] = ($_ENV['ADMIN_URL'] ?? 'https://decohomesinrival.com.ar/admin') . '/reservations/' . $reservationId;

        $template = $this->getTemplate('admin_new_reservation');
        $subject = $this->replaceVariables($template['subject'], $reservation);
        $body = $this->replaceVariables($template['body'], $reservation);

        // Emails del admin - principal y copias
        $adminEmails = [
            $_ENV['ADMIN_EMAIL'] ?? 'info@decohomesinrival.com.ar',
            'federiconjg@gmail.com',
            'Franconico25@gmail.com'
        ];

        // Enviar a cada email
        $results = [];
        foreach ($adminEmails as $email) {
            try {
                $results[] = $this->send($email, $subject, $body);
                error_log("Admin notification sent to: $email");
            } catch (\Exception $e) {
                error_log("Failed to send admin notification to $email: " . $e->getMessage());
            }
        }

        // Retornar true si al menos uno fue enviado exitosamente
        return in_array(true, $results);
    }

    /**
     * Obtener datos de la reserva
     */
    private function getReservationData($reservationId)
    {
        $stmt = $this->db->prepare("SELECT * FROM reservations WHERE id = :id");
        $stmt->execute(['id' => $reservationId]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reservation) {
            return null;
        }

        // Obtener items
        $stmt = $this->db->prepare("SELECT * FROM reservation_items WHERE reservation_id = :id");
        $stmt->execute(['id' => $reservationId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Generar HTML de items
        $itemsHtml = '<table style="width: 100%; border-collapse: collapse;">';
        $itemsHtml .= '<tr style="background-color: #f2f2f2;">';
        $itemsHtml .= '<th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Producto</th>';
        $itemsHtml .= '<th style="border: 1px solid #ddd; padding: 8px; text-align: center;">Cantidad</th>';
        $itemsHtml .= '<th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Precio</th>';
        $itemsHtml .= '<th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Total</th>';
        $itemsHtml .= '</tr>';

        foreach ($items as $item) {
            $itemsHtml .= '<tr>';
            $itemsHtml .= '<td style="border: 1px solid #ddd; padding: 8px;">' . htmlspecialchars($item['product_name']) . '</td>';
            $itemsHtml .= '<td style="border: 1px solid #ddd; padding: 8px; text-align: center;">' . $item['quantity'] . '</td>';
            $itemsHtml .= '<td style="border: 1px solid #ddd; padding: 8px; text-align: right;">$' . number_format($item['price'], 2) . '</td>';
            $itemsHtml .= '<td style="border: 1px solid #ddd; padding: 8px; text-align: right;">$' . number_format($item['total'], 2) . '</td>';
            $itemsHtml .= '</tr>';
        }

        $itemsHtml .= '</table>';

        $reservation['items'] = $items;
        $reservation['items_html'] = $itemsHtml;
        $reservation['notes'] = $reservation['notes'] ?? 'Sin notas';
        $reservation['admin_notes'] = $reservation['admin_notes'] ?? 'Nos comunicaremos contigo pronto.';

        return $reservation;
    }

    /**
     * Obtener template de email
     */
    private function getTemplate($key)
    {
        $stmt = $this->db->prepare("SELECT * FROM email_templates WHERE template_key = :key AND active = TRUE");
        $stmt->execute(['key' => $key]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$template) {
            throw new \Exception("Email template not found: $key");
        }

        return $template;
    }

    /**
     * Reemplazar variables en el template
     */
    private function replaceVariables($text, $data)
    {
        foreach ($data as $key => $value) {
            if (!is_array($value)) {
                $text = str_replace('{{' . $key . '}}', $value, $text);
            }
        }
        return $text;
    }

    /**
     * Enviar email
     */
    private function send($to, $subject, $body, $attachmentContent = null, $attachmentFilename = null)
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            $this->mailer->addAddress($to);
            $this->mailer->Subject = $subject;
            $this->mailer->isHTML(true);
            $this->mailer->Body = $body;

            // Agregar adjunto si se proporciona
            if ($attachmentContent && $attachmentFilename) {
                $this->mailer->addStringAttachment($attachmentContent, $attachmentFilename, 'base64', 'text/html');
            }

            $result = $this->mailer->send();

            if ($result) {
                error_log("Email sent successfully to: $to");
            }

            return $result;

        } catch (Exception $e) {
            error_log("Email error: " . $this->mailer->ErrorInfo);
            throw $e;
        }
    }
}
