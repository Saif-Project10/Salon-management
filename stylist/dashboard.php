<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireRole('stylist');

// Get total appointments assigned
$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE stylist_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_apps = $stmt->fetchColumn();

// Get upcoming appointments today
$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM appointments  
    WHERE stylist_id = ? AND appointment_date = ? AND status != 'cancelled'
");
$stmt->execute([$_SESSION['user_id'], $today]);
$apps_today = $stmt->fetchColumn();

// Get commission info
$stmt = $pdo->prepare("SELECT commission_rate FROM staff WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$staffData = $stmt->fetch();
$commission = $staffData ? $staffData['commission_rate'] : 0.00;

include '../includes/header.php';
?>

<div class="container py-2">
    <div class="flex flex-between mb-2">
        <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h2>
        <span class="badge" style="background:var(--color-primary);color:var(--color-black);">Expert Stylist</span>
    </div>

    <div class="dashboard-grid">
        <div class="stat-card text-center" style="align-items:center;">
            <h3>Appointments Today</h3>
            <div class="stat-value text-gold"><?php echo $apps_today; ?></div>
        </div>
        <div class="stat-card text-center" style="align-items:center;">
            <h3>Total Lifetime Bookings</h3>
            <div class="stat-value"><?php echo $total_apps; ?></div>
        </div>
        <div class="stat-card text-center" style="align-items:center;">
            <h3>Commission Rate</h3>
            <div class="stat-value" style="color: #28a745;"><?php echo number_format($commission, 2); ?>%</div>
        </div>
    </div>

    <h3 class="mb-1 mt-2">Stylist Portal</h3>
    <div class="dashboard-grid">
        <div class="stat-card" style="border-left: none; text-align: center; border-top: 4px solid var(--color-primary);">
            <h3 style="margin-bottom: 20px;">My Schedule</h3>
            <p style="color: #666; margin-bottom: 20px; flex-grow: 1;">View your upcoming calendar and daily bookings.</p>
            <a href="/salon-management/calendar.php" class="btn btn-primary" style="width: 100%;">Calendar View</a>
        </div>
        
        <div class="stat-card" style="border-left: none; text-align: center; border-top: 4px solid var(--color-primary);">
            <h3 style="margin-bottom: 20px;">Client Roster</h3>
            <p style="color: #666; margin-bottom: 20px; flex-grow: 1;">Access client histories, formulas, and treatment notes.</p>
            <a href="/salon-management/clients.php" class="btn btn-outline-gold" style="width: 100%;">View Clients</a>
        </div>
        
        <div class="stat-card" style="border-left: none; text-align: center; border-top: 4px solid var(--color-primary);">
            <h3 style="margin-bottom: 20px;">Manage Bookings</h3>
            <p style="color: #666; margin-bottom: 20px; flex-grow: 1;">Update statuses or cancel specific appointment slots.</p>
            <a href="/salon-management/appointments.php" class="btn btn-outline-gold" style="width: 100%;">View List</a>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
