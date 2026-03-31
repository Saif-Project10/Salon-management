CREATE DATABASE IF NOT EXISTS `elegance_salon`;
USE `elegance_salon`;

CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `phone` VARCHAR(20),
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'receptionist', 'stylist', 'client') DEFAULT 'client',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `services` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `duration` INT NOT NULL COMMENT 'duration in minutes',
  `price` DECIMAL(10,2) NOT NULL
);

CREATE TABLE `clients` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20),
  `email` VARCHAR(100),
  `preferences` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

CREATE TABLE `staff` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `commission_rate` DECIMAL(5,2) DEFAULT 0.00,
  `services` TEXT COMMENT 'comma separated service ids',
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE `inventory` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_name` VARCHAR(100) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 0,
  `price` DECIMAL(10,2) NOT NULL,
  `supplier` VARCHAR(100),
  `min_stock` INT DEFAULT 5
);

CREATE TABLE `appointments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT NOT NULL,
  `service_id` INT NOT NULL,
  `stylist_id` INT NOT NULL,
  `appointment_date` DATE NOT NULL,
  `appointment_time` TIME NOT NULL,
  `status` ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`stylist_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `appointment_id` INT NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_method` VARCHAR(50),
  `payment_status` ENUM('pending', 'completed') DEFAULT 'completed',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE CASCADE
);

CREATE TABLE `feedback` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `message` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default Admin user (Password: admin123)
-- bcrypt hash for 'admin123' is $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
INSERT INTO `users` (`name`, `email`, `phone`, `password`, `role`) VALUES
('System Admin', 'admin@elegance.local', '1234567890', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Add some dummy services
INSERT INTO `services` (`name`, `description`, `duration`, `price`) VALUES
('Signature Haircut', 'Premium haircut by expert stylist', 45, 50.00),
('Luxury Facial', 'Deep cleansing and relaxing facial', 60, 80.00),
('Bridal Makeup', 'Complete bridal makeup package', 120, 200.00);
