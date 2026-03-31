<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
include 'includes/header.php';
?>

<div class="container py-5">
    <div class="text-center mb-2">
        <span class="eyebrow">Developer Contact</span>
        <h2>Contact the Organization Developing the Application</h2>
        <p style="color: #666;">For support, customization, maintenance, or deployment inquiries, reach out to the development organization behind this salon management platform.</p>
    </div>

    <div class="dashboard-grid">
        <div class="stat-card text-center" style="align-items:center;">
            <h3 style="color: var(--color-primary); font-size: 1.5rem; margin-bottom: 20px;">Organization</h3>
            <p>Elegance Digital Solutions<br>Custom Web Systems Division<br>Karachi, Pakistan</p>
        </div>

        <div class="stat-card text-center" style="align-items:center;">
            <h3 style="color: var(--color-primary); font-size: 1.5rem; margin-bottom: 20px;">Phone</h3>
            <p style="font-size: 1.2rem;">+92 300 1234567</p>
            <p style="color: #666; margin-top: 10px;">Mon - Fri, 10:00 AM - 6:00 PM</p>
        </div>

        <div class="stat-card text-center" style="align-items:center;">
            <h3 style="color: var(--color-primary); font-size: 1.5rem; margin-bottom: 20px;">Email</h3>
            <p style="font-size: 1.1rem;">support@elegancedigital.dev</p>
            <br>
            <a href="feedback.php" class="btn btn-outline-gold mt-1">Send Feedback</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
