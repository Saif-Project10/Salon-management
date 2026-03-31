<?php
// Ensure session is started and helpers are available.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Assume auth.php is included before header.php in most files.
// Define base URL for absolute paths
$base_url = '/salon-management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elegance Salon Management System</title>
    <!-- Use absolute path so it works from subdirectories -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/style.css">
    <script src="<?php echo $base_url; ?>/assets/js/script.js" defer></script>
</head>
<body>

<header class="header">
    <div class="container nav-container">
        <!-- Brand / Logo (SVG Minimal Luxury Gold and Black) -->
        <a href="<?php echo $base_url; ?>/index.php" class="logo-container">
            <svg class="logo-svg" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                <!-- Outer Ring (Gold) -->
                <circle cx="50" cy="50" r="46" stroke="#D4AF37" stroke-width="4" />
                <!-- Inner Element (Black and Gold) -->
                <path d="M 30,70 Q 50,15 70,70" fill="transparent" stroke="#111" stroke-width="3" />
                <path d="M 40,70 Q 50,30 60,70" fill="transparent" stroke="#D4AF37" stroke-width="2" />
                <circle cx="50" cy="50" r="8" fill="#111" />
                <!-- Small elegant diamond cut at bottom -->
                <polygon points="50,65 55,75 50,85 45,75" fill="#D4AF37" />
            </svg>
            <span class="nav-brand">Elegance<span style="color:var(--color-primary);">.</span></span>
        </a>

        <!-- Burger Menu for Mobile -->
        <div class="burger-menu">&#9776;</div>

        <!-- Navigation Links -->
        <nav class="nav-menu">
            <a href="<?php echo $base_url; ?>/index.php" class="nav-link">Home</a>
            <a href="<?php echo $base_url; ?>/appointments.php" class="nav-link">Book Online</a>
            <a href="<?php echo $base_url; ?>/contact.php" class="nav-link">Contact</a>
            
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php 
                $role = $_SESSION['user_role'] ?? 'user';
                $dash_url = $base_url . '/user/dashboard.php';
                if ($role === 'admin' || $role === 'receptionist') {
                    $dash_url = $base_url . '/admin/dashboard.php';
                } elseif ($role === 'stylist') {
                    $dash_url = $base_url . '/stylist/dashboard.php';
                }
                ?>
                <a href="<?php echo $dash_url; ?>" class="nav-link">Dashboard (<?php echo ucfirst($role); ?>)</a>
                <a href="<?php echo $base_url; ?>/logout.php" class="nav-link text-gold" style="font-weight:600;">Logout</a>
            <?php else: ?>
                <a href="<?php echo $base_url; ?>/login.php" class="nav-link">Login</a>
                <a href="<?php echo $base_url; ?>/register.php" class="btn btn-primary" style="margin-left:15px; padding: 8px 20px;">Sign Up</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container py-2" style="flex:1;">
