<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireRole('admin');

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
</div>

<?php include '../includes/footer.php'; ?>
