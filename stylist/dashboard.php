<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireRole('stylist');

$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE stylist_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_apps = (int) $stmt->fetchColumn();

$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE stylist_id = ? AND appointment_date = ? AND status != 'cancelled'");
$stmt->execute([$_SESSION['user_id'], $today]);
$apps_today = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT commission_rate FROM staff WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$commission = (float) ($stmt->fetchColumn() ?: 0);

$stmt = $pdo->prepare("SELECT COALESCE(SUM(commission_amount), 0) FROM commissions WHERE stylist_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_commission = (float) ($stmt->fetchColumn() ?: 0);

$notifications = salonFetchNotifications($pdo, $_SESSION['user_id'], 5);

include '../includes/header.php';
?>

<div class="container py-2">
    <div class="flex flex-between mb-2">
        <div>
            <span class="eyebrow">Stylist Portal</span>
            <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h2>
        </div>
        <span class="badge" style="background:var(--color-primary);color:var(--color-black);">Expert Stylist</span>
    </div>

    <div class="dashboard-grid">
        <div class="stat-card text-center"><h3>Appointments Today</h3><div class="stat-value text-gold"><?php echo $apps_today; ?></div></div>
        <div class="stat-card text-center"><h3>Total Bookings</h3><div class="stat-value"><?php echo $total_apps; ?></div></div>
        <div class="stat-card text-center"><h3>Commission Rate</h3><div class="stat-value" style="color: var(--color-success);"><?php echo number_format($commission, 2); ?>%</div></div>
        <div class="stat-card text-center"><h3>Total Commissions</h3><div class="stat-value" style="color: var(--color-info);">$<?php echo number_format($total_commission, 2); ?></div></div>
    </div>

    <div class="dashboard-grid mt-2">
        <div class="detail-card">
            <h3>Notifications</h3>
            <div class="notification-list">
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item">
                        <strong><?php echo htmlspecialchars($notification['title']); ?></strong>
                        <p><?php echo htmlspecialchars($notification['message']); ?></p>
                    </div>
                <?php endforeach; ?>
                <?php if (!$notifications): ?><p>No notifications yet.</p><?php endif; ?>
            </div>
        </div>
        <div class="stat-card" style="text-align:center;"><h3>My Schedule</h3><p>View your assigned appointments in the calendar-based schedule.</p><a href="/salon-management/calendar.php" class="btn btn-primary">Calendar View</a></div>
        <div class="stat-card" style="text-align:center;"><h3>Client Roster</h3><p>Access client preferences and recent service history.</p><a href="/salon-management/clients.php" class="btn btn-outline-gold">View Clients</a></div>
        <div class="stat-card" style="text-align:center;"><h3>Manage Bookings</h3><p>Confirm, complete, or cancel only your own appointments.</p><a href="/salon-management/appointments.php" class="btn btn-outline-gold">View List</a></div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
