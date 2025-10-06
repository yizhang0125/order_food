-- Add cancelled_at and cancel_reason fields to orders table
ALTER TABLE orders 
ADD COLUMN cancelled_at TIMESTAMP NULL AFTER created_at,
ADD COLUMN cancel_reason TEXT NULL AFTER cancelled_at,
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER cancel_reason;

-- Create index for better performance on cancelled orders
CREATE INDEX idx_orders_cancelled_at ON orders (cancelled_at);
CREATE INDEX idx_orders_status_created ON orders (status, created_at);
ALTER TABLE orders 
ADD COLUMN cancelled_at TIMESTAMP NULL AFTER created_at,
ADD COLUMN cancel_reason TEXT NULL AFTER cancelled_at,
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER cancel_reason;

-- Create index for better performance on cancelled orders
CREATE INDEX idx_orders_cancelled_at ON orders (cancelled_at);
CREATE INDEX idx_orders_status_created ON orders (status, created_at);


