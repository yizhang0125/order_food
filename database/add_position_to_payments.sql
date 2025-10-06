-- Add position field to payments table
ALTER TABLE payments 
ADD COLUMN processed_by_position VARCHAR(100) NULL AFTER processed_by_name;

-- Add index for better performance
CREATE INDEX idx_processed_by_position ON payments(processed_by_position);
