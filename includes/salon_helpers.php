<?php

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
            "ALTER TABLE appointments ADD COLUMN notes TEXT DEFAULT NULL"
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

    $synced = true;
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

function salonGetUserEmail(PDO $pdo, int $userId): ?string
{
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $email = $stmt->fetchColumn();
    return $email ? (string) $email : null;
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

function salonNotifyUser(PDO $pdo, int $userId, string $title, string $message, string $type = 'info', ?string $emailSubject = null): void
{
    salonCreateNotification($pdo, $userId, $title, $message, $type);

    $email = salonGetUserEmail($pdo, $userId);
    if ($email) {
        salonSendEmail($email, $emailSubject ?: $title, $message);
    }
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

function salonStylistWorksAt(array $availabilityMap, int $stylistId, string $date, string $time): bool
{
    $day = (int) date('N', strtotime($date));
    $rule = $availabilityMap[$stylistId][$day] ?? null;
    if (!$rule || !$rule['available']) {
        return false;
    }

    return $time >= $rule['start'] && $time < $rule['end'];
}

function salonGenerateSlotState(array $slots, array $availabilityMap, array $blocked, int $stylistId, string $date): array
{
    $dayBlocked = $blocked[$stylistId][$date] ?? [];
    $slotState = [];

    foreach ($slots as $slot) {
        $status = 'available';
        if (!salonStylistWorksAt($availabilityMap, $stylistId, $date, $slot)) {
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

function salonGenerateAutoPurchaseOrders(PDO $pdo, int $createdBy): int
{
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
