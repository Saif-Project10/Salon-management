<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$services = $pdo->query("
    SELECT id, name, description, duration, price, category, featured_image
    FROM services
    ORDER BY FIELD(category, 'Hair', 'Skin', 'Bridal', 'Nails'), name ASC
")->fetchAll();

$imageBase = '/salon-management/assets/images/';
$imageDir = __DIR__ . '/assets/images/';

$categoryFallbacks = [
    'Hair' => 'haircut.png',
    'Skin' => 'facial.jpg',
    'Bridal' => 'bridal.png',
    'Nails' => 'classic_manicure.jpeg',
];

$categoryDescriptions = [
    'Hair' => 'Cuts, color, spa, and smoothing services with polished professional styling.',
    'Skin' => 'Facial and skin-care treatments for glow, clarity, and healthy texture.',
    'Bridal' => 'Bridal and event makeup services for elegant special-occasion looks.',
    'Nails' => 'Manicure, pedicure, gel, and nail art services with clean finishing.',
];

$categoryOrder = ['Hair', 'Skin', 'Bridal', 'Nails'];
$servicesByCategory = [];

foreach ($services as $service) {
    $selectedImage = null;
    if (!empty($service['featured_image']) && is_file($imageDir . $service['featured_image'])) {
        $selectedImage = $imageBase . $service['featured_image'];
    } else {
        $fallback = $categoryFallbacks[$service['category']] ?? 'haircut.png';
        $selectedImage = $imageBase . $fallback;
    }

    $service['image'] = $selectedImage;
    $service['description'] = trim((string) $service['description']) !== ''
        ? $service['description']
        : 'Professional salon service with quality care and a refined finish.';

    $servicesByCategory[$service['category']][] = $service;
}

include 'includes/header.php';
?>

<style>
#services-page {
    padding: 42px 0 72px;
}

#services-page .services-clean-hero {
    padding: 56px 0 28px;
    background: linear-gradient(145deg, #171310 0%, #241d18 52%, #2f241d 100%);
    color: #fff;
    border-radius: 28px;
    margin-bottom: 28px;
}

#services-page .services-clean-hero-inner {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

#services-page .services-clean-hero-inner p {
    max-width: 760px;
    color: rgba(255, 255, 255, 0.8);
    margin: 0;
}

#services-page .services-clean-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin: 0 0 28px;
}

#services-page .services-clean-sections {
    display: grid;
    gap: 34px;
}

#services-page .services-clean-category {
    display: block;
}

#services-page .services-clean-category.is-hidden {
    display: none;
}

#services-page .services-clean-category-head {
    display: flex;
    justify-content: space-between;
    align-items: end;
    gap: 20px;
    margin-bottom: 22px;
}

#services-page .services-clean-category-head p {
    max-width: 520px;
    color: #7a7269;
    margin: 0;
}

#services-page .services-clean-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 28px;
    align-items: stretch;
}

#services-page .services-clean-card {
    display: flex;
    flex-direction: column;
    min-width: 0;
    height: 100%;
    background: #fff;
    border: 1px solid rgba(200, 162, 74, 0.16);
    border-radius: 22px;
    overflow: hidden;
    box-shadow: 0 18px 34px rgba(15, 11, 8, 0.08);
    transition: transform 0.28s ease, box-shadow 0.28s ease, border-color 0.28s ease;
}

#services-page .services-clean-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 26px 46px rgba(15, 11, 8, 0.14);
    border-color: rgba(200, 162, 74, 0.28);
}

#services-page .services-clean-card-image {
    width: 100%;
    height: 220px;
    overflow: hidden;
    background: #efe7dd;
}

#services-page .services-clean-card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    display: block;
}

#services-page .services-clean-card-body {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding: 20px 20px 26px;
    flex: 1;
}

#services-page .services-clean-card-tag {
    display: inline-flex;
    width: fit-content;
    padding: 8px 12px;
    border-radius: 999px;
    background: rgba(200, 162, 74, 0.1);
    color: #ab8330;
    font-size: 0.76rem;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    font-weight: 700;
}

#services-page .services-clean-card-body h3 {
    margin: 0;
}

#services-page .services-clean-card-body p {
    margin: 0;
    color: #7a7269;
    line-height: 1.6;
}

