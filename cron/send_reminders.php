<?php
require_once dirname(__DIR__) . '/includes/db.php';

$queries = [
    '24_hours_before' => "
        SELECT id
        FROM appointments
        WHERE status IN ('pending', 'confirmed')
          AND TIMESTAMP(appointment_date, appointment_time) BETWEEN DATE_ADD(NOW(), INTERVAL 24 HOUR) AND DATE_ADD(DATE_ADD(NOW(), INTERVAL 24 HOUR), INTERVAL 15 MINUTE)
    ",
    '1_hour_before' => "
        SELECT id
        FROM appointments
        WHERE status IN ('pending', 'confirmed')
          AND TIMESTAMP(appointment_date, appointment_time) BETWEEN DATE_ADD(NOW(), INTERVAL 1 HOUR) AND DATE_ADD(NOW(), INTERVAL 75 MINUTE)
    ",
    '7_days_followup' => "
        SELECT id
        FROM appointments
        WHERE status = 'completed'
          AND completed_at IS NOT NULL
          AND completed_at BETWEEN DATE_SUB(DATE_SUB(NOW(), INTERVAL 7 DAY), INTERVAL 15 MINUTE) AND DATE_SUB(NOW(), INTERVAL 7 DAY)
    ",
];

$sent = [
    '24_hours_before' => 0,
    '1_hour_before' => 0,
    '7_days_followup' => 0,
];

foreach ($queries as $type => $query) {
    $stmt = $pdo->query($query);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $appointmentId) {
        if (salonSendReminder($pdo, (int) $appointmentId, $type, 'both')) {
            $sent[$type]++;
        }
    }
}

echo 'Reminder run complete. 24h: ' . $sent['24_hours_before']
    . ', 1h: ' . $sent['1_hour_before']
    . ', followup: ' . $sent['7_days_followup'] . PHP_EOL;
