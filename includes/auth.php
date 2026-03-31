<?php
session_start();

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Redirect to login if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        // Since we include this in many files (like /admin/dashboard.php), 
        // we use an absolute path relative to web root or just a simple relative path logic.
        // Assuming /salon-management/ is the web root path
        header("Location: /salon-management/login.php");
        exit();
    }
}

// Check if user has a specific role
function hasRole($role) {
    if(is_array($role)) {
        return isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], $role);
    }
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

// Require a specific role (or array of roles) to access a page
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        // Unauthorized access
        header("Location: /salon-management/index.php?error=unauthorized");
        exit();
    }
}

// Redirect logged in users to their respective dashboards
function redirectLoggedInUser() {
    if (isLoggedIn()) {
        $role = $_SESSION['user_role'];
        if ($role === 'admin' || $role === 'receptionist') {
            header("Location: /salon-management/admin/dashboard.php");
        } elseif ($role === 'stylist') {
            header("Location: /salon-management/stylist/dashboard.php");
        } else {
            header("Location: /salon-management/user/dashboard.php");
        }
        exit();
    }
}

// Helper function to show alerts
function showAlert() {
    if (isset($_SESSION['success'])) {
        echo "<div class='alert alert-success'>" . htmlspecialchars($_SESSION['success']) . "</div>";
        unset($_SESSION['success']);
    }
    if (isset($_SESSION['error'])) {
        echo "<div class='alert alert-error'>" . htmlspecialchars($_SESSION['error']) . "</div>";
        unset($_SESSION['error']);
    }
}
?>
