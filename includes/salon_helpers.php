<?php

$salonAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($salonAutoload)) {
    require_once $salonAutoload;
}

function salonTableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

function salonColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$column]);
    return (bool) $stmt->fetch();
}

function salonSyncSchema(PDO $pdo): void
{
    static $synced = false;
    if ($synced) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT DEFAULT NULL,
            rating TINYINT NOT NULL DEFAULT 5,
            review_text TEXT NOT NULL,
            status ENUM('published', 'hidden') DEFAULT 'published',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS commissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            appointment_id INT NOT NULL,
            stylist_id INT NOT NULL,
            service_amount DECIMAL(10,2) NOT NULL,
            commission_rate DECIMAL(5,2) NOT NULL,
            commission_amount DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_commission_appointment (appointment_id),
            FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
            FOREIGN KEY (stylist_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(150) NOT NULL,
            message TEXT NOT NULL,
            notification_type VARCHAR(50) DEFAULT 'info',
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS staff_availability (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            work_day TINYINT NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            is_available TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_staff_day (user_id, work_day),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS staff_leaves (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            leave_date DATE NOT NULL,
            reason VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_staff_leave_date (user_id, leave_date),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS staff_tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            assigned_to INT NOT NULL,
            assigned_by INT DEFAULT NULL,
            title VARCHAR(150) NOT NULL,
            description TEXT DEFAULT NULL,
            due_date DATE DEFAULT NULL,
            priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
            status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS purchase_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            supplier VARCHAR(100) NOT NULL,
            created_by INT DEFAULT NULL,
            status ENUM('draft', 'ordered', 'received') DEFAULT 'draft',
            total_amount DECIMAL(10,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS purchase_order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            purchase_order_id INT NOT NULL,
            inventory_id INT NOT NULL,
            product_name VARCHAR(100) NOT NULL,
            quantity_needed INT NOT NULL,
            unit_cost DECIMAL(10,2) NOT NULL,
            line_total DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
            FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS service_inventory (
            id INT AUTO_INCREMENT PRIMARY KEY,
            service_id INT NOT NULL,
            inventory_id INT NOT NULL,
            quantity_used DECIMAL(10,2) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_service_inventory (service_id, inventory_id),
            FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
            FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sms_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL,
            appointment_id INT DEFAULT NULL,
            phone VARCHAR(30) DEFAULT NULL,
            message TEXT NOT NULL,
            context VARCHAR(100) DEFAULT 'general',
            status VARCHAR(30) DEFAULT 'queued',
            provider_response TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_calendar_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            access_token TEXT NOT NULL,
            refresh_token TEXT DEFAULT NULL,
            expires_at DATETIME DEFAULT NULL,
            calendar_id VARCHAR(255) DEFAULT 'primary',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_calendar_token (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS appointment_calendar_syncs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            appointment_id INT NOT NULL,
            user_id INT NOT NULL,
            google_event_id VARCHAR(255) NOT NULL,
            last_action ENUM('upserted', 'cancelled') DEFAULT 'upserted',
            synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_appointment_calendar_sync (appointment_id, user_id),
            FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reminders_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            appointment_id INT NOT NULL,
            reminder_type VARCHAR(50) NOT NULL,
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(30) DEFAULT 'sent',
            method VARCHAR(30) DEFAULT 'system',
            UNIQUE KEY uniq_reminder_log (appointment_id, reminder_type, method),
            FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS inventory_deductions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            appointment_id INT NOT NULL,
            inventory_id INT NOT NULL,
            quantity_used DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_inventory_deduction (appointment_id, inventory_id),
            FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
            FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE CASCADE
        )
    ");

    $columns = [
        'services' => [
            "ALTER TABLE services ADD COLUMN category VARCHAR(100) DEFAULT 'Signature Care'",
            "ALTER TABLE services ADD COLUMN featured_image VARCHAR(255) DEFAULT NULL"
        ],
        'users' => [
            "ALTER TABLE users ADD COLUMN username VARCHAR(100) DEFAULT NULL",
            "ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL"
        ],
        'staff' => [
            "ALTER TABLE staff ADD COLUMN specialization VARCHAR(150) DEFAULT NULL",
            "ALTER TABLE staff ADD COLUMN experience_years INT DEFAULT 0",
            "ALTER TABLE staff ADD COLUMN bio TEXT DEFAULT NULL"
        ],
        'appointments' => [
            "ALTER TABLE appointments ADD COLUMN notes TEXT DEFAULT NULL",
            "ALTER TABLE appointments ADD COLUMN completed_at DATETIME DEFAULT NULL",
            "ALTER TABLE appointments ADD COLUMN inventory_deducted_at DATETIME DEFAULT NULL"
        ]
    ];

    foreach ($columns as $table => $queries) {
        if (!salonTableExists($pdo, $table)) {
            continue;
        }
        foreach ($queries as $query) {
            preg_match('/ADD COLUMN ([a-z_]+)/i', $query, $matches);
            $column = $matches[1] ?? '';
            if ($column && !salonColumnExists($pdo, $table, $column)) {
                $pdo->exec($query);
            }
        }
    }

    if (salonColumnExists($pdo, 'users', 'username')) {
        salonBackfillUsernames($pdo);
    }

    if (!$pdo->query("SELECT COUNT(*) FROM staff_availability")->fetchColumn()) {
        $stylists = $pdo->query("SELECT id FROM users WHERE role = 'stylist'")->fetchAll(PDO::FETCH_COLUMN);
        $stmt = $pdo->prepare("
            INSERT INTO staff_availability (user_id, work_day, start_time, end_time, is_available)
            VALUES (?, ?, '09:00:00', '19:00:00', 1)
        ");
        foreach ($stylists as $stylistId) {
            for ($day = 1; $day <= 6; $day++) {
                $stmt->execute([$stylistId, $day]);
            }
        }
    }

    if (!$pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn()) {
        $clientIds = $pdo->query("SELECT id FROM clients ORDER BY id ASC LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
        if (!$clientIds) {
            $clientIds = [null, null, null];
        }
        $seed = $pdo->prepare("INSERT INTO reviews (client_id, rating, review_text) VALUES (?, ?, ?)");
        $seed->execute([$clientIds[0] ?? null, 5, 'Elegant atmosphere, punctual service, and a stylist who understood exactly what I wanted.']);
        $seed->execute([$clientIds[1] ?? null, 5, 'Booking was effortless and the treatment felt truly premium from start to finish.']);
        $seed->execute([$clientIds[2] ?? null, 4, 'Beautiful salon, attentive reception, and excellent finishing on my color session.']);
    }

    salonSeedExpandedServices($pdo);

    $synced = true;
}

function salonSeedExpandedServices(PDO $pdo): void
{
    if (!salonTableExists($pdo, 'services')) {
        return;
    }

    $catalog = [
        ['Signature Haircut', 'Precision haircut with shaping, styling, and a polished finish.', 45, 50.00, 'Hair', 'signature_haircut.jpeg'],
        ['Classic Hair Styling', 'Classic blow-dry and styling service for a neat salon-ready look.', 45, 55.00, 'Hair', 'classic_hair_styling.webp'],
        ['Fashion Hair Styling', 'Trendy styling session designed for events and modern finishes.', 50, 60.00, 'Hair', 'hair-style.png'],
        ['Hair Color + Highlights', 'Custom color application with dimensional highlights and shine.', 90, 120.00, 'Hair', 'hair_color.avif'],
        ['Hair Spa Treatment', 'Deep nourishment therapy that refreshes the scalp and smooths the hair.', 60, 70.00, 'Hair', 'hair_spa.avif'],
        ['Keratin Smoothing', 'Frizz-control treatment for smoother, softer, and manageable hair.', 120, 150.00, 'Hair', 'keratin_smothing.avif'],
        ['Hair Nourish Ritual', 'Luxury nourishment treatment focused on softness, hydration, and finish.', 55, 65.00, 'Hair', 'hair_nourish_Ritual.avif'],

        ['Luxury Facial', 'Deep cleansing facial treatment for freshness, glow, and relaxation.', 60, 80.00, 'Skin', 'facial.jpg'],
        ['Signature Facial Glow', 'Glow-enhancing facial ritual for brighter, refreshed skin.', 60, 85.00, 'Skin', 'signature_facial_glow.jpeg'],
        ['Brightening Facial', 'Complexion-brightening facial designed for radiance and even tone.', 60, 90.00, 'Skin', 'brightining_facial.avif'],
        ['Acne Control Facial', 'Targeted facial treatment to calm congestion and support clear skin.', 50, 70.00, 'Skin', 'acne_control_facial.avif'],
        ['Anti-aging Treatment', 'Firming and smoothing therapy for mature or tired-looking skin.', 75, 110.00, 'Skin', 'anti_aging_treatment.avif'],
        ['Skin Renewal Ritual', 'Relaxing skin ritual focused on hydration, texture, and balance.', 55, 78.00, 'Skin', 'skin_renewal_ritual.jpeg'],

        ['Bridal Makeup', 'Complete bridal makeup package with elegant long-wear finishing.', 120, 200.00, 'Bridal', 'bridal.png'],
        ['Engagement Makeup', 'Refined engagement-event makeup with camera-ready finishing.', 90, 150.00, 'Bridal', 'engagement_makeup.jpeg'],
        ['Party Makeup', 'Soft glam and evening makeup for parties and celebrations.', 75, 100.00, 'Bridal', 'party_makeup.jpeg'],
        ['Airbrush Makeup', 'High-definition airbrush application for flawless event coverage.', 120, 250.00, 'Bridal', 'airbrush_makeup.jpeg'],
        ['Bridal Trial Look', 'Preview session to finalize bridal beauty direction before the event.', 75, 95.00, 'Bridal', 'bridial_trial_look.jpeg'],
        ['Reception Glam Makeup', 'Elegant glam makeup designed for receptions and formal occasions.', 95, 170.00, 'Bridal', 'glam_makeup.jpeg'],

        ['Classic Manicure', 'Essential manicure care with shaping, cuticle work, and finish.', 30, 30.00, 'Nails', 'classic_manicure.jpeg'],
        ['Essential Manicure', 'Clean and simple manicure service for everyday polished nails.', 30, 28.00, 'Nails', 'manicure.jpg'],
        ['Premium Manicure', 'Enhanced manicure service with extra care and refined finishing.', 40, 38.00, 'Nails', 'Manicure.png'],
        ['Classic Pedicure', 'Classic foot care with soaking, grooming, and fresh finishing.', 40, 40.00, 'Nails', 'pedicure.jpg'],
        ['Spa Pedicure', 'Relaxing pedicure experience with extra pampering and comfort.', 50, 48.00, 'Nails', 'spa_padicure.jpeg'],
        ['Gel Nails', 'Durable gel nail application with a sleek salon-quality finish.', 45, 50.00, 'Nails', 'jel_nails.jpeg'],
        ['Nail Art', 'Creative nail art detailing for a more expressive finished look.', 30, 25.00, 'Nails', 'nail_art.jpeg'],
        ['Nail Care Ritual', 'Nail conditioning and finishing ritual for healthy-looking hands and feet.', 35, 32.00, 'Nails', 'nail_ritual.jpeg'],
    ];

    $stmt = $pdo->prepare("
        INSERT INTO services (name, description, duration, price, category, featured_image)
        SELECT ?, ?, ?, ?, ?, ?
        FROM DUAL
        WHERE NOT EXISTS (
            SELECT 1 FROM services WHERE name = ?
        )
    ");
    $updateStmt = $pdo->prepare("
        UPDATE services
        SET description = ?, duration = ?, price = ?, category = ?, featured_image = ?
        WHERE name = ?
    ");

    foreach ($catalog as $service) {
        $stmt->execute([
            $service[0],
            $service[1],
            $service[2],
            $service[3],
            $service[4],
            $service[5],
            $service[0],
        ]);
        $updateStmt->execute([
            $service[1],
            $service[2],
            $service[3],
            $service[4],
            $service[5],
            $service[0],
        ]);
    }
}

function salonGenerateUsername(string $name, string $email): string
{
    $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $name), '_'));
    if ($base === '') {
        $base = strtolower(strstr($email, '@', true) ?: 'user');
    }
    return substr($base, 0, 40);
}

function salonUniqueUsername(PDO $pdo, string $base, ?int $ignoreUserId = null): string
{
    $base = $base !== '' ? $base : 'user';
    $candidate = $base;
    $suffix = 1;

    while (true) {
        $query = "SELECT id FROM users WHERE username = ?";
        $params = [$candidate];
        if ($ignoreUserId) {
            $query .= " AND id != ?";
            $params[] = $ignoreUserId;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        if (!$stmt->fetch()) {
            return $candidate;
        }

        $candidate = substr($base, 0, 36) . '_' . $suffix;
        $suffix++;
    }
}

function salonBackfillUsernames(PDO $pdo): void
{
    $rows = $pdo->query("SELECT id, name, email, username FROM users")->fetchAll();
    $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");

    foreach ($rows as $row) {
        if (!empty($row['username'])) {
            continue;
        }

        $base = salonGenerateUsername($row['name'], $row['email']);
        $username = salonUniqueUsername($pdo, $base, (int) $row['id']);
        $stmt->execute([$username, $row['id']]);
    }
}

function salonCreateNotification(PDO $pdo, int $userId, string $title, string $message, string $type = 'info'): void
{
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, notification_type)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $title, $message, $type]);
}

function salonGetSetting(PDO $pdo, string $key, ?string $default = null): ?string
{
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value !== false ? (string) $value : $default;
}

function salonSetSetting(PDO $pdo, string $key, ?string $value): void
{
    $stmt = $pdo->prepare("
        INSERT INTO settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    $stmt->execute([$key, $value]);
}

function salonGetSettings(PDO $pdo, array $keys): array
{
    if (!$keys) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)");
    $stmt->execute($keys);

    $settings = [];
    foreach ($stmt->fetchAll() as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    return $settings;
}

function salonGetSystemActorId(PDO $pdo): ?int
{
    $stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
    $id = $stmt->fetchColumn();
    return $id ? (int) $id : null;
}

function salonGetRoleUserIds(PDO $pdo, array $roles): array
{
    if (!$roles) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($roles), '?'));
    $stmt = $pdo->prepare("SELECT id FROM users WHERE role IN ($placeholders)");
    $stmt->execute($roles);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function salonGetUserEmail(PDO $pdo, int $userId): ?string
{
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $email = $stmt->fetchColumn();
    return $email ? (string) $email : null;
}

function salonGetUserPhone(PDO $pdo, int $userId): ?string
{
    $stmt = $pdo->prepare("
        SELECT COALESCE(NULLIF(u.phone, ''), NULLIF(c.phone, '')) AS phone
        FROM users u
        LEFT JOIN clients c ON c.user_id = u.id
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $phone = $stmt->fetchColumn();
    return $phone ? (string) $phone : null;
}

function salonSendEmail(string $toEmail, string $subject, string $message): bool
{
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $fromEmail = ini_get('sendmail_from') ?: 'support@elegancesalon.com';
    $fromName = 'Elegance Salon';
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/plain; charset=UTF-8',
        'From: ' . $fromName . ' <' . $fromEmail . '>',
        'Reply-To: ' . $fromEmail,
        'X-Mailer: PHP/' . PHP_VERSION
    ];

    $mailBody = "Elegance Salon Notification\n\n" . trim($message);
    $sent = @mail($toEmail, $subject, $mailBody, implode("\r\n", $headers));

    if (!$sent) {
        error_log('Salon email failed for ' . $toEmail . ' subject: ' . $subject);
    }

    return $sent;
}

function salonSendSMS(PDO $pdo, int $userId, string $message, string $context = 'general', ?int $appointmentId = null): bool
{
    $phone = salonGetUserPhone($pdo, $userId);
    $status = 'skipped';
    $providerResponse = null;

    if (!$phone) {
        $providerResponse = 'Missing recipient phone number.';
    } else {
        $settings = salonGetSettings($pdo, ['twilio_account_sid', 'twilio_auth_token', 'twilio_phone_number']);
        $sid = trim((string) ($settings['twilio_account_sid'] ?? ''));
        $token = trim((string) ($settings['twilio_auth_token'] ?? ''));
        $from = trim((string) ($settings['twilio_phone_number'] ?? ''));

        if ($sid === '' || $token === '' || $from === '') {
            $providerResponse = 'Twilio settings are incomplete.';
        } elseif (!class_exists('Twilio\\Rest\\Client')) {
            $providerResponse = 'Twilio SDK is not installed.';
        } else {
            try {
                $client = new \Twilio\Rest\Client($sid, $token);
                $messageResource = $client->messages->create($phone, [
                    'from' => $from,
                    'body' => $message,
                ]);
                $status = 'sent';
                $providerResponse = (string) ($messageResource->sid ?? 'sent');
            } catch (Throwable $exception) {
                $status = 'failed';
                $providerResponse = $exception->getMessage();
                error_log('Salon SMS failed: ' . $providerResponse);
            }
        }
    }

    $log = $pdo->prepare("
        INSERT INTO sms_logs (user_id, appointment_id, phone, message, context, status, provider_response)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $log->execute([$userId, $appointmentId, $phone, $message, $context, $status, $providerResponse]);

    return $status === 'sent';
}

function salonNotifyUser(PDO $pdo, int $userId, string $title, string $message, string $type = 'info', ?string $emailSubject = null, ?int $appointmentId = null): void
{
    salonCreateNotification($pdo, $userId, $title, $message, $type);

    $email = salonGetUserEmail($pdo, $userId);
    if ($email) {
        salonSendEmail($email, $emailSubject ?: $title, $message);
    }

    salonSendSMS($pdo, $userId, $message, strtolower(str_replace(' ', '_', $title)), $appointmentId);
}

function salonFetchNotifications(PDO $pdo, int $userId, int $limit = 5): array
{
    $stmt = $pdo->prepare("
        SELECT id, title, message, notification_type, is_read, created_at
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT $limit
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function salonMarkNotificationsRead(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
}

function salonGetTimeSlots(string $start = '09:00', string $end = '19:00', int $stepMinutes = 30): array
{
    $slots = [];
    $cursor = strtotime($start);
    $endTime = strtotime($end);

    while ($cursor < $endTime) {
        $slots[] = date('H:i', $cursor);
        $cursor = strtotime("+{$stepMinutes} minutes", $cursor);
    }

    return $slots;
}

function salonFetchAvailabilityMap(PDO $pdo): array
{
    $rows = $pdo->query("
        SELECT user_id, work_day, start_time, end_time, is_available
        FROM staff_availability
        ORDER BY user_id, work_day
    ")->fetchAll();

    $map = [];
    foreach ($rows as $row) {
        $map[$row['user_id']][(int) $row['work_day']] = [
            'start' => substr($row['start_time'], 0, 5),
            'end' => substr($row['end_time'], 0, 5),
            'available' => (bool) $row['is_available']
        ];
    }

    return $map;
}

function salonFetchBlockedSlots(PDO $pdo): array
{
    $rows = $pdo->query("
        SELECT stylist_id, appointment_date, appointment_time
        FROM appointments
        WHERE status IN ('pending', 'confirmed', 'completed')
    ")->fetchAll();

    $blocked = [];
    foreach ($rows as $row) {
        $blocked[$row['stylist_id']][$row['appointment_date']][] = substr($row['appointment_time'], 0, 5);
    }

    return $blocked;
}

function salonStylistWorksAt(array $availabilityMap, int $stylistId, string $date, string $time, ?PDO $pdo = null): bool
{
    static $leaveCache = [];
    $cacheKey = $stylistId . '|' . $date;

    $db = $pdo;
    if (!$db && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $db = $GLOBALS['pdo'];
    }

    if (!array_key_exists($cacheKey, $leaveCache) || ($leaveCache[$cacheKey] === null && $db)) {
        if ($db) {
            $leaveStmt = $db->prepare("
                SELECT id
                FROM staff_leaves
                WHERE user_id = ? AND leave_date = ?
                LIMIT 1
            ");
            $leaveStmt->execute([$stylistId, $date]);
            $leaveCache[$cacheKey] = (bool) $leaveStmt->fetchColumn();
        } else {
            $leaveCache[$cacheKey] = null;
        }
    }

    if (($leaveCache[$cacheKey] ?? false) === true) {
        return false;
    }

    $day = (int) date('N', strtotime($date));
    $rule = $availabilityMap[$stylistId][$day] ?? null;
    if (!$rule || !$rule['available']) {
        return false;
    }

    return $time >= $rule['start'] && $time < $rule['end'];
}

function salonGenerateSlotState(array $slots, array $availabilityMap, array $blocked, int $stylistId, string $date, ?PDO $pdo = null): array
{
    $dayBlocked = $blocked[$stylistId][$date] ?? [];
    $slotState = [];

    foreach ($slots as $slot) {
        $status = 'available';
        if (!salonStylistWorksAt($availabilityMap, $stylistId, $date, $slot, $pdo)) {
            $status = 'unavailable';
        } elseif (in_array($slot, $dayBlocked, true)) {
            $status = 'booked';
        }

        $slotState[] = [
            'time' => $slot,
            'status' => $status
        ];
    }

    return $slotState;
}

function salonCreateCommission(PDO $pdo, int $appointmentId): void
{
    $stmt = $pdo->prepare("
        SELECT a.id, a.stylist_id, s.price AS service_amount, COALESCE(st.commission_rate, 0) AS commission_rate
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        LEFT JOIN staff st ON st.user_id = a.stylist_id
        WHERE a.id = ?
    ");
    $stmt->execute([$appointmentId]);
    $data = $stmt->fetch();

    if (!$data) {
        return;
    }

    $check = $pdo->prepare("SELECT id FROM commissions WHERE appointment_id = ?");
    $check->execute([$appointmentId]);
    if ($check->fetch()) {
        return;
    }

    $commissionAmount = ((float) $data['service_amount']) * ((float) $data['commission_rate'] / 100);
    $insert = $pdo->prepare("
        INSERT INTO commissions (appointment_id, stylist_id, service_amount, commission_rate, commission_amount)
        VALUES (?, ?, ?, ?, ?)
    ");
    $insert->execute([
        $appointmentId,
        $data['stylist_id'],
        $data['service_amount'],
        $data['commission_rate'],
        $commissionAmount
    ]);
}

function salonDeductInventoryForService(PDO $pdo, int $appointmentId): array
{
    $appointmentStmt = $pdo->prepare("
        SELECT id, service_id, status, inventory_deducted_at
        FROM appointments
        WHERE id = ?
        LIMIT 1
    ");
    $appointmentStmt->execute([$appointmentId]);
    $appointment = $appointmentStmt->fetch();

    if (!$appointment || $appointment['status'] !== 'completed' || !empty($appointment['inventory_deducted_at'])) {
        return ['items' => 0, 'deducted' => 0.0];
    }

    $itemsStmt = $pdo->prepare("
        SELECT si.inventory_id, si.quantity_used
        FROM service_inventory si
        WHERE si.service_id = ?
    ");
    $itemsStmt->execute([(int) $appointment['service_id']]);
    $items = $itemsStmt->fetchAll();

    $checkStmt = $pdo->prepare("SELECT id FROM inventory_deductions WHERE appointment_id = ? AND inventory_id = ?");
    $deductionStmt = $pdo->prepare("
        INSERT INTO inventory_deductions (appointment_id, inventory_id, quantity_used)
        VALUES (?, ?, ?)
    ");
    $updateInventory = $pdo->prepare("
        UPDATE inventory
        SET quantity = CASE
            WHEN quantity - ? < 0 THEN 0
            ELSE quantity - ?
        END
        WHERE id = ?
    ");

    $itemCount = 0;
    $totalDeducted = 0.0;

    foreach ($items as $item) {
        $checkStmt->execute([$appointmentId, $item['inventory_id']]);
        if ($checkStmt->fetch()) {
            continue;
        }

        $quantityUsed = (float) $item['quantity_used'];
        $updateInventory->execute([$quantityUsed, $quantityUsed, $item['inventory_id']]);
        $deductionStmt->execute([$appointmentId, $item['inventory_id'], $quantityUsed]);
        $itemCount++;
        $totalDeducted += $quantityUsed;
    }

    $markStmt = $pdo->prepare("UPDATE appointments SET inventory_deducted_at = NOW() WHERE id = ?");
    $markStmt->execute([$appointmentId]);

    return ['items' => $itemCount, 'deducted' => $totalDeducted];
}

function salonGenerateAutoPurchaseOrders(PDO $pdo, ?int $createdBy = null): int
{
    $createdBy = $createdBy ?: salonGetSystemActorId($pdo);
    $items = $pdo->query("
        SELECT i.id, i.product_name, i.quantity, i.min_stock, i.price, i.supplier
        FROM inventory i
        WHERE i.quantity <= i.min_stock
        ORDER BY i.supplier ASC, i.product_name ASC
    ")->fetchAll();

    if (!$items) {
        return 0;
    }

    $grouped = [];
    foreach ($items as $item) {
        $check = $pdo->prepare("
            SELECT poi.id
            FROM purchase_order_items poi
            JOIN purchase_orders po ON po.id = poi.purchase_order_id
            WHERE poi.inventory_id = ? AND po.status IN ('draft', 'ordered')
        ");
        $check->execute([$item['id']]);
        if ($check->fetch()) {
            continue;
        }

        $supplier = trim($item['supplier']) !== '' ? $item['supplier'] : 'Unassigned Supplier';
        $grouped[$supplier][] = $item;
    }

    if (!$grouped) {
        return 0;
    }

    $poStmt = $pdo->prepare("
        INSERT INTO purchase_orders (supplier, created_by, status, total_amount)
        VALUES (?, ?, 'draft', 0)
    ");
    $itemStmt = $pdo->prepare("
        INSERT INTO purchase_order_items (purchase_order_id, inventory_id, product_name, quantity_needed, unit_cost, line_total)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $updatePo = $pdo->prepare("UPDATE purchase_orders SET total_amount = ? WHERE id = ?");

    $createdCount = 0;

    foreach ($grouped as $supplier => $supplierItems) {
        $poStmt->execute([$supplier, $createdBy]);
        $purchaseOrderId = (int) $pdo->lastInsertId();
        $total = 0;

        foreach ($supplierItems as $item) {
            $quantityNeeded = max((int) $item['min_stock'] * 2 - (int) $item['quantity'], 1);
            $lineTotal = $quantityNeeded * (float) $item['price'];
            $itemStmt->execute([
                $purchaseOrderId,
                $item['id'],
                $item['product_name'],
                $quantityNeeded,
                $item['price'],
                $lineTotal
            ]);
            $total += $lineTotal;
        }

        $updatePo->execute([$total, $purchaseOrderId]);
        $createdCount++;
    }

    return $createdCount;
}

function salonCheckAndGenerateAutoPO(PDO $pdo, ?int $createdBy = null): int
{
    $createdBy = $createdBy ?: salonGetSystemActorId($pdo);
    $created = salonGenerateAutoPurchaseOrders($pdo, $createdBy);

    if ($created > 0) {
        foreach (salonGetRoleUserIds($pdo, ['admin']) as $adminId) {
            salonCreateNotification(
                $pdo,
                $adminId,
                'Automatic Purchase Orders',
                $created . ' purchase order(s) were generated automatically for low-stock inventory.',
                'warning'
            );
        }
    }

    return $created;
}

function salonReminderAlreadyLogged(PDO $pdo, int $appointmentId, string $reminderType, string $method): bool
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM reminders_log
        WHERE appointment_id = ? AND reminder_type = ? AND method = ?
        LIMIT 1
    ");
    $stmt->execute([$appointmentId, $reminderType, $method]);
    return (bool) $stmt->fetchColumn();
}

function salonLogReminder(PDO $pdo, int $appointmentId, string $reminderType, string $status, string $method): void
{
    $stmt = $pdo->prepare("
        INSERT INTO reminders_log (appointment_id, reminder_type, status, method)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE sent_at = CURRENT_TIMESTAMP, status = VALUES(status)
    ");
    $stmt->execute([$appointmentId, $reminderType, $status, $method]);
}

function salonFetchAppointmentContext(PDO $pdo, int $appointmentId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            a.id,
            a.client_id,
            a.service_id,
            a.stylist_id,
            a.appointment_date,
            a.appointment_time,
            a.status,
            a.notes,
            a.completed_at,
            s.name AS service_name,
            s.duration,
            c.name AS client_name,
            c.user_id AS client_user_id,
            u.name AS stylist_name
        FROM appointments a
        JOIN services s ON s.id = a.service_id
        JOIN clients c ON c.id = a.client_id
        JOIN users u ON u.id = a.stylist_id
        WHERE a.id = ?
        LIMIT 1
    ");
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch();
    return $appointment ?: null;
}

function salonSendReminder(PDO $pdo, int $appointmentId, string $reminderType, string $method = 'both'): bool
{
    if (salonReminderAlreadyLogged($pdo, $appointmentId, $reminderType, $method)) {
        return false;
    }

    $appointment = salonFetchAppointmentContext($pdo, $appointmentId);
    if (!$appointment) {
        return false;
    }

    $dateLabel = date('M d, Y', strtotime($appointment['appointment_date']));
    $timeLabel = date('h:i A', strtotime($appointment['appointment_time']));
    $clientMessage = '';
    $stylistMessage = '';

    if ($reminderType === '24_hours_before') {
        $clientMessage = "Reminder: your {$appointment['service_name']} appointment is scheduled for {$dateLabel} at {$timeLabel}.";
        $stylistMessage = "Reminder: you have {$appointment['client_name']} booked for {$appointment['service_name']} on {$dateLabel} at {$timeLabel}.";
    } elseif ($reminderType === '1_hour_before') {
        $clientMessage = "Your Elegance Salon appointment for {$appointment['service_name']} starts at {$timeLabel}. Please arrive 10 minutes early.";
        $stylistMessage = "Upcoming appointment: {$appointment['client_name']} is due at {$timeLabel} for {$appointment['service_name']}.";
    } elseif ($reminderType === '7_days_followup') {
        $clientMessage = "We hope you loved your {$appointment['service_name']} experience. We'd appreciate your feedback when you have a moment.";
    } else {
        return false;
    }

    $status = 'sent';

    try {
        if ($method === 'sms') {
            if (!empty($appointment['client_user_id']) && $clientMessage !== '') {
                salonSendSMS($pdo, (int) $appointment['client_user_id'], $clientMessage, $reminderType, $appointmentId);
            }
            if ($stylistMessage !== '' && $reminderType !== '7_days_followup') {
                salonSendSMS($pdo, (int) $appointment['stylist_id'], $stylistMessage, $reminderType, $appointmentId);
            }
        } else {
            if (!empty($appointment['client_user_id']) && $clientMessage !== '') {
                salonCreateNotification($pdo, (int) $appointment['client_user_id'], 'Appointment Reminder', $clientMessage, 'info');
                $clientEmail = salonGetUserEmail($pdo, (int) $appointment['client_user_id']);
                if ($clientEmail) {
                    salonSendEmail($clientEmail, 'Elegance Salon Reminder', $clientMessage);
                }
            }
            if ($stylistMessage !== '' && $reminderType !== '7_days_followup') {
                salonCreateNotification($pdo, (int) $appointment['stylist_id'], 'Schedule Reminder', $stylistMessage, 'info');
                $stylistEmail = salonGetUserEmail($pdo, (int) $appointment['stylist_id']);
                if ($stylistEmail) {
                    salonSendEmail($stylistEmail, 'Elegance Salon Staff Reminder', $stylistMessage);
                }
            }
        }
    } catch (Throwable $exception) {
        $status = 'failed';
        error_log('Salon reminder failed: ' . $exception->getMessage());
    }

    salonLogReminder($pdo, $appointmentId, $reminderType, $status, $method);
    return $status === 'sent';
}

function salonFetchReminderStats(PDO $pdo): array
{
    return [
        'today' => (int) $pdo->query("SELECT COUNT(*) FROM reminders_log WHERE DATE(sent_at) = CURDATE()")->fetchColumn(),
        'week' => (int) $pdo->query("SELECT COUNT(*) FROM reminders_log WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
        'failed' => (int) $pdo->query("SELECT COUNT(*) FROM reminders_log WHERE status = 'failed' AND sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
    ];
}

function salonGoogleRedirectUri(PDO $pdo): string
{
    $configured = trim((string) salonGetSetting($pdo, 'google_redirect_uri', ''));
    if ($configured !== '') {
        return $configured;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/salon-management/google_calendar_auth.php';
}

function salonHasGoogleCalendarConnection(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare("SELECT id FROM user_calendar_tokens WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    return (bool) $stmt->fetchColumn();
}

function salonBuildGoogleClient(PDO $pdo, ?int $userId = null): ?object
{
    if (!class_exists('Google\\Client')) {
        return null;
    }

    $settings = salonGetSettings($pdo, ['google_client_id', 'google_client_secret', 'google_redirect_uri']);
    $clientId = trim((string) ($settings['google_client_id'] ?? ''));
    $clientSecret = trim((string) ($settings['google_client_secret'] ?? ''));

    if ($clientId === '' || $clientSecret === '') {
        return null;
    }

    $client = new \Google\Client();
    $client->setClientId($clientId);
    $client->setClientSecret($clientSecret);
    $client->setRedirectUri($settings['google_redirect_uri'] ?? salonGoogleRedirectUri($pdo));
    $client->setAccessType('offline');
    $client->setPrompt('consent');
    $client->setScopes(['https://www.googleapis.com/auth/calendar']);

    if ($userId) {
        $stmt = $pdo->prepare("
            SELECT access_token, refresh_token, expires_at
            FROM user_calendar_tokens
            WHERE user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $tokenRow = $stmt->fetch();

        if ($tokenRow) {
            $client->setAccessToken([
                'access_token' => $tokenRow['access_token'],
                'refresh_token' => $tokenRow['refresh_token'],
                'expires_in' => max(strtotime((string) $tokenRow['expires_at']) - time(), 0),
                'created' => time(),
            ]);

            if ($client->isAccessTokenExpired() && !empty($tokenRow['refresh_token'])) {
                try {
                    $newToken = $client->fetchAccessTokenWithRefreshToken($tokenRow['refresh_token']);
                    if (!isset($newToken['error'])) {
                        $accessToken = $newToken['access_token'] ?? $tokenRow['access_token'];
                        $refreshToken = $newToken['refresh_token'] ?? $tokenRow['refresh_token'];
                        $expiresAt = date('Y-m-d H:i:s', time() + (int) ($newToken['expires_in'] ?? 3600));
                        $save = $pdo->prepare("
                            UPDATE user_calendar_tokens
                            SET access_token = ?, refresh_token = ?, expires_at = ?
                            WHERE user_id = ?
                        ");
                        $save->execute([$accessToken, $refreshToken, $expiresAt, $userId]);
                        $client->setAccessToken([
                            'access_token' => $accessToken,
                            'refresh_token' => $refreshToken,
                            'expires_in' => (int) ($newToken['expires_in'] ?? 3600),
                            'created' => time(),
                        ]);
                    }
                } catch (Throwable $exception) {
                    error_log('Google token refresh failed: ' . $exception->getMessage());
                }
            }
        }
    }

    return $client;
}

function salonStoreGoogleCalendarToken(PDO $pdo, int $userId, array $tokenPayload, string $calendarId = 'primary'): void
{
    $accessToken = (string) ($tokenPayload['access_token'] ?? '');
    if ($accessToken === '') {
        throw new RuntimeException('Google access token missing.');
    }

    $refreshToken = (string) ($tokenPayload['refresh_token'] ?? '');
    $expiresAt = date('Y-m-d H:i:s', time() + (int) ($tokenPayload['expires_in'] ?? 3600));

    $existingStmt = $pdo->prepare("SELECT refresh_token FROM user_calendar_tokens WHERE user_id = ? LIMIT 1");
    $existingStmt->execute([$userId]);
    $existingRefresh = (string) ($existingStmt->fetchColumn() ?: '');
    if ($refreshToken === '') {
        $refreshToken = $existingRefresh;
    }

    $stmt = $pdo->prepare("
        INSERT INTO user_calendar_tokens (user_id, access_token, refresh_token, expires_at, calendar_id)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            access_token = VALUES(access_token),
            refresh_token = VALUES(refresh_token),
            expires_at = VALUES(expires_at),
            calendar_id = VALUES(calendar_id)
    ");
    $stmt->execute([$userId, $accessToken, $refreshToken, $expiresAt, $calendarId]);
}

function salonSyncToGoogleCalendar(PDO $pdo, int $appointmentId, string $action = 'upsert'): void
{
    if (!class_exists('Google\\Service\\Calendar')) {
        return;
    }

    $appointment = salonFetchAppointmentContext($pdo, $appointmentId);
    if (!$appointment) {
        return;
    }

    $recipientIds = [];
    if (!empty($appointment['client_user_id'])) {
        $recipientIds[] = (int) $appointment['client_user_id'];
    }
    $recipientIds[] = (int) $appointment['stylist_id'];
    $recipientIds = array_merge($recipientIds, salonGetRoleUserIds($pdo, ['admin']));
    $recipientIds = array_values(array_unique(array_filter($recipientIds)));

    $syncLookup = $pdo->prepare("
        SELECT google_event_id
        FROM appointment_calendar_syncs
        WHERE appointment_id = ? AND user_id = ?
        LIMIT 1
    ");
    $syncSave = $pdo->prepare("
        INSERT INTO appointment_calendar_syncs (appointment_id, user_id, google_event_id, last_action)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE google_event_id = VALUES(google_event_id), last_action = VALUES(last_action), synced_at = CURRENT_TIMESTAMP
    ");
    $syncDelete = $pdo->prepare("DELETE FROM appointment_calendar_syncs WHERE appointment_id = ? AND user_id = ?");
    $tokenStmt = $pdo->prepare("SELECT calendar_id FROM user_calendar_tokens WHERE user_id = ? LIMIT 1");

    foreach ($recipientIds as $userId) {
        if (!salonHasGoogleCalendarConnection($pdo, $userId)) {
            continue;
        }

        $client = salonBuildGoogleClient($pdo, $userId);
        if (!$client) {
            continue;
        }

        $tokenStmt->execute([$userId]);
        $calendarId = (string) ($tokenStmt->fetchColumn() ?: 'primary');
        $service = new \Google\Service\Calendar($client);

        $syncLookup->execute([$appointmentId, $userId]);
        $existingEventId = $syncLookup->fetchColumn();

        try {
            if ($action === 'cancel') {
                if ($existingEventId) {
                    $service->events->delete($calendarId, (string) $existingEventId);
                    $syncDelete->execute([$appointmentId, $userId]);
                }
                continue;
            }

            $start = new DateTime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']);
            $end = clone $start;
            $end->modify('+' . max((int) $appointment['duration'], 30) . ' minutes');
            $timeZone = $start->getTimezone()->getName();

            $event = new \Google\Service\Calendar\Event([
                'summary' => $appointment['service_name'] . ' - ' . $appointment['client_name'],
                'description' => "Client: {$appointment['client_name']}\nStylist: {$appointment['stylist_name']}\nStatus: {$appointment['status']}" . (!empty($appointment['notes']) ? "\nNotes: {$appointment['notes']}" : ''),
                'start' => [
                    'dateTime' => $start->format(DateTimeInterface::RFC3339),
                    'timeZone' => $timeZone,
                ],
                'end' => [
                    'dateTime' => $end->format(DateTimeInterface::RFC3339),
                    'timeZone' => $timeZone,
                ],
            ]);

            if ($existingEventId) {
                $savedEvent = $service->events->update($calendarId, (string) $existingEventId, $event);
            } else {
                $savedEvent = $service->events->insert($calendarId, $event);
            }

            $syncSave->execute([$appointmentId, $userId, (string) $savedEvent->id, 'upserted']);
        } catch (Throwable $exception) {
            error_log('Google calendar sync failed: ' . $exception->getMessage());
        }
    }
}

function salonIcsEscape(string $value): string
{
    $value = str_replace(["\\", ";", ",", "\r\n", "\n", "\r"], ["\\\\", "\;", "\,", "\\n", "\\n", "\\n"], $value);
    return $value;
}

function salonAppointmentDateTimeUtc(string $date, string $time): string
{
    $dt = new DateTime($date . ' ' . $time, new DateTimeZone(date_default_timezone_get()));
    $dt->setTimezone(new DateTimeZone('UTC'));
    return $dt->format('Ymd\THis\Z');
}

function salonAppointmentEndTimeUtc(string $date, string $time, int $durationMinutes): string
{
    $dt = new DateTime($date . ' ' . $time, new DateTimeZone(date_default_timezone_get()));
    $dt->modify('+' . max($durationMinutes, 30) . ' minutes');
    $dt->setTimezone(new DateTimeZone('UTC'));
    return $dt->format('Ymd\THis\Z');
}

function salonBuildIcs(array $appointments, string $calendarName = 'Elegance Salon Appointments'): string
{
    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Elegance Salon//Salon Management//EN',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'X-WR-CALNAME:' . salonIcsEscape($calendarName)
    ];

    foreach ($appointments as $appointment) {
        $start = salonAppointmentDateTimeUtc($appointment['appointment_date'], $appointment['appointment_time']);
        $end = salonAppointmentEndTimeUtc($appointment['appointment_date'], $appointment['appointment_time'], (int) ($appointment['duration'] ?? 30));
        $summary = salonIcsEscape(($appointment['service_name'] ?? 'Salon Appointment') . ' - ' . ($appointment['stylist_name'] ?? 'Elegance Salon'));
        $description = salonIcsEscape(
            "Client: " . ($appointment['client_name'] ?? 'Guest') .
            "\nService: " . ($appointment['service_name'] ?? 'Appointment') .
            "\nStylist: " . ($appointment['stylist_name'] ?? 'Elegance Salon') .
            (!empty($appointment['notes']) ? "\nNotes: " . $appointment['notes'] : '')
        );

        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:appointment-' . (int) $appointment['id'] . '@elegancesalon.local';
        $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
        $lines[] = 'DTSTART:' . $start;
        $lines[] = 'DTEND:' . $end;
        $lines[] = 'SUMMARY:' . $summary;
        $lines[] = 'DESCRIPTION:' . $description;
        $lines[] = 'STATUS:CONFIRMED';
        $lines[] = 'END:VEVENT';
    }

    $lines[] = 'END:VCALENDAR';

    return implode("\r\n", $lines) . "\r\n";
}
