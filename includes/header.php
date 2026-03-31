<?php
// Ensure session is started and helpers are available.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$base_url = '/salon-management';
$current_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
function navIsActive(string $path): string {
    global $current_path;
    return str_ends_with($current_path, $path) ? 'active' : '';
}
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
        <a href="<?php echo $base_url; ?>/index.php" class="logo-container">
            <svg class="logo-svg" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="50" cy="50" r="46" stroke="#D4AF37" stroke-width="4" />
                <path d="M 30,70 Q 50,15 70,70" fill="transparent" stroke="#111" stroke-width="3" />
                <path d="M 40,70 Q 50,30 60,70" fill="transparent" stroke="#D4AF37" stroke-width="2" />
                <circle cx="50" cy="50" r="8" fill="#111" />
                <polygon points="50,65 55,75 50,85 45,75" fill="#D4AF37" />
            </svg>
            <span class="nav-brand">Elegance<span style="color:var(--color-primary);">.</span></span>
        </a>

        <button class="burger-menu" type="button" aria-label="Toggle menu" aria-expanded="false">&#9776;</button>

        <nav class="nav-menu">
            <a href="<?php echo $base_url; ?>/index.php" class="nav-link <?php echo navIsActive('/index.php'); ?>">Home</a>
            <a href="<?php echo $base_url; ?>/services.php" class="nav-link <?php echo navIsActive('/services.php'); ?>">Services</a>
            <a href="<?php echo $base_url; ?>/stylists.php" class="nav-link <?php echo navIsActive('/stylists.php'); ?>">Stylists</a>
            <a href="<?php echo $base_url; ?>/appointments.php" class="nav-link <?php echo navIsActive('/appointments.php'); ?>">Book Online</a>
            <a href="<?php echo $base_url; ?>/contact.php" class="nav-link <?php echo navIsActive('/contact.php'); ?>">Contact</a>
            
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php 
                $role = currentUserRole();
                $dash_url = dashboardUrlForRole($role);
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
<main class="site-main">
