-- POS System Enhancements Database Schema
-- This file contains additional tables needed for advanced POS functionality

-- Add new columns to existing payments table
ALTER TABLE payments 
ADD COLUMN card_reference VARCHAR(100) NULL,
ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN discount_type ENUM('none', 'percentage', 'fixed') DEFAULT 'none',
ADD COLUMN discount_code VARCHAR(50) NULL,
ADD COLUMN split_payment_id INT NULL,
ADD COLUMN is_split_payment BOOLEAN DEFAULT FALSE;

-- Create refunds table
CREATE TABLE IF NOT EXISTS refunds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    refund_amount DECIMAL(10,2) NOT NULL,
    refund_reason TEXT NOT NULL,
    refund_method ENUM('cash', 'card', 'tng_pay', 'store_credit') DEFAULT 'cash',
    processed_by_name VARCHAR(100) NOT NULL,
    refund_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    INDEX idx_refund_date (refund_date),
    INDEX idx_processed_by (processed_by_name)
);

-- Create voided_transactions table
CREATE TABLE IF NOT EXISTS voided_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    void_reason TEXT NOT NULL,
    processed_by_name VARCHAR(100) NOT NULL,
    manager_approval BOOLEAN DEFAULT FALSE,
    manager_name VARCHAR(100) NULL,
    void_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    INDEX idx_void_date (void_date),
    INDEX idx_processed_by (processed_by_name)
);

-- Create cash_drawer_sessions table
CREATE TABLE IF NOT EXISTS cash_drawer_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cashier_name VARCHAR(100) NOT NULL,
    opening_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    closing_amount DECIMAL(10,2) NULL,
    expected_amount DECIMAL(10,2) NULL,
    discrepancy_amount DECIMAL(10,2) NULL,
    session_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    session_end TIMESTAMP NULL,
    status ENUM('open', 'closed') DEFAULT 'open',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cashier (cashier_name),
    INDEX idx_session_start (session_start),
    INDEX idx_status (status)
);

-- Create cash_drawer_transactions table
CREATE TABLE IF NOT EXISTS cash_drawer_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    transaction_type ENUM('cash_sale', 'cash_refund', 'cash_in', 'cash_out', 'float_adjustment') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT NULL,
    payment_id INT NULL,
    processed_by_name VARCHAR(100) NOT NULL,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES cash_drawer_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,
    INDEX idx_session (session_id),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_transaction_date (transaction_date)
);

-- Create discounts table
CREATE TABLE IF NOT EXISTS discounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    discount_code VARCHAR(50) UNIQUE NOT NULL,
    discount_name VARCHAR(100) NOT NULL,
    discount_type ENUM('percentage', 'fixed') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    minimum_amount DECIMAL(10,2) DEFAULT 0.00,
    maximum_discount DECIMAL(10,2) NULL,
    valid_from DATE NOT NULL,
    valid_until DATE NOT NULL,
    usage_limit INT NULL,
    used_count INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_by VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_discount_code (discount_code),
    INDEX idx_valid_dates (valid_from, valid_until),
    INDEX idx_is_active (is_active)
);

-- Create customer_loyalty table
CREATE TABLE IF NOT EXISTS customer_loyalty (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_phone VARCHAR(20) UNIQUE NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_email VARCHAR(100) NULL,
    points_balance INT DEFAULT 0,
    total_spent DECIMAL(10,2) DEFAULT 0.00,
    visit_count INT DEFAULT 0,
    last_visit TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_phone (customer_phone),
    INDEX idx_points (points_balance)
);

-- Create loyalty_transactions table
CREATE TABLE IF NOT EXISTS loyalty_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    payment_id INT NULL,
    transaction_type ENUM('earn', 'redeem', 'adjustment') NOT NULL,
    points_amount INT NOT NULL,
    description TEXT NULL,
    processed_by_name VARCHAR(100) NOT NULL,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customer_loyalty(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,
    INDEX idx_customer (customer_id),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_transaction_date (transaction_date)
);

-- Create shift_management table
CREATE TABLE IF NOT EXISTS shift_management (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cashier_name VARCHAR(100) NOT NULL,
    shift_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    shift_end TIMESTAMP NULL,
    opening_cash DECIMAL(10,2) DEFAULT 0.00,
    closing_cash DECIMAL(10,2) NULL,
    total_sales DECIMAL(10,2) DEFAULT 0.00,
    total_transactions INT DEFAULT 0,
    status ENUM('active', 'ended') DEFAULT 'active',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cashier (cashier_name),
    INDEX idx_shift_start (shift_start),
    INDEX idx_status (status)
);

-- Create receipt_templates table
CREATE TABLE IF NOT EXISTS receipt_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(100) NOT NULL,
    template_type ENUM('receipt', 'invoice', 'refund') DEFAULT 'receipt',
    header_text TEXT NULL,
    footer_text TEXT NULL,
    show_logo BOOLEAN DEFAULT TRUE,
    show_tax_breakdown BOOLEAN DEFAULT TRUE,
    show_payment_method BOOLEAN DEFAULT TRUE,
    is_default BOOLEAN DEFAULT FALSE,
    created_by VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_template_type (template_type),
    INDEX idx_is_default (is_default)
);

-- Create inventory_tracking table
CREATE TABLE IF NOT EXISTS inventory_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    menu_item_id INT NOT NULL,
    quantity_sold INT NOT NULL,
    order_id INT NOT NULL,
    payment_id INT NULL,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,
    INDEX idx_menu_item (menu_item_id),
    INDEX idx_transaction_date (transaction_date)
);

-- Insert default receipt template
INSERT INTO receipt_templates (template_name, template_type, header_text, footer_text, is_default, created_by) 
VALUES ('Default Receipt', 'receipt', 'Thank you for dining with us!', 'Please come again!', TRUE, 'System');

-- Insert default discount codes
INSERT INTO discounts (discount_code, discount_name, discount_type, discount_value, valid_from, valid_until, created_by) 
VALUES 
('WELCOME10', 'Welcome Discount', 'percentage', 10.00, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'System'),
('STUDENT5', 'Student Discount', 'percentage', 5.00, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'System'),
('SENIOR15', 'Senior Citizen Discount', 'percentage', 15.00, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'System');
