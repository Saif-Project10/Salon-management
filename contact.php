<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
include 'includes/header.php';
?>

<div class="container py-5">
    <div class="text-center mb-2">
        <h2>Contact Us</h2>
        <p style="color: #666;">We would love to hear from you. Get in touch with our team.</p>
    </div>

    <div class="dashboard-grid">
        <div class="stat-card text-center" style="align-items:center;">
            <h3 style="color: var(--color-primary); font-size: 1.5rem; margin-bottom: 20px;">Address</h3>
            <p>123 Luxury Avenue<br>Suite 1A<br>Beverly Hills, CA 90210</p>
        </div>

        <div class="stat-card text-center" style="align-items:center;">
            <h3 style="color: var(--color-primary); font-size: 1.5rem; margin-bottom: 20px;">Phone</h3>
            <p style="font-size: 1.2rem;">+1 (555) 123-4567</p>
            <p style="color: #666; margin-top: 10px;">Mon - Sat, 9:00 AM - 8:00 PM</p>
        </div>

        <div class="stat-card text-center" style="align-items:center;">
            <h3 style="color: var(--color-primary); font-size: 1.5rem; margin-bottom: 20px;">Email</h3>
            <p style="font-size: 1.1rem;">hello@elegancesalon.com</p>
            <br>
            <a href="feedback.php" class="btn btn-outline-gold mt-1">Send us a Message</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