#services-page .services-clean-card-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    color: #ab8330;
    font-weight: 700;
    margin-bottom: 10px;
}

#services-page .services-clean-card-btn {
    width: 100%;
    margin-top: auto;
    margin-bottom: 2px;
}

#services-page .services-scroll-controls {
    position: fixed;
    right: 22px;
    bottom: 22px;
    z-index: 999;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

#services-page .services-scroll-btn {
    width: 48px;
    height: 48px;
    border: none;
    border-radius: 999px;
    background: linear-gradient(135deg, #ddb861, #c8a24a);
    color: #131110;
    font-size: 1.25rem;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 14px 28px rgba(15, 11, 8, 0.18);
    transition: transform 0.28s ease, box-shadow 0.28s ease;
}

#services-page .services-scroll-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 18px 34px rgba(15, 11, 8, 0.22);
}

@media (max-width: 1080px) {
    #services-page .services-clean-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 760px) {
    #services-page .services-clean-category-head {
        flex-direction: column;
        align-items: start;
    }

    #services-page .services-clean-grid {
        grid-template-columns: 1fr;
        gap: 22px;
    }

    #services-page .services-clean-card-image {
        height: 210px;
    }
}
</style>

<div id="services-page">
<section class="services-clean-hero">
    <div class="container">
        <div class="services-clean-hero-inner">
            <span class="eyebrow">Elegance Salon Services</span>
            <h1>Choose a category and book directly from the service card.</h1>
            <p>Every service is shown in a clean card with one image, price, short description, duration, and a visible booking button.</p>
        </div>
    </div>
</section>

<section class="services-clean-shell">
    <div class="container">
        <div class="services-clean-sections">
            <?php foreach ($categoryOrder as $category): ?>
                <?php if (empty($servicesByCategory[$category])) { continue; } ?>
                <section class="services-clean-category" data-service-category="<?php echo htmlspecialchars(strtolower($category)); ?>">
                    <div class="services-clean-category-head">
                        <div>
                            <span class="eyebrow"><?php echo htmlspecialchars($category); ?></span>
                            <h2><?php echo htmlspecialchars($category); ?> Services</h2>
                        </div>
                        <p><?php echo htmlspecialchars($categoryDescriptions[$category] ?? ''); ?></p>
                    </div>

                    <div class="services-clean-grid">
                        <?php foreach ($servicesByCategory[$category] as $service): ?>
                            <article class="services-clean-card">
                                <div class="services-clean-card-image">
                                    <img src="<?php echo htmlspecialchars($service['image']); ?>" alt="<?php echo htmlspecialchars($service['name']); ?>">
                                </div>

                                <div class="services-clean-card-body">
                                    <span class="services-clean-card-tag"><?php echo htmlspecialchars($service['category']); ?></span>
                                    <h3><?php echo htmlspecialchars($service['name']); ?></h3>
                                    <p><?php echo htmlspecialchars($service['description']); ?></p>

                                    <div class="services-clean-card-meta">
                                        <span>$<?php echo number_format((float) $service['price'], 2); ?></span>
                                        <span><?php echo (int) $service['duration']; ?> min</span>
                                    </div>

                                    <a href="/salon-management/appointments.php?service_id=<?php echo (int) $service['id']; ?>" class="btn btn-primary services-clean-card-btn">Book Now</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<div class="services-scroll-controls" aria-label="Scroll controls">
    <button type="button" class="services-scroll-btn" data-scroll-up aria-label="Scroll to top">&#8593;</button>
    <button type="button" class="services-scroll-btn" data-scroll-down aria-label="Scroll down">&#8595;</button>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const downButton = document.querySelector('[data-scroll-down]');
    const upButton = document.querySelector('[data-scroll-up]');

    if (downButton) {
        downButton.addEventListener('click', function () {
            const step = Math.max(Math.round((window.innerHeight / 3) * 2), 440);
            window.scrollBy({
                top: step,
                left: 0,
                behavior: 'smooth'
            });
        });
    }

    if (upButton) {
        upButton.addEventListener('click', function () {
            window.scrollTo({
                top: 0,
                left: 0,
                behavior: 'smooth'
            });
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
