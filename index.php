<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$featuredNames = ['Signature Haircut', 'Luxury Facial', 'Bridal Makeup', 'Classic Manicure'];
$featuredPlaceholders = implode(',', array_fill(0, count($featuredNames), '?'));
$featuredStmt = $pdo->prepare("
    SELECT id, name, description, duration, price, category, featured_image
    FROM services
    WHERE name IN ($featuredPlaceholders)
    ORDER BY FIELD(name, 'Signature Haircut', 'Luxury Facial', 'Bridal Makeup', 'Classic Manicure')
");
$featuredStmt->execute($featuredNames);
$featuredServices = $featuredStmt->fetchAll();

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

$settings_map = [
    'about_title' => 'Designed to feel luxurious from the first click to the final mirror check.',
    'about_desc' => 'Our experience blends modern salon aesthetics with practical booking tools, so clients enjoy visual confidence while staff stay organized behind the scenes.',
    'promo_title' => 'Reserve your next salon moment with confidence.',
    'promo_desc' => 'Explore premium services, choose your stylist, and confirm your preferred time from any device.'
];
$stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('about_title','about_desc','promo_title','promo_desc')");
while ($row = $stmt_settings->fetch()) {
    $settings_map[$row['setting_key']] = $row['setting_value'];
}

$serviceImagePool = [
    '/salon-management/assets/images/hairstyle.jpg',
    '/salon-management/assets/images/manicure.jpg',
    '/salon-management/assets/images/pedicure.jpg',
    '/salon-management/assets/images/facial.jpg'
];

$serviceImageMap = [
    'Bridal Makeup' => '/salon-management/assets/images/bridal.png',
    'Luxury Facial' => '/salon-management/assets/images/facial.png',
    'Signature Haircut' => '/salon-management/assets/images/signature_haircut.jpeg',
    'Classic Manicure' => '/salon-management/assets/images/classic_manicure.jpeg',
];

$heroSlides = [
    [
        'title' => 'Hair Styling',
        'description' => 'Signature cuts, polished blowouts, and modern styling tailored to your features.',
        'image' => '/salon-management/assets/images/hairstyle.jpg'
    ],
    [
        'title' => 'Manicure',
        'description' => 'Detail-focused nail care with refined finishes and premium hand rituals.',
        'image' => '/salon-management/assets/images/manicure.jpg'
    ],
    [
        'title' => 'Pedicure',
        'description' => 'Relaxing foot care designed for comfort, wellness, and elegant presentation.',
        'image' => '/salon-management/assets/images/pedicure.jpg'
    ],
    [
        'title' => 'Facial',
        'description' => 'Glow-focused skin treatments that combine relaxation with visible results.',
        'image' => '/salon-management/assets/images/facial.jpg'
    ]
];

include 'includes/header.php';
?>

<style>
.home-featured-services .service-grid-home .image-card {
    height: 100%;
}

.home-featured-services .service-grid-home .card-body {
    display: flex;
    flex-direction: column;
    gap: 14px;
    min-height: 230px;
    padding: 22px 22px 30px;
}

.home-featured-services .service-grid-home .service-meta {
    margin-bottom: 12px;
}

.home-featured-services .service-grid-home .btn {
    margin-top: auto;
    margin-bottom: 4px;
    align-self: flex-start;
}
</style>

<section class="hero-carousel" data-carousel>
    <div class="hero-carousel-track">
        <?php foreach ($heroSlides as $index => $slide): ?>
            <article class="hero-slide <?php echo $index === 0 ? 'active' : ''; ?>" style="background-image:url('<?php echo htmlspecialchars($slide['image']); ?>');" data-slide>
                <div class="hero-slide-overlay"></div>
                <div class="container hero-slide-content">
                    <span class="eyebrow">Luxury salon collection</span>
                    <h1><?php echo htmlspecialchars($slide['title']); ?></h1>
                    <p><?php echo htmlspecialchars($slide['description']); ?></p>
                    <div class="hero-actions">
                        <a href="/salon-management/appointments.php" class="btn btn-primary">Book Now</a>
                        <a href="/salon-management/services.php" class="btn btn-outline-gold">View Services</a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <button class="carousel-control prev" type="button" aria-label="Previous slide" data-carousel-prev>&lsaquo;</button>
    <button class="carousel-control next" type="button" aria-label="Next slide" data-carousel-next>&rsaquo;</button>
    <div class="carousel-dots" aria-label="Hero slider navigation">
        <?php foreach ($heroSlides as $index => $slide): ?>
            <button type="button" class="carousel-dot <?php echo $index === 0 ? 'active' : ''; ?>" data-carousel-dot="<?php echo $index; ?>" aria-label="Go to slide <?php echo $index + 1; ?>"></button>
        <?php endforeach; ?>
    </div>
</section>

<section class="section-shell home-featured-services">
    <div class="container">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Image-led services</span>
                <h2>Signature treatments with polished presentation</h2>
            </div>
            <a href="/salon-management/services.php" class="text-link">Explore full menu</a>
        </div>
        <div class="lux-grid service-grid-home">
            <?php foreach ($featuredServices as $index => $service): ?>
                <?php
                $image = !empty($service['featured_image'])
                    ? '/salon-management/assets/images/' . $service['featured_image']
                    : ($serviceImageMap[$service['name']] ?? $serviceImagePool[$index % count($serviceImagePool)]);
                ?>
                <article class="lux-card service-card image-card">
                    <div class="card-image-wrap">
                        <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($service['name']); ?>">
                        <div class="card-image-overlay"></div>
                        <span class="pill image-pill"><?php echo htmlspecialchars($service['category'] ?: 'Signature Care'); ?></span>
                    </div>
                    <div class="card-body">
                        <h3><?php echo htmlspecialchars($service['name']); ?></h3>
                        <p><?php echo htmlspecialchars($service['description']); ?></p>
                        <div class="service-meta">
                            <span>$<?php echo number_format($service['price'], 2); ?></span>
                            <span><?php echo (int) $service['duration']; ?> min</span>
                        </div>
                        <a href="/salon-management/appointments.php?service_id=<?php echo $service['id']; ?>" class="btn btn-outline-gold">Book This Service</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section-shell about-showcase">
    <div class="container about-grid">
        <div class="about-visual">
            <img src="/salon-management/assets/images/facial.jpg" alt="Salon facial treatment room">
            <div class="about-visual-badge">Relaxed rituals, premium care</div>
        </div>
        <div class="highlight-panel">
            <span class="eyebrow">About Elegance</span>
            <h2><?php echo htmlspecialchars($settings_map['about_title']); ?></h2>
            <p><?php echo htmlspecialchars($settings_map['about_desc']); ?></p>
            <div class="benefit-stack compact-stack">
                <div class="benefit-card">
                    <strong>Real availability</strong>
                    <span>Clients choose services and open time slots without guessing.</span>
                </div>
                <div class="benefit-card">
                    <strong>Curated beauty menu</strong>
                    <span>Hair, manicure, pedicure, and facial experiences presented with strong visuals.</span>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-shell section-dark">
    <div class="container">
        <div class="section-heading light">
            <div>
                <span class="eyebrow">Meet the artists</span>
                <h2>Stylists with refined specialties</h2>
            </div>
            <a href="/salon-management/stylists.php" class="text-link text-link-light">See all stylists</a>
        </div>
        <div class="lux-grid stylist-grid improved-stylist-grid">
            <?php foreach ($stylists as $stylist): ?>
                <article class="lux-card stylist-card clean-stylist-card">
                    <div class="stylist-avatar-wrap">
                        <img class="stylist-avatar" src="<?php echo htmlspecialchars($stylist['avatar'] ?: '/salon-management/assets/images/stylist-default.svg'); ?>" alt="<?php echo htmlspecialchars($stylist['name']); ?>">
                    </div>
                    <div>
                        <h3><?php echo htmlspecialchars($stylist['name']); ?></h3>
                        <p class="text-gold"><?php echo htmlspecialchars($stylist['specialization'] ?: 'Luxury Hair Design'); ?></p>
                        <p><?php echo htmlspecialchars($stylist['bio'] ?: 'Known for calm consultations, elevated styling, and polished guest care.'); ?></p>
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

<section class="section-shell testimonials-shell">
    <div class="container">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Testimonials</span>
                <h2>Guests who love the experience</h2>
            </div>
        </div>
        <div class="lux-grid testimonial-grid">
            <?php foreach ($reviews as $review): ?>
                <article class="lux-card testimonial-card image-testimonial-card">
                    <div class="testimonial-head">
                        <div class="testimonial-avatar"><?php echo strtoupper(substr($review['client_name'], 0, 1)); ?></div>
                        <div>
                            <strong><?php echo htmlspecialchars($review['client_name']); ?></strong>
                            <div class="rating-row"><?php echo str_repeat('★', (int) $review['rating']); ?></div>
                        </div>
                    </div>
                    <p><?php echo htmlspecialchars($review['review_text']); ?></p>
                    <span><?php echo date('M Y', strtotime($review['created_at'])); ?></span>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section-shell contact-image-section">
    <div class="container contact-promo">
        <div class="contact-promo-copy">
            <span class="eyebrow">Visit or book today</span>
            <h2><?php echo htmlspecialchars($settings_map['promo_title']); ?></h2>
            <p><?php echo htmlspecialchars($settings_map['promo_desc']); ?></p>
            <div class="hero-actions">
                <a href="/salon-management/appointments.php" class="btn btn-primary">Book Now</a>
                <a href="/salon-management/contact.php" class="btn btn-outline-gold">Contact Us</a>
            </div>
        </div>
        <div class="contact-promo-image">
            <img src="/salon-management/assets/images/manicure.jpg" alt="Salon contact and beauty service visual">
        </div>
    </div>
</section>


<?php include 'includes/footer.php'; ?>

