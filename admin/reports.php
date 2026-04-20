<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireRole('admin');

// Staff Performance Date Range Filter (default: current month to today)
$staffStartDate = $_GET['start_date'] ?? date('Y-m-01');
$staffEndDate = $_GET['end_date'] ?? date('Y-m-d');

$isValidDate = static function (string $value): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }
    [$y, $m, $d] = array_map('intval', explode('-', $value));
    return checkdate($m, $d, $y);
};

if (!$isValidDate($staffStartDate)) {
    $staffStartDate = date('Y-m-01');
}
if (!$isValidDate($staffEndDate)) {
    $staffEndDate = date('Y-m-d');
}
if ($staffStartDate > $staffEndDate) {
    $tmp = $staffStartDate;
    $staffStartDate = $staffEndDate;
    $staffEndDate = $tmp;
}

// Upcoming Appointments
$upcoming = $pdo->query("
    SELECT a.id, a.appointment_date, a.appointment_time, a.status, 
           c.name as client_name, s.name as service_name, u.name as stylist_name 
    FROM appointments a
    JOIN clients c ON a.client_id = c.id
    JOIN services s ON a.service_id = s.id
    JOIN users u ON a.stylist_id = u.id
    WHERE a.appointment_date >= CURDATE()
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 10
")->fetchAll();

// Recent Payments
$payments = $pdo->query("
    SELECT p.id, p.amount, p.payment_method, p.payment_status, p.created_at,
           c.name as client_name
    FROM payments p
    JOIN appointments a ON p.appointment_id = a.id
    JOIN clients c ON a.client_id = c.id
    ORDER BY p.created_at DESC
    LIMIT 10
")->fetchAll();

// Monthly Revenue Summary (Current Year)
$year = date('Y');
$revenueData = $pdo->prepare("
    SELECT MONTH(created_at) as m, SUM(amount) as total
    FROM payments 
    WHERE YEAR(created_at) = ? AND payment_status = 'completed'
    GROUP BY MONTH(created_at)
    ORDER BY m
");
$revenueData->execute([$year]);
$revRows = $revenueData->fetchAll();
$monthly = array_fill(1, 12, 0);
foreach($revRows as $r) {
    $monthly[(int)$r['m']] = (float)$r['total'];
}

$popularServices = $pdo->query("
    SELECT s.name, COUNT(a.id) AS bookings
    FROM appointments a
    JOIN services s ON s.id = a.service_id
    WHERE a.status != 'cancelled'
    GROUP BY s.id, s.name
    ORDER BY bookings DESC
    LIMIT 5
")->fetchAll();

$peakHours = $pdo->query("
    SELECT HOUR(appointment_time) AS booking_hour, COUNT(*) AS total
    FROM appointments
    WHERE status != 'cancelled'
    GROUP BY HOUR(appointment_time)
    ORDER BY total DESC, booking_hour ASC
    LIMIT 5
")->fetchAll();

$inventoryUsage = $pdo->query("
    SELECT product_name, quantity, min_stock,
           GREATEST(min_stock - quantity, 0) AS shortage
    FROM inventory
    ORDER BY shortage DESC, quantity ASC
    LIMIT 5
")->fetchAll();

$staffPerformanceStmt = $pdo->prepare("
    SELECT
        u.name AS stylist_name,
        COALESCE(ap.total_bookings, 0) AS total_bookings,
        COALESCE(ap.completed_bookings, 0) AS completed_bookings,
        COALESCE(cm.total_commission, 0) AS total_commission
    FROM users u
    LEFT JOIN (
        SELECT
            stylist_id,
            COUNT(id) AS total_bookings,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_bookings
        FROM appointments
        WHERE appointment_date BETWEEN ? AND ?
        GROUP BY stylist_id
    ) ap ON ap.stylist_id = u.id
    LEFT JOIN (
        SELECT
            a.stylist_id,
            SUM(c.commission_amount) AS total_commission
        FROM commissions c
        JOIN appointments a ON a.id = c.appointment_id
        WHERE a.appointment_date BETWEEN ? AND ?
        GROUP BY a.stylist_id
    ) cm ON cm.stylist_id = u.id
    WHERE u.role = 'stylist'
    ORDER BY completed_bookings DESC, total_bookings DESC
    LIMIT 8
");
$staffPerformanceStmt->execute([$staffStartDate, $staffEndDate, $staffStartDate, $staffEndDate]);
$staffPerformance = $staffPerformanceStmt->fetchAll();

include '../includes/header.php';
?>

<div class="container py-2">
    <div class="flex flex-between mb-2">
        <h2>System Reports Center</h2>
        <div>
            <button id="print-btn" class="btn btn-primary">Print Report</button>
            <a href="/salon-management/admin/dashboard.php" class="btn btn-outline-gold">&larr; Dashboard</a>
        </div>
    </div>

    <!-- Monthly Revenue Summary -->
    <div class="form-card mb-2" style="max-width: 100%; display: flex; flex-direction: column; align-items: stretch;">
        <h3 class="mb-1">Monthly Revenue (<?php echo $year; ?>)</h3>
        <div style="display: flex; height: 200px; align-items: flex-end; gap: 10px; padding-bottom: 20px; border-bottom: 1px solid #ddd;">
            <?php 
            $maxRev = max(1, max($monthly)); // Prevent division by zero
            $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            for($i = 1; $i <= 12; $i++): 
                $height = ($monthly[$i] / $maxRev) * 100;
            ?>
                <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end;">
                    <span style="font-size: 0.7rem; color: #666; margin-bottom: 5px;">$<?php echo round($monthly[$i]); ?></span>
                    <div style="width: 100%; background-color: var(--color-primary); height: <?php echo max(5, $height); ?>%;"></div>
                    <span style="font-size: 0.8rem; margin-top: 5px;"><?php echo $months[$i-1]; ?></span>
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <div class="dashboard-grid">
        <!-- Upcoming Appointments -->
        <div class="table-responsive">
            <h3 class="mb-1">Upcoming Appointments</h3>
            <?php if (count($upcoming) > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Client</th>
                        <th>Service</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcoming as $app): ?>
                    <tr>
                        <td>
                            <strong><?php echo date('M d', strtotime($app['appointment_date'])); ?></strong><br>
                            <?php echo date('h:i A', strtotime($app['appointment_time'])); ?>
                        </td>
                        <td><?php echo htmlspecialchars($app['client_name']); ?></td>
                        <td><?php echo htmlspecialchars($app['service_name']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p>No upcoming appointments found.</p>
            <?php endif; ?>
        </div>

        <!-- Recent Payments -->
        <div class="table-responsive">
            <h3 class="mb-1">Recent Transactions</h3>
            <?php if (count($payments) > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Method</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($p['client_name']); ?><br>
                            <small style="color:#888;"><?php echo date('M d, g:i A', strtotime($p['created_at'])); ?></small>
                        </td>
                        <td style="font-weight: bold;">$<?php echo number_format($p['amount'], 2); ?></td>
                        <td>
                            <?php if($p['payment_status'] === 'completed'): ?>
                                <span class="badge badge-completed">Paid</span>
                            <?php else: ?>
                                <span class="badge badge-pending">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($p['payment_method'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p>No transactions found.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-grid mt-2">
        <div class="detail-card">
            <h3 class="mb-1">Popular Services</h3>
            <div class="history-list">
                <?php foreach ($popularServices as $service): ?>
                    <div class="history-item">
                        <strong><?php echo htmlspecialchars($service['name']); ?></strong>
                        <small><?php echo (int) $service['bookings']; ?> total bookings</small>
                    </div>
                <?php endforeach; ?>
                <?php if (!$popularServices): ?><p>No service trends yet.</p><?php endif; ?>
            </div>
        </div>

        <div class="detail-card">
            <h3 class="mb-1">Peak Booking Hours</h3>
            <div class="history-list">
                <?php foreach ($peakHours as $hour): ?>
                    <div class="history-item">
                        <strong><?php echo date('g:00 A', strtotime(sprintf('%02d:00:00', $hour['booking_hour']))); ?></strong>
                        <small><?php echo (int) $hour['total']; ?> bookings</small>
                    </div>
                <?php endforeach; ?>
                <?php if (!$peakHours): ?><p>No booking hour data available.</p><?php endif; ?>
            </div>
        </div>

        <div class="detail-card">
            <h3 class="mb-1">Inventory Usage Trend</h3>
            <div class="history-list">
                <?php foreach ($inventoryUsage as $item): ?>
                    <div class="history-item">
                        <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                        <small><?php echo (int) $item['quantity']; ?> in stock | minimum <?php echo (int) $item['min_stock']; ?></small>
                    </div>
                <?php endforeach; ?>
                <?php if (!$inventoryUsage): ?><p>No inventory items found.</p><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="table-responsive mt-2">
        <h3 class="mb-1">Staff Performance</h3>
        <form method="GET" class="filter-row mb-1" style="align-items:flex-end;">
            <div class="form-group" style="margin-bottom:0;">
                <label>Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($staffStartDate); ?>">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label>End Date</label>
                <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($staffEndDate); ?>">
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
        </form>
        <small style="color:#888; display:block; margin-bottom:10px;">
            Showing data from <?php echo date('M d, Y', strtotime($staffStartDate)); ?> to <?php echo date('M d, Y', strtotime($staffEndDate)); ?>.
        </small>
        <?php if ($staffPerformance): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Stylist</th>
                    <th>Total Bookings</th>
                    <th>Completed</th>
                    <th>Commission Earned</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staffPerformance as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['stylist_name']); ?></td>
                        <td><?php echo (int) $row['total_bookings']; ?></td>
                        <td><?php echo (int) $row['completed_bookings']; ?></td>
                        <td>$<?php echo number_format((float) $row['total_commission'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p>No staff performance data found.</p>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
