-- Crear tabla de reservas
CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_number VARCHAR(50) UNIQUE NOT NULL,

    -- Datos del cliente
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(50) NOT NULL,

    -- Dirección (opcional en reservas)
    shipping_address TEXT,
    shipping_city VARCHAR(100),
    shipping_state VARCHAR(100),
    shipping_zip_code VARCHAR(20),

    -- Estado de la reserva
    status ENUM('pending', 'confirmed', 'rejected', 'expired') DEFAULT 'pending',

    -- Totales
    subtotal DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    shipping_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(10, 2) NOT NULL,

    -- Notas y comentarios
    notes TEXT,
    admin_notes TEXT,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP NULL,
    confirmed_by INT NULL,

    -- Índices
    INDEX idx_status (status),
    INDEX idx_customer_email (customer_email),
    INDEX idx_created_at (created_at),

    -- Foreign key para admin que confirmó
    FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla de items de reserva
CREATE TABLE IF NOT EXISTS reservation_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_sku VARCHAR(100),
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    total DECIMAL(10, 2) NOT NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Foreign keys
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,

    -- Índices
    INDEX idx_reservation_id (reservation_id),
    INDEX idx_product_id (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla de logs de reservas (auditoría)
CREATE TABLE IF NOT EXISTS reservation_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    action VARCHAR(50) NOT NULL, -- 'created', 'confirmed', 'rejected', 'expired', 'email_sent'
    user_id INT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_reservation_id (reservation_id),
    INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla de configuración de emails
CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(100) UNIQUE NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    variables TEXT, -- JSON con variables disponibles
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar templates de email por defecto
INSERT INTO email_templates (template_key, subject, body, variables) VALUES
('reservation_created',
 'Reserva Recibida - {{reservation_number}}',
 '<h2>¡Gracias por tu reserva!</h2>
<p>Hola <strong>{{customer_name}}</strong>,</p>
<p>Hemos recibido tu reserva <strong>#{{reservation_number}}</strong> por un total de <strong>${{total_amount}}</strong>.</p>
<h3>Detalles de tu reserva:</h3>
{{items_html}}
<h3>Próximos pasos:</h3>
<p>Nuestro equipo revisará tu reserva y se comunicará contigo en las próximas <strong>24-48 horas</strong> para confirmar disponibilidad y coordinar el pago y entrega.</p>
<p>Si tienes alguna pregunta, puedes responder directamente a este email.</p>
<p>Gracias por elegirnos,<br><strong>Equipo DecoHomes Sin Rival</strong></p>',
 '{"customer_name": "Nombre del cliente", "reservation_number": "Número de reserva", "total_amount": "Monto total", "items_html": "HTML con lista de productos"}'),

('reservation_confirmed',
 'Reserva Confirmada - {{reservation_number}}',
 '<h2>¡Tu reserva ha sido confirmada!</h2>
<p>Hola <strong>{{customer_name}}</strong>,</p>
<p>Nos complace informarte que tu reserva <strong>#{{reservation_number}}</strong> ha sido <strong>confirmada</strong>.</p>
<h3>Detalles de tu compra:</h3>
{{items_html}}
<h3>Total: ${{total_amount}}</h3>
<h3>Próximos pasos:</h3>
<p>{{admin_notes}}</p>
<p>Si tienes alguna pregunta, puedes responder directamente a este email.</p>
<p>Gracias por tu compra,<br><strong>Equipo DecoHomes Sin Rival</strong></p>',
 '{"customer_name": "Nombre del cliente", "reservation_number": "Número de reserva", "total_amount": "Monto total", "items_html": "HTML con lista de productos", "admin_notes": "Notas del administrador"}'),

('admin_new_reservation',
 'Nueva Reserva Recibida - {{reservation_number}}',
 '<h2>Nueva Reserva en el Sistema</h2>
<p><strong>Número de reserva:</strong> {{reservation_number}}</p>
<p><strong>Cliente:</strong> {{customer_name}}</p>
<p><strong>Email:</strong> {{customer_email}}</p>
<p><strong>Teléfono:</strong> {{customer_phone}}</p>
<p><strong>Total:</strong> ${{total_amount}}</p>
<h3>Productos:</h3>
{{items_html}}
<h3>Notas del cliente:</h3>
<p>{{notes}}</p>
<p><a href="{{admin_url}}" style="background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 10px;">Ver en Panel de Admin</a></p>',
 '{"reservation_number": "Número de reserva", "customer_name": "Nombre", "customer_email": "Email", "customer_phone": "Teléfono", "total_amount": "Total", "items_html": "Productos", "notes": "Notas", "admin_url": "URL del panel"}');
