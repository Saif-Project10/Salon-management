<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireRole('client');

// Get total appointments
$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments a JOIN clients c ON a.client_id = c.id WHERE c.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_apps = $stmt->fetchColumn();

// Get upcoming appointments
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM appointments a 
    JOIN clients c ON a.client_id = c.id 
    WHERE c.user_id = ? AND a.appointment_date >= CURDATE() AND a.status != 'cancelled'
");
$stmt->execute([$_SESSION['user_id']]);
$upcoming_apps = $stmt->fetchColumn();

include '../includes/header.php';
?>

<div class="container py-2">
    <div class="flex flex-between mb-2">
        <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h2>
        <span class="badge badge-completed">Member</span>
    </div>

    <div class="dashboard-grid">
        <div class="stat-card text-center" style="align-items:center;">
            <h3>Total Visits</h3>
            <div class="stat-value"><?php echo $total_apps; ?></div>
        </div>
        <div class="stat-card text-center" style="align-items:center;">
            <h3>Upcoming Appointments</h3>
            <div class="stat-value text-gold"><?php echo $upcoming_apps; ?></div>
        </div>
    </div>

    <h3 class="mb-1 mt-2">Quick Actions</h3>
    <div class="dashboard-grid">
        <div class="stat-card" style="border-left: none; text-align: center; border-top: 4px solid var(--color-primary);">
            <h3 style="margin-bottom: 20px;">Book a Session</h3>
            <p style="color: #666; margin-bottom: 20px; flex-grow: 1;">Schedule your next luxury treatment with your favorite stylist.</p>
            <a href="/salon-management/appointments.php" class="btn btn-primary" style="width: 100%;">Book Now</a>
        </div>
        
        <div class="stat-card" style="border-left: none; text-align: center; border-top: 4px solid var(--color-primary);">
            <h3 style="margin-bottom: 20px;">My Appointments</h3>
            <p style="color: #666; margin-bottom: 20px; flex-grow: 1;">View your upcoming and past appointment history.</p>
            <a href="/salon-management/appointments.php" class="btn btn-outline-gold" style="width: 100%;">View History</a>
        </div>
        
        <div class="stat-card" style="border-left: none; text-align: center; border-top: 4px solid var(--color-primary);">
            <h3 style="margin-bottom: 20px;">Manage Profile</h3>
            <p style="color: #666; margin-bottom: 20px; flex-grow: 1;">Update your personal information and contact details.</p>
            <a href="/salon-management/user/profile.php" class="btn btn-outline-gold" style="width: 100%;">Edit Profile</a>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
