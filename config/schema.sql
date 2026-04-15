-- ============================================
-- Esquema de Base de datos MySQL - Control de Negocio
-- Todas las tablas se crean vacías
-- ============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- Tabla: settings (Configuración)
-- ============================================
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` INT PRIMARY KEY DEFAULT 1,
  `default_margin_percent` DECIMAL(10,2) DEFAULT 30.00,
  `initial_investment` DECIMAL(15,2) DEFAULT 0.00,
  CHECK (`id` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`id`, `default_margin_percent`, `initial_investment`) 
VALUES (1, 30.00, 0.00);

-- ============================================
-- Tabla: modules (Módulos del sistema)
-- ============================================
DROP TABLE IF EXISTS `modules`;
CREATE TABLE `modules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `icon` VARCHAR(50) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabla: roles (Roles de usuario)
-- ============================================
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `is_admin` TINYINT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`id`, `name`, `is_admin`) VALUES 
(1, 'Administrador', 1),
(2, 'Gerente', 0),
(3, 'Vendedor', 0);

-- ============================================
-- Tabla: users (Usuarios)
-- ============================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `salt` VARCHAR(255) NOT NULL,
  `role_id` INT DEFAULT 2,
  `is_active` TINYINT DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabla: user_sessions (Sesiones de usuario)
-- ============================================
DROP TABLE IF EXISTS `user_sessions`;
CREATE TABLE `user_sessions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `token` VARCHAR(255) NOT NULL UNIQUE,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_token` (`token`),
  INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabla: clients (Clientes)
-- ============================================
DROP TABLE IF EXISTS `clients`;
CREATE TABLE `clients` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(200) NOT NULL,
  `phone` VARCHAR(50) DEFAULT '',
  `address` VARCHAR(500) DEFAULT '',
  `city` VARCHAR(100) DEFAULT '',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabla: products (Productos)
-- ============================================
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(100) NOT NULL UNIQUE,
  `name` VARCHAR(200) NOT NULL,
  `category` VARCHAR(100) DEFAULT '',
  `description` TEXT,
  `features` TEXT,
  `stock` INT NOT NULL DEFAULT 0,
  `status` VARCHAR(50) NOT NULL DEFAULT 'Disponible',
  `entry_date` VARCHAR(50) NOT NULL,
  `supplier` VARCHAR(200) DEFAULT '',
  `photo_path` VARCHAR(500) DEFAULT NULL,
  `purchase_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `extra_costs` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_real_cost` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `margin_percent` DECIMAL(10,2) NOT NULL DEFAULT 30.00,
  `sale_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_code` (`code`),
  INDEX `idx_category` (`category`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabla: purchases (Compras)
-- ============================================
DROP TABLE IF EXISTS `purchases`;
CREATE TABLE `purchases` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `quantity` INT NOT NULL,
  `purchase_price` DECIMAL(15,2) NOT NULL,
  `supplier` VARCHAR(200) DEFAULT '',
  `shipping_cost` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `purchase_date` VARCHAR(50) NOT NULL,
  `total_invested` DECIMAL(15,2) NOT NULL,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  INDEX `idx_product_id` (`product_id`),
  INDEX `idx_purchase_date` (`purchase_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabla: sales (Ventas)
-- ============================================
DROP TABLE IF EXISTS `sales`;
CREATE TABLE `sales` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sale_date` VARCHAR(50) NOT NULL,
  `product_id` INT NOT NULL,
  `quantity` INT NOT NULL,
  `sale_price` DECIMAL(15,2) NOT NULL,
  `client_id` INT DEFAULT NULL,
  `payment_method` VARCHAR(50) NOT NULL,
  `includes_shipping` TINYINT NOT NULL DEFAULT 0,
  `shipping_value` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(15,2) NOT NULL,
  `total_cost` DECIMAL(15,2) NOT NULL,
  `profit` DECIMAL(15,2) NOT NULL,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE SET NULL,
  INDEX `idx_sale_date` (`sale_date`),
  INDEX `idx_product_id` (`product_id`),
  INDEX `idx_client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabla: shipments (Envíos)
-- ============================================
DROP TABLE IF EXISTS `shipments`;
CREATE TABLE `shipments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sale_id` INT DEFAULT NULL,
  `client_name` VARCHAR(200) NOT NULL,
  `client_address` VARCHAR(500) NOT NULL,
  `city` VARCHAR(100) NOT NULL,
  `shipping_value` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `transport_company` VARCHAR(200) DEFAULT '',
  `status` VARCHAR(50) NOT NULL DEFAULT 'Pendiente',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE SET NULL,
  INDEX `idx_sale_id` (`sale_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabla: cash_movements (Movimientos de caja)
-- ============================================
DROP TABLE IF EXISTS `cash_movements`;
CREATE TABLE `cash_movements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `movement_date` VARCHAR(50) NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `notes` TEXT,
  CHECK (`type` IN ('Ingreso', 'Egreso')),
  INDEX `idx_movement_date` (`movement_date`),
  INDEX `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- Fin del esquema
-- ============================================
