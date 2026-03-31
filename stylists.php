<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$stylists = $pdo->query("
    SELECT u.id, u.name, u.avatar, st.specialization, st.experience_years, st.bio, st.services
    FROM users u
    JOIN staff st ON st.user_id = u.id
    WHERE u.role = 'stylist'
    ORDER BY st.experience_years DESC, u.name ASC
")->fetchAll();

$serviceMap = $pdo->query("SELECT id, name FROM services ORDER BY name ASC")->fetchAll(PDO::FETCH_KEY_PAIR);

include 'includes/header.php';
?>

<section class="page-hero compact dark">
    <div class="container">
        <span class="eyebrow">Stylist Profiles</span>
        <h1>Find the stylist whose specialty fits your service.</h1>
        <p>Compare focus areas, experience, and assigned services before you reserve your appointment.</p>
    </div>
</section>

<section class="section-shell">
    <div class="container">
        <div class="lux-grid stylist-grid-full">
            <?php foreach ($stylists as $stylist): ?>
                <?php
                $assignedServices = array_filter(array_map('trim', explode(',', (string) $stylist['services'])));
                $labels = [];
                foreach ($assignedServices as $serviceId) {
                    if (isset($serviceMap[$serviceId])) {
                        $labels[] = $serviceMap[$serviceId];
                    }
                }
                ?>
                <article class="lux-card stylist-profile-card">
                    <img src="<?php echo htmlspecialchars($stylist['avatar'] ?: '/salon-management/assets/images/stylist-default.svg'); ?>" alt="<?php echo htmlspecialchars($stylist['name']); ?>">
                    <div class="stylist-copy">
                        <span class="pill"><?php echo (int) $stylist['experience_years']; ?>+ years experience</span>
                        <h3><?php echo htmlspecialchars($stylist['name']); ?></h3>
                        <p class="text-gold"><?php echo htmlspecialchars($stylist['specialization'] ?: 'Specialization not set yet'); ?></p>
                        <p><?php echo htmlspecialchars($stylist['bio'] ?: 'This stylist delivers polished finishing, guided consultations, and premium salon care.'); ?></p>
                        <div class="tag-row">
                            <?php foreach ($labels as $label): ?>
                                <span class="tag"><?php echo htmlspecialchars($label); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <a href="/salon-management/appointments.php?stylist_id=<?php echo $stylist['id']; ?>" class="btn btn-primary">Select Stylist</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
