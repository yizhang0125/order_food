-- Database updates for TNG Pay functionality
-- Run these SQL commands to add the missing columns

-- Add payment_method column to payments table
ALTER TABLE payments ADD COLUMN payment_method VARCHAR(20) DEFAULT 'cash' AFTER payment_date;

-- Add tng_reference column to payments table (optional)
ALTER TABLE payments ADD COLUMN tng_reference VARCHAR(100) NULL AFTER payment_method;

-- Update existing records to have 'cash' as payment method
UPDATE payments SET payment_method = 'cash' WHERE payment_method IS NULL;

-- Add index for better performance
CREATE INDEX idx_payments_method ON payments(payment_method);
CREATE INDEX idx_payments_tng_ref ON payments(tng_reference);

-- Optional: Add comments to columns
ALTER TABLE payments MODIFY COLUMN payment_method VARCHAR(20) DEFAULT 'cash' COMMENT 'Payment method: cash, tng_pay, etc.';
ALTER TABLE payments MODIFY COLUMN tng_reference VARCHAR(100) NULL COMMENT 'TNG Pay reference number for tracking';
