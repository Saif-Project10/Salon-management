<?php
require_once 'includes/db.php';

try {
    // 1. Create Base Users Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `name` VARCHAR(100) NOT NULL,
      `username` VARCHAR(100) DEFAULT NULL,
      `email` VARCHAR(100) NOT NULL UNIQUE,
      `phone` VARCHAR(20),
      `password` VARCHAR(255) NOT NULL,
      `role` ENUM('admin', 'receptionist', 'stylist', 'client') DEFAULT 'client',
      `avatar` VARCHAR(255) DEFAULT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Create Services Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `services` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `name` VARCHAR(100) NOT NULL,
      `description` TEXT,
      `duration` INT NOT NULL,
      `price` DECIMAL(10,2) NOT NULL,
      `category` VARCHAR(100) DEFAULT 'Signature Care',
      `featured_image` VARCHAR(255) DEFAULT NULL
    )");

    // 3. Create Clients Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `clients` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `user_id` INT DEFAULT NULL,
      `name` VARCHAR(100) NOT NULL,
      `phone` VARCHAR(20),
      `email` VARCHAR(100),
      `preferences` TEXT,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
    )");

    // 4. Create Staff Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `staff` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `user_id` INT NOT NULL,
      `commission_rate` DECIMAL(5,2) DEFAULT 0.00,
      `services` TEXT,
      `specialization` VARCHAR(150) DEFAULT NULL,
      `experience_years` INT DEFAULT 0,
      `bio` TEXT DEFAULT NULL,
      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    )");

    // 5. Create Appointments Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `appointments` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `client_id` INT NOT NULL,
      `service_id` INT NOT NULL,
      `stylist_id` INT NOT NULL,
      `appointment_date` DATE NOT NULL,
      `appointment_time` TIME NOT NULL,
      `status` ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
      `notes` TEXT,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE,
      FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE CASCADE,
      FOREIGN KEY (`stylist_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    )");

    // 6. Insert Default Admin
    $password = password_hash('password', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (name, username, email, password, role) VALUES ('System Admin', 'admin', 'admin@elegance.local', ?, 'admin')");
    $stmt->execute([$password]);

    echo "<h2 style='color:green;'>Success!</h2>";
    echo "<p>The database has been set up on this PC. You can now log in with the admin email.</p>";
    echo "<a href='login.php'>Go to Login</a>";
    
    // Trigger the helper sync to add extra columns/tables
    salonSyncSchema($pdo);

} catch (PDOException $e) {
    echo "<h2 style='color:red;'>Error!</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>