<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireRole('receptionist');

$today = date('Y-m-d');

$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date = ? AND status != 'cancelled'");
$stmt->execute([$today]);
$appointments_today = (int) $stmt->fetchColumn();

// Retrieve today's appointments
$stmt = $pdo->prepare("
    SELECT a.appointment_time, a.status, c.name AS client_name, s.name AS service_name, u.name AS stylist_name
    FROM appointments a
    JOIN clients c ON c.id = a.client_id
    JOIN services s ON s.id = a.service_id
    JOIN users u ON u.id = a.stylist_id
    WHERE a.appointment_date = ? AND a.status != 'cancelled'
    ORDER BY a.appointment_time ASC
");
$stmt->execute([$today]);
$todays_schedule = $stmt->fetchAll();

$notifications = salonFetchNotifications($pdo, $_SESSION['user_id'], 6);

include '../includes/header.php';
?>

<div class="container py-2">
    <div class="flex flex-between mb-2">
        <div>
            <span class="eyebrow">Front Desk Dashboard</span>
            <h2>Reception Panel</h2>
        </div>
        <div style="display:flex; gap: 15px; align-items:center;">
            <a href="profile.php" class="btn btn-outline-gold" style="padding: 10px 20px;">Profile Settings</a>
            <span class="badge badge-completed">Access Level: Receptionist</span>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="stat-card" style="grid-column: span 2;">
            <h3>Welcome back, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Receptionist'); ?>!</h3>
            <p style="color:var(--color-muted); margin-top:8px;">Manage today's appointments and clients from your front desk control center.</p>
        </div>
        <div class="stat-card">
            <h3>Appointments Today</h3>
            <div class="stat-value text-gold"><?php echo $appointments_today; ?></div>
        </div>
    </div>

    <div class="dashboard-grid mt-2">
        <div class="detail-card" style="grid-column: span 2;">
            <span class="eyebrow">Smart Calendar</span>
            <h3>Today's Schedule</h3>
            <div class="mini-calendar">
                <?php foreach ($todays_schedule as $slot): ?>
                    <div class="calendar-slot <?php echo htmlspecialchars($slot['status']); ?>">
                        <strong><?php echo date('h:i A', strtotime($slot['appointment_time'])); ?></strong>
                        <div><?php echo htmlspecialchars($slot['client_name']); ?> with <?php echo htmlspecialchars($slot['stylist_name']); ?></div>
                        <small><?php echo htmlspecialchars($slot['service_name']); ?> | <?php echo ucfirst($slot['status']); ?></small>
                    </div>
                <?php endforeach; ?>
                <?php if (!$todays_schedule): ?>
                    <p>No active appointments scheduled today.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="detail-card">
            <span class="eyebrow">Quick Actions</span>
            <h3>Manage operations</h3>
            <div class="history-list">
                <a href="/salon-management/appointments.php" class="btn btn-outline-gold">New Appointment</a>
                <a href="/salon-management/clients.php" class="btn btn-outline-gold">Client Database</a>
                <a href="/salon-management/payments.php" class="btn btn-outline-gold">Manage Payments</a>
                <a href="/salon-management/staff.php" class="btn btn-outline-gold">Stylist Schedules</a>
            </div>
        </div>

        <div class="detail-card">
            <span class="eyebrow">Alerts</span>
            <h3>Front desk notifications</h3>
            <div class="notification-list">
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item">
                        <strong><?php echo htmlspecialchars($notification['title']); ?></strong>
                        <p><?php echo htmlspecialchars($notification['message']); ?></p>
                        <small><?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?></small>
                    </div>
                <?php endforeach; ?>
                <?php if (!$notifications): ?>
                    <p>No new notifications.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
