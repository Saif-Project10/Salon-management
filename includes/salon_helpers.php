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

    $columns = [
        'services' => [
            "ALTER TABLE services ADD COLUMN category VARCHAR(100) DEFAULT 'Signature Care'",
            "ALTER TABLE services ADD COLUMN featured_image VARCHAR(255) DEFAULT NULL"
        ],
        'users' => [
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

function salonCreateNotification(PDO $pdo, int $userId, string $title, string $message, string $type = 'info'): void
{
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, notification_type)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $title, $message, $type]);
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

