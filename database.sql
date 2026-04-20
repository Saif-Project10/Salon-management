CREATE DATABASE IF NOT EXISTS `elegance_salon`;
USE `elegance_salon`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(100) DEFAULT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `phone` VARCHAR(20),
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'receptionist', 'stylist', 'client') DEFAULT 'client',
  `avatar` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `services` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `duration` INT NOT NULL COMMENT 'duration in minutes',
  `price` DECIMAL(10,2) NOT NULL,
  `category` VARCHAR(100) DEFAULT 'Signature Care',
  `featured_image` VARCHAR(255) DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS `clients` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20),
  `email` VARCHAR(100),
  `preferences` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS `staff` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `commission_rate` DECIMAL(5,2) DEFAULT 0.00,
  `services` TEXT COMMENT 'comma separated service ids',
  `specialization` VARCHAR(150) DEFAULT NULL,
  `experience_years` INT DEFAULT 0,
  `bio` TEXT DEFAULT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `inventory` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_name` VARCHAR(100) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 0,
  `price` DECIMAL(10,2) NOT NULL,
  `supplier` VARCHAR(100),
  `min_stock` INT DEFAULT 5
);

CREATE TABLE IF NOT EXISTS `appointments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT NOT NULL,
  `service_id` INT NOT NULL,
  `stylist_id` INT NOT NULL,
  `appointment_date` DATE NOT NULL,
  `appointment_time` TIME NOT NULL,
  `status` ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
  `notes` TEXT DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `inventory_deducted_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`stylist_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `appointment_id` INT NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_method` VARCHAR(50),
  `payment_status` ENUM('pending', 'completed') DEFAULT 'completed',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `feedback` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `message` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `reviews` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT DEFAULT NULL,
  `rating` TINYINT NOT NULL DEFAULT 5,
  `review_text` TEXT NOT NULL,
  `status` ENUM('published', 'hidden') DEFAULT 'published',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS `commissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `appointment_id` INT NOT NULL,
  `stylist_id` INT NOT NULL,
  `service_amount` DECIMAL(10,2) NOT NULL,
  `commission_rate` DECIMAL(5,2) NOT NULL,
  `commission_amount` DECIMAL(10,2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_commission_appointment` (`appointment_id`),
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`stylist_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `notification_type` VARCHAR(50) DEFAULT 'info',
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `staff_availability` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `work_day` TINYINT NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `is_available` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_staff_day` (`user_id`, `work_day`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `staff_tasks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `assigned_to` INT NOT NULL,
  `assigned_by` INT DEFAULT NULL,
  `title` VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `due_date` DATE DEFAULT NULL,
  `priority` ENUM('low', 'medium', 'high') DEFAULT 'medium',
  `status` ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `supplier` VARCHAR(100) NOT NULL,
  `created_by` INT DEFAULT NULL,
  `status` ENUM('draft', 'ordered', 'received') DEFAULT 'draft',
  `total_amount` DECIMAL(10,2) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS `purchase_order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `purchase_order_id` INT NOT NULL,
  `inventory_id` INT NOT NULL,
  `product_name` VARCHAR(100) NOT NULL,
  `quantity_needed` INT NOT NULL,
  `unit_cost` DECIMAL(10,2) NOT NULL,
  `line_total` DECIMAL(10,2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`inventory_id`) REFERENCES `inventory`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `service_inventory` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `service_id` INT NOT NULL,
  `inventory_id` INT NOT NULL,
  `quantity_used` DECIMAL(10,2) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_service_inventory` (`service_id`, `inventory_id`),
  FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`inventory_id`) REFERENCES `inventory`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT DEFAULT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `sms_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `appointment_id` INT DEFAULT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `message` TEXT NOT NULL,
  `context` VARCHAR(100) DEFAULT 'general',
  `status` VARCHAR(30) DEFAULT 'queued',
  `provider_response` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS `user_calendar_tokens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `access_token` TEXT NOT NULL,
  `refresh_token` TEXT DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `calendar_id` VARCHAR(255) DEFAULT 'primary',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_user_calendar_token` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `appointment_calendar_syncs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `appointment_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `google_event_id` VARCHAR(255) NOT NULL,
  `last_action` ENUM('upserted', 'cancelled') DEFAULT 'upserted',
  `synced_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_appointment_calendar_sync` (`appointment_id`, `user_id`),
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `reminders_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `appointment_id` INT NOT NULL,
  `reminder_type` VARCHAR(50) NOT NULL,
  `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `status` VARCHAR(30) DEFAULT 'sent',
  `method` VARCHAR(30) DEFAULT 'system',
  UNIQUE KEY `uniq_reminder_log` (`appointment_id`, `reminder_type`, `method`),
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `inventory_deductions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `appointment_id` INT NOT NULL,
  `inventory_id` INT NOT NULL,
  `quantity_used` DECIMAL(10,2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_inventory_deduction` (`appointment_id`, `inventory_id`),
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`inventory_id`) REFERENCES `inventory`(`id`) ON DELETE CASCADE
);

INSERT INTO `users` (`name`, `username`, `email`, `phone`, `password`, `role`)
SELECT * FROM (
  SELECT 
    'System Admin' AS col1, 
    'admin' AS col2, 
    'admin@elegance.local' AS col3, 
    '1234567890' AS col4, 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' AS col5, 
    'admin' AS col6
) AS tmp
WHERE NOT EXISTS (
  SELECT 1 FROM `users` WHERE `email` = 'admin@elegance.local'
) LIMIT 1;

INSERT INTO `services` (`name`, `description`, `duration`, `price`, `category`, `featured_image`)
SELECT * FROM (
  SELECT 'Signature Haircut', 'Precision haircut with shaping, styling, and a polished finish.', 45, 50.00, 'Hair', 'signature_haircut.jpeg' UNION ALL
  SELECT 'Classic Hair Styling', 'Classic blow-dry and styling service for a neat salon-ready look.', 45, 55.00, 'Hair', 'classic_hair_styling.webp' UNION ALL
  SELECT 'Fashion Hair Styling', 'Trendy styling session designed for events and modern finishes.', 50, 60.00, 'Hair', 'hair-style.png' UNION ALL
  SELECT 'Hair Color + Highlights', 'Custom color application with dimensional highlights and shine.', 90, 120.00, 'Hair', 'hair_color.avif' UNION ALL
  SELECT 'Hair Spa Treatment', 'Deep nourishment therapy that refreshes the scalp and smooths the hair.', 60, 70.00, 'Hair', 'hair_spa.avif' UNION ALL
  SELECT 'Keratin Smoothing', 'Frizz-control treatment for smoother, softer, and manageable hair.', 120, 150.00, 'Hair', 'keratin_smothing.avif' UNION ALL
  SELECT 'Hair Nourish Ritual', 'Luxury nourishment treatment focused on softness, hydration, and finish.', 55, 65.00, 'Hair', 'hair_nourish_Ritual.avif' UNION ALL

  SELECT 'Luxury Facial', 'Deep cleansing facial treatment for freshness, glow, and relaxation.', 60, 80.00, 'Skin', 'facial.jpg' UNION ALL
  SELECT 'Signature Facial Glow', 'Glow-enhancing facial ritual for brighter, refreshed skin.', 60, 85.00, 'Skin', 'signature_facial_glow.jpeg' UNION ALL
  SELECT 'Brightening Facial', 'Complexion-brightening facial designed for radiance and even tone.', 60, 90.00, 'Skin', 'brightining_facial.avif' UNION ALL
  SELECT 'Acne Control Facial', 'Targeted facial treatment to calm congestion and support clear skin.', 50, 70.00, 'Skin', 'acne_control_facial.avif' UNION ALL
  SELECT 'Anti-aging Treatment', 'Firming and smoothing therapy for mature or tired-looking skin.', 75, 110.00, 'Skin', 'anti_aging_treatment.avif' UNION ALL
  SELECT 'Skin Renewal Ritual', 'Relaxing skin ritual focused on hydration, texture, and balance.', 55, 78.00, 'Skin', 'skin_renewal_ritual.jpeg' UNION ALL

  SELECT 'Bridal Makeup', 'Complete bridal makeup package with elegant long-wear finishing.', 120, 200.00, 'Bridal', 'bridal.png' UNION ALL
  SELECT 'Engagement Makeup', 'Refined engagement-event makeup with camera-ready finishing.', 90, 150.00, 'Bridal', 'engagement_makeup.jpeg' UNION ALL
  SELECT 'Party Makeup', 'Soft glam and evening makeup for parties and celebrations.', 75, 100.00, 'Bridal', 'party_makeup.jpeg' UNION ALL
  SELECT 'Airbrush Makeup', 'High-definition airbrush application for flawless event coverage.', 120, 250.00, 'Bridal', 'airbrush_makeup.jpeg' UNION ALL
  SELECT 'Bridal Trial Look', 'Preview session to finalize bridal beauty direction before the event.', 75, 95.00, 'Bridal', 'bridial_trial_look.jpeg' UNION ALL
  SELECT 'Reception Glam Makeup', 'Elegant glam makeup designed for receptions and formal occasions.', 95, 170.00, 'Bridal', 'glam_makeup.jpeg' UNION ALL

  SELECT 'Classic Manicure', 'Essential manicure care with shaping, cuticle work, and finish.', 30, 30.00, 'Nails', 'classic_manicure.jpeg' UNION ALL
  SELECT 'Essential Manicure', 'Clean and simple manicure service for everyday polished nails.', 30, 28.00, 'Nails', 'manicure.jpg' UNION ALL
  SELECT 'Premium Manicure', 'Enhanced manicure service with extra care and refined finishing.', 40, 38.00, 'Nails', 'Manicure.png' UNION ALL
  SELECT 'Classic Pedicure', 'Classic foot care with soaking, grooming, and fresh finishing.', 40, 40.00, 'Nails', 'pedicure.jpg' UNION ALL
  SELECT 'Spa Pedicure', 'Relaxing pedicure experience with extra pampering and comfort.', 50, 48.00, 'Nails', 'spa_padicure.jpeg' UNION ALL
  SELECT 'Gel Nails', 'Durable gel nail application with a sleek salon-quality finish.', 45, 50.00, 'Nails', 'jel_nails.jpeg' UNION ALL
  SELECT 'Nail Art', 'Creative nail art detailing for a more expressive finished look.', 30, 25.00, 'Nails', 'nail_art.jpeg' UNION ALL
  SELECT 'Nail Care Ritual', 'Nail conditioning and finishing ritual for healthy-looking hands and feet.', 35, 32.00, 'Nails', 'nail_ritual.jpeg'
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `services` LIMIT 1);
-- MODIFIED
INSERT INTO `users` (`name`, `username`, `email`, `phone`, `password`, `role`, `avatar`)
SELECT * FROM (
  SELECT 'Aisha Khan' AS name, 'aisha' AS username, 'aisha@elegance.local' AS email, '1112223333' AS phone, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' AS password, 'stylist' AS role, '/salon-management/assets/images/aisha_stylist.png' AS avatar UNION ALL
  SELECT 'Ali Raza' AS name, 'ali' AS username, 'ali@elegance.local' AS email, '2223334444' AS phone, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' AS password, 'stylist' AS role, '/salon-management/assets/images/ali_stylist.jpg' AS avatar UNION ALL
  SELECT 'Fatima Ahmed' AS name, 'fatima' AS username, 'fatima@elegance.local' AS email, '3334445555' AS phone, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' AS password, 'stylist' AS role, '/salon-management/assets/images/fatima_stylist.png' AS avatar UNION ALL
  SELECT 'Zara Malik' AS name, 'zara' AS username, 'zara@elegance.local' AS email, '4445556666' AS phone, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' AS password, 'stylist' AS role, '/salon-management/assets/images/zara_stylist.jpg' AS avatar
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `role` = 'stylist') LIMIT 4;

INSERT INTO `staff` (`user_id`, `specialization`, `experience_years`, `bio`, `services`)
SELECT u.id, tmp.specialization, tmp.exp, tmp.bio, tmp.services
FROM `users` u
JOIN (
  SELECT 'aisha@elegance.local' AS email, 'Bridal Makeup Artist' AS specialization, 8 AS exp, 'Expert in bridal makeups, soft glam and creating flawless looks for any occasion.' AS bio, '14,15,16,17,18,19' AS services UNION ALL
  SELECT 'ali@elegance.local' AS email, 'Master Hair Stylist' AS specialization, 10 AS exp, 'Specializes in precision cuts, coloring, and transformative styling.' AS bio, '1,2,3,4,5,6,7' AS services UNION ALL
  SELECT 'fatima@elegance.local' AS email, 'Skin Care Specialist' AS specialization, 6 AS exp, 'Provides luxury facials, anti-aging treatments and personalized skin care advice.' AS bio, '8,9,10,11,12,13' AS services UNION ALL
  SELECT 'zara@elegance.local' AS email, 'Nail Art Technician' AS specialization, 5 AS exp, 'Creative nail art, gel applications and relaxing spa pedicures.' AS bio, '20,21,22,23,24,25,26,27' AS services
) AS tmp ON u.email = tmp.email
WHERE NOT EXISTS (SELECT 1 FROM `staff` s WHERE s.user_id = u.id);
-- Update avatar paths to .jpg for stylists to fix image display issues
UPDATE users SET avatar = REPLACE(avatar, '.png', '.jpg') WHERE role = 'stylist' AND avatar LIKE '%stylist.png';

-- ==================================================
-- SEED INVENTORY PRODUCTS (Beauty Co.)
-- ==================================================
INSERT IGNORE INTO `inventory` (`product_name`, `quantity`, `price`, `supplier`, `min_stock`) VALUES
-- Hair
('L''Oreal Shampoo (ml)', 5000, 0.05, 'Beauty Co.', 500),
('L''Oreal Conditioner (ml)', 3000, 0.05, 'Beauty Co.', 300),
('Keratin Solution (ml)', 1000, 0.20, 'Beauty Co.', 100),
('Hair Color Brown (pcs)', 50, 8.00, 'Beauty Co.', 5),
('Hair Color Black (pcs)', 40, 8.00, 'Beauty Co.', 5),
('Hair Spa Cream (gm)', 2000, 0.04, 'Beauty Co.', 200),

-- Skin
('Facial Cleanser (ml)', 2000, 0.08, 'Beauty Co.', 200),
('Massage Cream (gm)', 1500, 0.06, 'Beauty Co.', 150),
('Face Mask Sheet (pcs)', 100, 1.50, 'Beauty Co.', 10),
('Anti-Aging Serum (ml)', 500, 0.60, 'Beauty Co.', 50),

-- Bridal
('MAC Foundation (bottles)', 20, 15.00, 'Beauty Co.', 2),
('Setting Spray (bottles)', 15, 10.00, 'Beauty Co.', 2),
('False Lashes (pairs)', 50, 3.00, 'Beauty Co.', 5),
('Airbrush Fluid (ml)', 800, 0.30, 'Beauty Co.', 100),

-- Nails
('Nail Polish Remover (ml)', 1000, 0.04, 'Beauty Co.', 100),
('Cuticle Oil (ml)', 500, 0.12, 'Beauty Co.', 50),
('Gel Polish Red (pcs)', 30, 6.50, 'Beauty Co.', 3),
('Gel Polish Pink (pcs)', 25, 6.50, 'Beauty Co.', 3),
('Nail Art Stickers (sheets)', 40, 2.00, 'Beauty Co.', 5);

-- End of Inventory Seed
