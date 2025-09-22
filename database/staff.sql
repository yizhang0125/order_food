CREATE TABLE `staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `position` enum('manager', 'supervisor', 'waiter', 'kitchen') NOT NULL,
  `employment_type` enum('full-time', 'part-time') NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL UNIQUE,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `staff_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`staff_id`) REFERENCES `staff`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert basic permissions
INSERT INTO `permissions` (`name`, `description`) VALUES
('all', 'Full system access'),
('view_dashboard', 'Can view dashboard statistics'),
('manage_menu', 'Can manage menu items'),
('view_sales', 'Can view sales reports'),
('manage_orders', 'Can manage orders'),
('table_management_qr', 'Can manage tables and QR codes'),
('kitchen_view', 'Can access kitchen display'),
('manage_discounts', 'Can manage discounts'),
('staff_management', 'Can manage staff members');