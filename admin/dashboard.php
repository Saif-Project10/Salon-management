<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireRole('admin');

$today = date('Y-m-d');

$total_clients = (int) $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date = ? AND status != 'cancelled'");
$stmt->execute([$today]);
$appointments_today = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity <= min_stock");
$low_stock = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT SUM(amount) FROM payments p JOIN appointments a ON p.appointment_id = a.id WHERE a.appointment_date = ? AND p.payment_status = 'completed'");
$stmt->execute([$today]);
$revenue_today = (float) ($stmt->fetchColumn() ?: 0);

$stmt = $pdo->prepare("SELECT SUM(commission_amount) FROM commissions WHERE DATE(created_at) = ?");
$stmt->execute([$today]);
$commission_today = (float) ($stmt->fetchColumn() ?: 0);

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

$low_stock_items = $pdo->query("
    SELECT product_name, quantity, min_stock, supplier
    FROM inventory
    WHERE quantity <= min_stock
    ORDER BY quantity ASC, product_name ASC
    LIMIT 6
")->fetchAll();

$top_commissions = $pdo->query("
    SELECT u.name AS stylist_name, COUNT(c.id) AS completed_services, COALESCE(SUM(c.commission_amount), 0) AS earned
    FROM users u
    LEFT JOIN commissions c ON c.stylist_id = u.id
    WHERE u.role = 'stylist'
    GROUP BY u.id, u.name
    ORDER BY earned DESC, completed_services DESC
    LIMIT 5
")->fetchAll();

$notifications = salonFetchNotifications($pdo, $_SESSION['user_id'], 6);
$reminder_stats = salonFetchReminderStats($pdo);
$google_connected = salonHasGoogleCalendarConnection($pdo, (int) $_SESSION['user_id']);

include '../includes/header.php';
?>

<div class="container py-2">
    <div class="flex flex-between mb-2">
        <div>
            <span class="eyebrow">Operations Dashboard</span>
            <h2>Salon control center</h2>
        </div>
        <div style="display:flex; gap: 15px; align-items:center;">
            <a href="profile.php" class="btn btn-outline-gold" style="padding: 10px 20px;">Profile Settings</a>
            <span class="badge badge-completed">Access Level: <?php echo ucfirst($_SESSION['user_role']); ?></span>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="stat-card">
            <h3>Total Clients</h3>
            <div class="stat-value"><?php echo $total_clients; ?></div>
        </div>
        <div class="stat-card">
            <h3>Appointments Today</h3>
            <div class="stat-value text-gold"><?php echo $appointments_today; ?></div>
        </div>
        <div class="stat-card">
            <h3>Revenue Today</h3>
            <div class="stat-value" style="color: var(--color-success);">$<?php echo number_format($revenue_today, 2); ?></div>
        </div>
        <div class="stat-card">
            <h3>Commission Today</h3>
            <div class="stat-value" style="color: var(--color-info);">$<?php echo number_format($commission_today, 2); ?></div>
        </div>
        <div class="stat-card">
            <h3>Reminders Today</h3>
            <div class="stat-value" style="color: var(--color-success);"><?php echo $reminder_stats['today']; ?></div>
        </div>
    </div>

    <div class="dashboard-grid mt-2">
        <div class="detail-card" style="grid-column: span 2;">
            <span class="eyebrow">Smart Calendar</span>
            <h3>Today's appointments</h3>
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
            <span class="eyebrow">Inventory Alerts</span>
            <h3>Low stock warnings</h3>
            <div class="notification-list">
                <?php foreach ($low_stock_items as $item): ?>
                    <div class="notification-item">
                        <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                        <p><?php echo (int) $item['quantity']; ?> left, minimum <?php echo (int) $item['min_stock']; ?></p>
                        <small><?php echo htmlspecialchars($item['supplier'] ?: 'No supplier'); ?></small>
                    </div>
                <?php endforeach; ?>
                <?php if (!$low_stock_items): ?>
                    <p>All products are above the low-stock threshold.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="dashboard-grid mt-2">
        <div class="detail-card">
            <span class="eyebrow">Commission Calculator</span>
            <h3>Top stylist earnings</h3>
            <div class="history-list">
                <?php foreach ($top_commissions as $row): ?>
                    <div class="history-item">
                        <strong><?php echo htmlspecialchars($row['stylist_name']); ?></strong>
                        <div><?php echo (int) $row['completed_services']; ?> commission entries</div>
                        <small>$<?php echo number_format((float) $row['earned'], 2); ?> earned</small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="detail-card">
            <span class="eyebrow">Notifications</span>
            <h3>Front desk alerts</h3>
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

        <div class="detail-card">
            <span class="eyebrow">Scheduled Reminders</span>
            <h3>Delivery overview</h3>
            <div class="history-list">
                <div class="history-item">
                    <strong><?php echo $reminder_stats['today']; ?></strong>
                    <div>sent today</div>
                </div>
                <div class="history-item">
                    <strong><?php echo $reminder_stats['week']; ?></strong>
                    <div>sent in the last 7 days</div>
                </div>
                <div class="history-item">
                    <strong><?php echo $reminder_stats['failed']; ?></strong>
                    <div>failed in the last 7 days</div>
                </div>
            </div>
            <a href="/salon-management/google_calendar_auth.php" class="btn btn-outline-gold" style="margin-top:12px;">
                <?php echo $google_connected ? 'Reconnect Google Calendar' : 'Connect Google Calendar'; ?>
            </a>
        </div>

        <div class="detail-card">
            <span class="eyebrow">Quick Actions</span>
            <h3>Manage operations</h3>
            <div class="history-list">
                <a href="/salon-management/admin/manage_content.php" class="btn btn-outline-gold">Manage Content</a>
                <a href="/salon-management/admin/manage_users.php" class="btn btn-outline-gold">Manage Users</a>
                <a href="/salon-management/admin/manage_services.php" class="btn btn-outline-gold">Manage Services</a>
                <a href="/salon-management/inventory.php" class="btn btn-outline-gold">Inventory Control</a>
                <a href="/salon-management/admin/manage_service_inventory.php" class="btn btn-outline-gold">Service Inventory</a>
                <a href="/salon-management/purchase_orders.php" class="btn btn-outline-gold">Purchase Orders</a>
                <a href="/salon-management/admin/sms_settings.php" class="btn btn-outline-gold">SMS Settings</a>
                <a href="/salon-management/staff.php" class="btn btn-outline-gold">Staff Scheduling</a>
                <a href="/salon-management/tasks.php" class="btn btn-outline-gold">Task Board</a>
                <a href="/salon-management/clients.php" class="btn btn-outline-gold">Client Histories</a>
                <a href="/salon-management/admin/reports.php" class="btn btn-primary">Reports</a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
