<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireLogin();

$userRole = currentUserRole();
$userId = $_SESSION['user_id'];
$clientId = null;

if ($userRole === 'client') {
    $stmt = $pdo->prepare("SELECT id FROM clients WHERE user_id = ?");
    $stmt->execute([$userId]);
    $clientId = (int) ($stmt->fetchColumn() ?: 0);
}

$singleId = isset($_GET['appointment_id']) ? (int) $_GET['appointment_id'] : 0;
$params = [];
$where = "WHERE a.status IN ('pending', 'confirmed', 'completed')";

if ($singleId > 0) {
    $where .= " AND a.id = ?";
    $params[] = $singleId;
}

if ($userRole === 'client') {
    $where .= " AND a.client_id = ?";
    $params[] = $clientId;
} elseif ($userRole === 'stylist') {
    $where .= " AND a.stylist_id = ?";
    $params[] = $userId;
}

$stmt = $pdo->prepare("
    SELECT a.id, a.appointment_date, a.appointment_time, a.notes,
           c.name AS client_name, s.name AS service_name, s.duration,
           u.name AS stylist_name
    FROM appointments a
    JOIN clients c ON c.id = a.client_id
    JOIN services s ON s.id = a.service_id
    JOIN users u ON u.id = a.stylist_id
    $where
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
$stmt->execute($params);
$appointments = $stmt->fetchAll();

if (!$appointments) {
    http_response_code(404);
    die('No calendar entries available for export.');
}

$calendarName = $singleId > 0 ? 'Elegance Salon Appointment' : 'Elegance Salon Schedule';
$ics = salonBuildIcs($appointments, $calendarName);
$filename = $singleId > 0 ? 'appointment-' . $singleId . '.ics' : 'elegance-salon-schedule.ics';

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $ics;
