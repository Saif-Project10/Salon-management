<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$services = $pdo->query("
    SELECT id, name, description, duration, price, category
    FROM services
    ORDER BY category ASC, price ASC, name ASC
")->fetchAll();

include 'includes/header.php';
?>

<section class="page-hero compact">
    <div class="container">
        <span class="eyebrow">Service Menu</span>
        <h1>Choose the treatment that matches your next look.</h1>
        <p>Transparent pricing, clear duration, and direct booking links for every service.</p>
    </div>
</section>

<section class="section-shell">
    <div class="container">
        <div class="lux-grid service-grid-full">
            <?php foreach ($services as $service): ?>
                <article class="lux-card service-card">
                    <span class="pill"><?php echo htmlspecialchars($service['category'] ?: 'Signature Care'); ?></span>
                    <h3><?php echo htmlspecialchars($service['name']); ?></h3>
                    <p><?php echo htmlspecialchars($service['description']); ?></p>
                    <div class="service-meta">
                        <span>$<?php echo number_format($service['price'], 2); ?></span>
                        <span><?php echo (int) $service['duration']; ?> min</span>
                    </div>
                    <a href="/salon-management/appointments.php?service_id=<?php echo $service['id']; ?>" class="btn btn-primary">Book Now</a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
