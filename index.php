<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Fetch services to display on the homepage
$stmt = $pdo->query("SELECT * FROM services LIMIT 6");
$services = $stmt->fetchAll();

include 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero text-center">
    <div class="container">
        <h1>Experience True Elegance</h1>
        <p>Your premier destination for luxury hair, beauty, and relaxation.</p>
        <a href="/salon-management/appointments.php" class="btn btn-primary">Book an Appointment</a>
        <?php if(!isLoggedIn()): ?>
            <a href="/salon-management/login.php" class="btn btn-outline-gold" style="margin-left: 10px;">Staff / Client Login</a>
        <?php endif; ?>
    </div>
</section>

<!-- Services Preview Section -->
<section class="container py-5">
    <div class="text-center mb-2">
        <h2>Our Premium Services</h2>
        <p style="color: var(--color-dark-grey); max-width: 600px; margin: 0 auto;">Discover our tailor-made treatments designed to elevate your style and replenish your confidence.</p>
    </div>

    <div class="dashboard-grid mt-2">
        <?php if (count($services) > 0): ?>
            <?php foreach ($services as $service): ?>
                <div class="stat-card" style="align-items: center; text-align: center;">
                    <div style="font-size: 2rem; color: var(--color-primary); margin-bottom: 10px;">&#10024;</div>
                    <h3 style="margin-bottom: 10px; font-size: 1.2rem;"><?php echo htmlspecialchars($service['name']); ?></h3>
                    <p style="color: #666; font-size: 0.9rem; margin-bottom: 15px; flex-grow: 1;">
                        <?php echo htmlspecialchars($service['description']); ?>
                    </p>
                    <div style="font-weight: bold; font-size: 1.1rem; color: var(--color-black); margin-bottom: 15px;">
                        $<?php echo number_format($service['price'], 2); ?> 
                        <span style="font-size: 0.8rem; color: #888; font-weight: normal;">/ <?php echo $service['duration']; ?> mins</span>
                    </div>
                    <a href="/salon-management/appointments.php?service_id=<?php echo $service['id']; ?>" class="btn btn-outline-gold" style="width: 100%;">Book Now</a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-center" style="grid-column: 1 / -1;">No services available at the moment.</p>
        <?php endif; ?>
    </div>
</section>

<!-- Call to Action -->
<section style="background-color: var(--color-black); color: var(--color-white); padding: 60px 0; text-align: center;">
    <div class="container">
        <h2 style="color: var(--color-white);">Ready to Transform Your Look?</h2>
        <p style="color: #ccc; margin-bottom: 30px;">Join our exclusive clientele and experience the elegance you deserve.</p>
        <a href="/salon-management/register.php" class="btn btn-primary">Become a Member</a>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
