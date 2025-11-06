-- ========================================
-- TABLA DE PAGOS - MERCADO PAGO
-- ========================================

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    payment_id VARCHAR(255) UNIQUE, -- ID de Mercado Pago
    payment_method VARCHAR(50),
    payment_type VARCHAR(50), -- credit_card, debit_card, ticket, etc.
    payment_status ENUM('pending', 'approved', 'rejected', 'cancelled', 'refunded', 'in_process') DEFAULT 'pending',
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'ARS',
    external_reference VARCHAR(255),
    preference_id VARCHAR(255), -- ID de la preferencia creada
    merchant_order_id VARCHAR(255), -- ID del merchant_order
    payment_data JSON, -- Datos completos de MP para auditoría
    payer_email VARCHAR(255),
    payer_name VARCHAR(255),
    payer_identification VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_payment_id (payment_id),
    INDEX idx_order_id (order_id),
    INDEX idx_payment_status (payment_status),
    INDEX idx_external_reference (external_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- TABLA DE WEBHOOKS (para auditoría)
-- ========================================

CREATE TABLE IF NOT EXISTS mercadopago_webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50), -- payment, merchant_order
    event_action VARCHAR(50), -- payment.created, payment.updated, etc.
    data_id VARCHAR(255), -- ID del recurso notificado
    webhook_data JSON, -- Datos completos del webhook
    processed BOOLEAN DEFAULT FALSE,
    processed_at TIMESTAMP NULL,
    error_message TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_data_id (data_id),
    INDEX idx_processed (processed),
    INDEX idx_event_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
