-- Create categories table
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert some default categories
INSERT INTO `categories` (`name`, `description`, `status`) VALUES
('Appetizers', 'Start your meal with our delicious appetizers', 'active'),
('Main Course', 'Satisfying main dishes for every taste', 'active'),
('Desserts', 'Sweet treats to end your meal', 'active'),
('Beverages', 'Refreshing drinks and beverages', 'active');

-- Update menu_items table to add category_id
ALTER TABLE `menu_items` 
ADD COLUMN `category_id` INT,
ADD CONSTRAINT `fk_category` 
FOREIGN KEY (`category_id`) 
REFERENCES `categories`(`id`) 
ON DELETE RESTRICT 
ON UPDATE CASCADE; 