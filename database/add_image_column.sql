-- Add image_path column to menu_items table
ALTER TABLE menu_items
ADD COLUMN image_path VARCHAR(255) DEFAULT NULL; 