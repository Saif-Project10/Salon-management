<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireRole(['admin', 'receptionist', 'stylist']);

$weekOffset = isset($_GET['week']) ? (int) $_GET['week'] : 0;
$monday = date('Y-m-d', strtotime("monday this week $weekOffset week"));
$days = [];
for ($i = 0; $i < 7; $i++) {
    $days[] = date('Y-m-d', strtotime("$monday +$i day"));
}

$where = 'WHERE a.appointment_date BETWEEN ? AND ? AND a.status != \'cancelled\'';
$params = [$days[0], end($days)];
if (currentUserRole() === 'stylist') {
    $where .= ' AND a.stylist_id = ?';
    $params[] = $_SESSION['user_id'];
}

$stmt = $pdo->prepare("
    SELECT a.appointment_date, a.appointment_time, a.status,
           c.name AS client_name, s.name AS service_name, u.name AS stylist_name
    FROM appointments a
    JOIN clients c ON c.id = a.client_id
    JOIN services s ON s.id = a.service_id
    JOIN users u ON u.id = a.stylist_id
    $where
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
$stmt->execute($params);
$appointments = $stmt->fetchAll();

$grouped = [];
foreach ($appointments as $appointment) {
    $grouped[$appointment['appointment_date']][] = $appointment;
}

include 'includes/header.php';
?>

<div class="container py-2">
    <div class="flex flex-between mb-2">
        <div>
            <span class="eyebrow">Schedule Calendar</span>
            <h2>Weekly appointment view</h2>
        </div>
        <div class="filter-row">
            <a href="/salon-management/calendar_export.php" class="btn btn-outline-gold">Export Schedule (.ics)</a>
            <a href="?week=<?php echo $weekOffset - 1; ?>" class="btn btn-outline-gold">Previous Week</a>
            <a href="?week=<?php echo $weekOffset + 1; ?>" class="btn btn-primary">Next Week</a>
        </div>
    </div>

    <div class="dashboard-grid" style="grid-template-columns: repeat(7, minmax(0, 1fr));">
        <?php foreach ($days as $day): ?>
            <div class="calendar-day">
                <h4><?php echo date('D', strtotime($day)); ?></h4>
                <strong><?php echo date('M d', strtotime($day)); ?></strong>
                <?php foreach ($grouped[$day] ?? [] as $slot): ?>
                    <div class="calendar-slot <?php echo htmlspecialchars($slot['status']); ?>">
                        <strong><?php echo date('h:i A', strtotime($slot['appointment_time'])); ?></strong>
                        <div><?php echo htmlspecialchars($slot['client_name']); ?></div>
                        <small><?php echo htmlspecialchars($slot['service_name']); ?><?php if (currentUserRole() !== 'stylist'): ?> | <?php echo htmlspecialchars($slot['stylist_name']); ?><?php endif; ?></small>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($grouped[$day])): ?>
                    <p style="margin-top:12px; color:var(--color-muted);">No bookings</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
