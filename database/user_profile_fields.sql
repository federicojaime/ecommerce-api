-- ========================================
-- CAMPOS ADICIONALES PARA PERFIL DE USUARIO
-- ========================================

-- Agregar campos de perfil extendido a la tabla users
ALTER TABLE users
ADD COLUMN IF NOT EXISTS phone VARCHAR(20) AFTER email,
ADD COLUMN IF NOT EXISTS birth_date DATE AFTER phone,
ADD COLUMN IF NOT EXISTS gender ENUM('male', 'female', 'other', 'prefer_not_to_say') AFTER birth_date,
ADD COLUMN IF NOT EXISTS document_type VARCHAR(20) AFTER gender,
ADD COLUMN IF NOT EXISTS document_number VARCHAR(50) AFTER document_type,
ADD COLUMN IF NOT EXISTS avatar VARCHAR(255) AFTER document_number,
ADD COLUMN IF NOT EXISTS bio TEXT AFTER avatar,
ADD COLUMN IF NOT EXISTS preferences JSON AFTER bio,
ADD COLUMN IF NOT EXISTS newsletter_subscribed BOOLEAN DEFAULT TRUE AFTER preferences,
ADD COLUMN IF NOT EXISTS email_verified BOOLEAN DEFAULT FALSE AFTER newsletter_subscribed,
ADD COLUMN IF NOT EXISTS email_verified_at TIMESTAMP NULL AFTER email_verified,
ADD COLUMN IF NOT EXISTS last_login_at TIMESTAMP NULL AFTER email_verified_at,
ADD COLUMN IF NOT EXISTS last_login_ip VARCHAR(45) AFTER last_login_at;

-- Índices para mejorar rendimiento
CREATE INDEX IF NOT EXISTS idx_phone ON users(phone);
CREATE INDEX IF NOT EXISTS idx_document ON users(document_type, document_number);
CREATE INDEX IF NOT EXISTS idx_email_verified ON users(email_verified);

-- ========================================
-- TABLA DE PREFERENCIAS DE USUARIO
-- ========================================

CREATE TABLE IF NOT EXISTS user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    preference_key VARCHAR(100) NOT NULL,
    preference_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_preference (user_id, preference_key),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- DATOS DE EJEMPLO PARA PREFERENCIAS
-- ========================================

-- Tipos de preferencias comunes:
-- theme: light, dark
-- language: es, en
-- currency: ARS, USD
-- notifications_email: true, false
-- notifications_sms: true, false
-- product_categories_interest: JSON array

COMMIT;
