-- Create system_settings table for tax and other settings
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default tax settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('tax_rate', '6', 'number', 'Tax rate percentage (e.g., 6 for 6%)'),
('tax_name', 'SST', 'string', 'Tax name to display (e.g., SST, VAT, GST)'),
('currency_symbol', 'RM', 'string', 'Currency symbol'),
('currency_code', 'MYR', 'string', 'Currency code'),
('restaurant_name', 'Gourmet Delights', 'string', 'Restaurant name'),
('contact_email', 'contact@restaurant.com', 'string', 'Contact email'),
('opening_time', '09:00', 'string', 'Restaurant opening time'),
('closing_time', '22:00', 'string', 'Restaurant closing time'),
('last_order_time', '21:30', 'string', 'Last order time'),
('online_ordering', '1', 'boolean', 'Enable online ordering'),
('reservations', '1', 'boolean', 'Enable table reservations'),
('order_notifications', '1', 'boolean', 'Send order notifications'),
('cash_payments', '1', 'boolean', 'Accept cash payments'),
('card_payments', '1', 'boolean', 'Accept card payments'),
('digital_payments', '0', 'boolean', 'Accept digital wallet payments')
ON DUPLICATE KEY UPDATE 
    setting_value = VALUES(setting_value),
    updated_at = CURRENT_TIMESTAMP;
