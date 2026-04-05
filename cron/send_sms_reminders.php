<?php
require_once dirname(__DIR__) . '/includes/db.php';

$stmt = $pdo->query("
    SELECT id
    FROM appointments
    WHERE status IN ('pending', 'confirmed')
      AND TIMESTAMP(appointment_date, appointment_time) BETWEEN DATE_ADD(NOW(), INTERVAL 1 HOUR) AND DATE_ADD(NOW(), INTERVAL 75 MINUTE)
    ORDER BY appointment_date ASC, appointment_time ASC
");

$count = 0;

foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $appointmentId) {
    if (salonSendReminder($pdo, (int) $appointmentId, '1_hour_before', 'sms')) {
        $count++;
    }
}

echo "SMS reminder run complete. Sent: {$count}" . PHP_EOL;
