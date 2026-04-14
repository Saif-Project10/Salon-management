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

<style>
.editorial-shell {
    padding: 100px 0;
    background: linear-gradient(180deg, #fffdf9 0%, #f6f0e8 100%);
}

.stylist-editorial {
    display: flex;
    flex-wrap: wrap;
    gap: 8vh;
    align-items: center;
    margin-bottom: 120px;
    opacity: 0;
    animation: fadeUp 1s forwards ease-out;
}

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(40px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Delay staggered animations */
.stylist-editorial:nth-child(1) { animation-delay: 0.1s; }
.stylist-editorial:nth-child(2) { animation-delay: 0.3s; }
.stylist-editorial:nth-child(3) { animation-delay: 0.5s; }
.stylist-editorial:nth-child(4) { animation-delay: 0.7s; }
.stylist-editorial:nth-child(5) { animation-delay: 0.9s; }

.stylist-editorial:nth-child(even) {
    flex-direction: row-reverse;
}

.stylist-editorial:last-child {
    margin-bottom: 0;
}

.stylist-ed-image {
    flex: 1;
    min-width: 45%;
    position: relative;
}

.stylist-ed-image::before {
    content: '';
    position: absolute;
    inset: -20px 20px 20px -20px;
    border: 2px solid var(--color-primary);
    border-radius: 40px;
    z-index: 0;
    opacity: 0.3;
    transition: all 0.5s ease;
}

.stylist-editorial:nth-child(even) .stylist-ed-image::before {
    inset: -20px -20px 20px 20px;
}

.stylist-ed-image:hover::before {
    inset: -10px 10px 10px -10px;
    opacity: 0.8;
}

.stylist-editorial:nth-child(even) .stylist-ed-image:hover::before {
    inset: -10px -10px 10px 10px;
}

.stylist-ed-image img {
    position: relative;
    z-index: 1;
    width: 100%;
    height: 65vh;
    min-height: 500px;
    object-fit: cover;
    border-radius: 40px;
    box-shadow: 0 30px 60px rgba(15,11,8,0.18);
    transition: transform 0.6s cubic-bezier(0.25, 0.8, 0.25, 1);
}

.stylist-ed-image:hover img {
    transform: scale(1.02);
}

.stylist-ed-content {
    flex: 1;
    min-width: 45%;
    padding: 0 5%;
}

.stylist-ed-content h2 {
    font-size: clamp(3rem, 4vw, 4.5rem);
    margin-bottom: 12px;
    line-height: 1.05;
    color: var(--color-black);
}

.stylist-ed-specialty {
    font-size: 1.1rem;
    color: var(--color-primary-dark);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.15em;
    margin-bottom: 30px;
    display: inline-block;
    position: relative;
}

.stylist-ed-specialty::after {
    content: '';
    display: block;
    width: 40px;
    height: 3px;
    background: var(--color-primary);
    margin-top: 15px;
}

.stylist-ed-bio {
    font-size: 1.15rem;
    color: var(--color-muted);
    margin-bottom: 35px;
    line-height: 1.8;
}

.stylist-ed-exp {
    display: inline-flex;
    align-items: center;
    background: linear-gradient(135deg, #171310, #2a221c);
    color: var(--color-white);
    padding: 12px 24px;
    border-radius: 999px;
    font-weight: 600;
    font-size: 0.95rem;
    letter-spacing: 0.05em;
    margin-bottom: 35px;
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.stylist-ed-exp span {
    color: var(--color-primary);
    font-weight: 800;
    margin-right: 6px;
    font-size: 1.2rem;
}

.stylist-ed-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 45px;
}

.stylist-ed-tag {
    background: rgba(255, 255, 255, 0.6);
    border: 1px solid rgba(200, 162, 74, 0.4);
    padding: 8px 18px;
    border-radius: 999px;
    font-size: 0.85rem;
    color: var(--color-black);
    font-weight: 700;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.stylist-ed-tag:hover {
    background: var(--color-primary);
    color: var(--color-black);
    border-color: var(--color-primary);
}

.stylist-ed-action .btn {
    padding: 18px 36px;
    font-size: 0.95rem;
    border-radius: 999px;
}

@media (max-width: 992px) {
    .stylist-editorial, .stylist-editorial:nth-child(even) {
        flex-direction: column;
        gap: 40px;
        margin-bottom: 80px;
    }
    .stylist-ed-image, .stylist-ed-content {
        min-width: 100%;
        padding: 0;
    }
    .stylist-ed-image::before {
        display: none;
    }
    .stylist-ed-image img {
        height: 50vh;
        min-height: 400px;
    }
    .stylist-ed-content {
        text-align: center;
    }
    .stylist-ed-specialty::after {
        margin: 15px auto 0;
    }
    .stylist-ed-tags {
        justify-content: center;
    }
}
</style>

<section class="page-hero dark" style="background: url('/salon-management/assets/images/spa_padicure.jpeg') center/cover; position:relative; z-index:1; padding: 120px 0 80px;">
    <div style="position:absolute; inset:0; background:linear-gradient(90deg, rgba(16,13,10,0.95) 0%, rgba(16,13,10,0.7) 100%); z-index:-1;"></div>
    <div class="container hero-copy">
        <span class="eyebrow" style="color: var(--color-primary);">The Artists Behind Elegance</span>
        <h1 style="font-size: clamp(3rem, 5vw, 5rem); margin-bottom:20px;">Meet Our Master Stylists</h1>
        <p style="font-size: 1.2rem; max-width: 700px; line-height:1.6; color: rgba(255,255,255,0.8);">Our curated team of industry-leading professionals brings decades of collective experience, specializing in transformative beauty and precise finishing.</p>
    </div>
</section>

<section class="editorial-shell">
    <div class="container">
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
            <article class="stylist-editorial">
                <div class="stylist-ed-image">
                    <img src="<?php echo htmlspecialchars($stylist['avatar'] ?: '/salon-management/assets/images/stylist-default.svg'); ?>" alt="<?php echo htmlspecialchars($stylist['name']); ?>">
                </div>
                
                <div class="stylist-ed-content">
                    <h2><?php echo htmlspecialchars($stylist['name']); ?></h2>
                    <span class="stylist-ed-specialty"><?php echo htmlspecialchars($stylist['specialization'] ?: 'Styling Expert'); ?></span>
                    
                    <p class="stylist-ed-bio">
                        <?php echo htmlspecialchars($stylist['bio'] ?: 'This stylist delivers polished finishing, guided consultations, and premium salon care.'); ?>
                    </p>
                    
                    <div class="stylist-ed-exp">
                        <span><?php echo (int) $stylist['experience_years']; ?>+</span> YEARS OF EXPERIENCE
                    </div>
                    
                    <?php if (!empty($labels)): ?>
                        <div class="stylist-ed-tags">
                            <?php foreach ($labels as $label): ?>
                                <span class="stylist-ed-tag"><?php echo htmlspecialchars($label); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="stylist-ed-action">
                        <a href="/salon-management/appointments.php?stylist_id=<?php echo $stylist['id']; ?>" class="btn btn-primary">Book an Appointment</a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
