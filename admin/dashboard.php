<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireRole(['admin', 'receptionist']); // Receptionist can also see dashboard

// Fetch statistics
$today = date('Y-m-d');

// Total Clients
$stmt = $pdo->query("SELECT COUNT(*) FROM clients");
$total_clients = $stmt->fetchColumn();

// Appointments Today
$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date = ?");
$stmt->execute([$today]);
$appointments_today = $stmt->fetchColumn();

// Low Stock Alert
$stmt = $pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity <= min_stock");
$low_stock = $stmt->fetchColumn();

// Total Revenue Today
$stmt = $pdo->prepare("SELECT SUM(amount) FROM payments p JOIN appointments a ON p.appointment_id = a.id WHERE a.appointment_date = ? AND p.payment_status = 'completed'");
$stmt->execute([$today]);
$revenue_today = $stmt->fetchColumn() ?: 0;

include '../includes/header.php';
?>

<div class="container py-2">
    <div class="flex flex-between mb-2">
        <h2>Dashboard Overview</h2>
        <div>
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
        <div class="stat-card" style="<?php echo $low_stock > 0 ? 'border-left-color: #dc3545;' : ''; ?>">
            <h3>Low Stock Items</h3>
            <div class="stat-value" style="<?php echo $low_stock > 0 ? 'color: #dc3545;' : ''; ?>"><?php echo $low_stock; ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #28a745;">
            <h3>Revenue Today</h3>
            <div class="stat-value" style="color: #28a745;">$<?php echo number_format($revenue_today, 2); ?></div>
        </div>
    </div>

    <!-- Quick Actions Menu -->
    <div class="dashboard-grid mt-2">
        <div class="stat-card" style="border-left: none; text-align: center; border-top: 4px solid var(--color-primary);">
            <h3 style="margin-bottom: 20px;">User Management</h3>
            <p style="color: #666; margin-bottom: 20px; flex-grow: 1;">Manage administrative staff, receptionists, and user roles.</p>
            <a href="/salon-management/admin/manage_users.php" class="btn btn-outline-gold" style="width: 100%;">Manage Users</a>
        </div>
        <div class="stat-card" style="border-left: none; text-align: center; border-top: 4px solid var(--color-primary);">
            <h3 style="margin-bottom: 20px;">Inventory Control</h3>
            <p style="color: #666; margin-bottom: 20px; flex-grow: 1;">Track product stock, add new items, and monitor usage.</p>
            <a href="/salon-management/inventory.php" class="btn btn-outline-gold" style="width: 100%;">Manage Inventory</a>
        </div>
        <div class="stat-card" style="border-left: none; text-align: center; border-top: 4px solid var(--color-primary);">
            <h3 style="margin-bottom: 20px;">Staff & Services</h3>
            <p style="color: #666; margin-bottom: 20px; flex-grow: 1;">Assign services to stylists and view their performance.</p>
            <a href="/salon-management/staff.php" class="btn btn-outline-gold" style="width: 100%;">Manage Staff</a>
        </div>
        <div class="stat-card" style="border-left: none; text-align: center; border-top: 4px solid var(--color-primary);">
            <h3 style="margin-bottom: 20px;">Client CRM</h3>
            <p style="color: #666; margin-bottom: 20px; flex-grow: 1;">View client histories, add new clients, and manage preferences.</p>
            <a href="/salon-management/clients.php" class="btn btn-outline-gold" style="width: 100%;">View Clients</a>
        </div>
        <div class="stat-card" style="border-left: none; text-align: center; border-top: 4px solid var(--color-primary);">
            <h3 style="margin-bottom: 20px;">System Reports</h3>
            <p style="color: #666; margin-bottom: 20px; flex-grow: 1;">Generate analytics for sales, appointments, and performance.</p>
            <a href="/salon-management/admin/reports.php" class="btn btn-outline-gold" style="width: 100%;">View Reports</a>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
