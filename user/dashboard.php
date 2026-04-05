<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireRole('client');

$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments a JOIN clients c ON a.client_id = c.id WHERE c.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_apps = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments a JOIN clients c ON a.client_id = c.id WHERE c.user_id = ? AND a.appointment_date >= CURDATE() AND a.status != 'cancelled'");
$stmt->execute([$_SESSION['user_id']]);
$upcoming_apps = (int) $stmt->fetchColumn();

$notifications = salonFetchNotifications($pdo, $_SESSION['user_id'], 5);
$google_connected = salonHasGoogleCalendarConnection($pdo, (int) $_SESSION['user_id']);

include '../includes/header.php';
?>

<div class="container py-2">
    <div class="flex flex-between mb-2">
        <div>
            <span class="eyebrow">Client Dashboard</span>
            <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h2>
        </div>
        <span class="badge badge-completed">Member</span>
    </div>

    <div class="dashboard-grid">
        <div class="stat-card text-center"><h3>Total Visits</h3><div class="stat-value"><?php echo $total_apps; ?></div></div>
        <div class="stat-card text-center"><h3>Upcoming Appointments</h3><div class="stat-value text-gold"><?php echo $upcoming_apps; ?></div></div>
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
    </div>

    <div class="dashboard-grid mt-2">
        <div class="stat-card" style="text-align:center;"><h3>Book a Session</h3><p>Schedule your next luxury treatment with your favorite stylist.</p><a href="/salon-management/appointments.php" class="btn btn-primary">Book Now</a></div>
        <div class="stat-card" style="text-align:center;"><h3>My Appointments</h3><p>Review previous services, statuses, and reminders.</p><a href="/salon-management/appointments.php" class="btn btn-outline-gold">View History</a></div>
        <div class="stat-card" style="text-align:center;"><h3>Manage Profile</h3><p>Keep your contact details up to date for confirmations and reminders.</p><a href="/salon-management/user/profile.php" class="btn btn-outline-gold">Edit Profile</a></div>
        <div class="stat-card" style="text-align:center;"><h3>Google Calendar</h3><p>Sync your salon bookings with your connected calendar account.</p><a href="/salon-management/google_calendar_auth.php" class="btn btn-outline-gold"><?php echo $google_connected ? 'Reconnect Calendar' : 'Connect Calendar'; ?></a></div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
