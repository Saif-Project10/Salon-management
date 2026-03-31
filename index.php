<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$featuredServices = $pdo->query("
    SELECT id, name, description, duration, price, category
    FROM services
    ORDER BY price DESC, duration DESC
    LIMIT 6
")->fetchAll();

$stylists = $pdo->query("
    SELECT u.id, u.name, u.avatar, st.specialization, st.experience_years, st.bio
    FROM users u
    JOIN staff st ON st.user_id = u.id
    WHERE u.role = 'stylist'
    ORDER BY st.experience_years DESC, u.name ASC
    LIMIT 3
")->fetchAll();

$reviews = $pdo->query("
    SELECT r.rating, r.review_text, r.created_at, COALESCE(c.name, 'Guest Client') AS client_name
    FROM reviews r
    LEFT JOIN clients c ON c.id = r.client_id
    WHERE r.status = 'published'
    ORDER BY r.created_at DESC
    LIMIT 3
")->fetchAll();

include 'includes/header.php';
?>

<section class="hero-banner">
    <div class="hero-backdrop"></div>
    <div class="container hero-grid">
        <div class="hero-copy">
            <span class="eyebrow">Luxury salon experience</span>
            <h1>Precision beauty, elevated service, and effortless online booking.</h1>
            <p>From signature cuts to bridal preparation, Elegance pairs premium care with a polished digital experience for clients, stylists, and front-desk staff.</p>
            <div class="hero-actions">
                <a href="/salon-management/appointments.php" class="btn btn-primary">Book Now</a>
                <a href="/salon-management/services.php" class="btn btn-outline-gold">View Services</a>
            </div>
            <div class="hero-metrics">
                <div class="metric-chip">
                    <strong>30 min</strong>
                    <span>slot planning</span>
                </div>
                <div class="metric-chip">
                    <strong>Expert stylists</strong>
                    <span>tailored specializations</span>
                </div>
                <div class="metric-chip">
                    <strong>Smart reminders</strong>
                    <span>simulated confirmations</span>
                </div>
            </div>
        </div>
        <div class="hero-gallery">
            <article class="hero-card tall">
                <img src="/salon-management/assets/images/hero-salon-main.svg" alt="Luxury salon interior">
            </article>
            <article class="hero-card">
                <img src="/salon-management/assets/images/hero-stylist.svg" alt="Professional stylist at work">
            </article>
            <article class="hero-card accent">
                <img src="/salon-management/assets/images/hero-beauty.svg" alt="Beauty and salon treatment setting">
            </article>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="container">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Curated menu</span>
                <h2>Most-loved salon services</h2>
            </div>
            <a href="/salon-management/services.php" class="text-link">Explore full menu</a>
        </div>
        <div class="lux-grid">
            <?php foreach ($featuredServices as $service): ?>
                <article class="lux-card service-card">
                    <span class="pill"><?php echo htmlspecialchars($service['category'] ?: 'Signature Care'); ?></span>
                    <h3><?php echo htmlspecialchars($service['name']); ?></h3>
                    <p><?php echo htmlspecialchars($service['description']); ?></p>
                    <div class="service-meta">
                        <span>$<?php echo number_format($service['price'], 2); ?></span>
                        <span><?php echo (int) $service['duration']; ?> min</span>
                    </div>
                    <a href="/salon-management/appointments.php?service_id=<?php echo $service['id']; ?>" class="btn btn-outline-gold">Book This Service</a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section-shell section-dark">
    <div class="container">
        <div class="section-heading light">
            <div>
                <span class="eyebrow">Meet the artists</span>
                <h2>Stylists with distinct specialties</h2>
            </div>
            <a href="/salon-management/stylists.php" class="text-link text-link-light">See all stylists</a>
        </div>
        <div class="lux-grid stylist-grid">
            <?php foreach ($stylists as $stylist): ?>
                <article class="lux-card stylist-card">
                    <img src="<?php echo htmlspecialchars($stylist['avatar'] ?: '/salon-management/assets/images/stylist-default.svg'); ?>" alt="<?php echo htmlspecialchars($stylist['name']); ?>">
                    <div>
                        <h3><?php echo htmlspecialchars($stylist['name']); ?></h3>
                        <p class="text-gold"><?php echo htmlspecialchars($stylist['specialization'] ?: 'Luxury Hair Design'); ?></p>
                        <p><?php echo htmlspecialchars($stylist['bio'] ?: 'Known for refined finishing, thoughtful consultations, and polished guest care.'); ?></p>
                        <div class="mini-meta">
                            <span><?php echo (int) $stylist['experience_years']; ?>+ years</span>
                            <a href="/salon-management/appointments.php?stylist_id=<?php echo $stylist['id']; ?>" class="text-link text-link-light">Select stylist</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="container split-highlight">
        <div class="highlight-panel">
            <span class="eyebrow">Why clients return</span>
            <h2>Refined hospitality backed by organized salon operations.</h2>
            <p>Clients can browse services, choose a stylist, and lock in real availability without back-and-forth calls. Staff get cleaner scheduling, reminders, and billing in one place.</p>
            <a href="/salon-management/register.php" class="btn btn-primary">Create Client Account</a>
        </div>
        <div class="benefit-stack">
            <div class="benefit-card">
                <strong>Calendar booking</strong>
                <span>Real slot visibility and double-booking prevention.</span>
            </div>
            <div class="benefit-card">
                <strong>Tailored specialists</strong>
                <span>Book by expertise, not just by time.</span>
            </div>
            <div class="benefit-card">
                <strong>Luxury presentation</strong>
                <span>Clean, mobile-friendly browsing for every page.</span>
            </div>
        </div>
    </div>
</section>

<section class="section-shell testimonials-shell">
    <div class="container">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Testimonials</span>
                <h2>What guests are saying</h2>
            </div>
        </div>
        <div class="lux-grid testimonial-grid">
            <?php foreach ($reviews as $review): ?>
                <article class="lux-card testimonial-card">
                    <div class="rating-row"><?php echo str_repeat('★', (int) $review['rating']); ?></div>
                    <p><?php echo htmlspecialchars($review['review_text']); ?></p>
                    <div class="mini-meta">
                        <strong><?php echo htmlspecialchars($review['client_name']); ?></strong>
                        <span><?php echo date('M Y', strtotime($review['created_at'])); ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
