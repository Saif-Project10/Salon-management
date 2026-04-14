<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    if (empty($name) || empty($email) || empty($message)) {
        $error_msg = "Please fill out all required fields.";
    } else {
        $success_msg = "Thank you for getting in touch! We will get back to you soon.";
    }
}

include 'includes/header.php';
?>

<style>
/* Smooth animations for cards and interactive elements */
.hover-animate {
    transition: transform 0.3s cubic-bezier(0.25, 0.8, 0.25, 1), box-shadow 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}
.hover-animate:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(15, 11, 8, 0.12);
}
.hover-animate:active {
    transform: scale(0.97) translateY(-2px);
}

.social-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 16px;
    background: rgba(255, 255, 255, 0.9);
    color: var(--color-black);
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    transition: all 0.3s ease;
    text-decoration: none;
}
.social-btn:hover {
    background: var(--color-black);
    color: var(--color-primary);
    transform: translateY(-3px);
}
.social-btn:active {
    transform: scale(0.95);
}

.form-control, .btn {
    transition: all 0.3s ease;
}
.form-control:focus {
    transform: translateY(-2px);
}
.btn:active {
    transform: scale(0.95);
}
</style>

<section class="page-hero dark" style="background: url('/salon-management/assets/images/salon_exterior.png') center/cover; position:relative; z-index:1;">
    <div style="position:absolute; inset:0; background:linear-gradient(90deg, rgba(10,9,8,0.85) 0%, rgba(10,9,8,0.65) 100%); z-index:-1;"></div>
    <div class="container hero-copy">
        <span class="eyebrow">Get in touch</span>
        <h1>Contact Us</h1>
        <p>We would love to hear from you. Visit our salon, give us a call, or send us a message below.</p>
    </div>
</section>

<section class="section-shell">
    <div class="container">
        
        <?php if ($success_msg): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <div class="booking-layout">
            <!-- Left Side: Form -->
            <div class="lux-card form-card hover-animate">
                <span class="eyebrow">Send a message</span>
                <h2 style="font-size: 2rem; margin-bottom:10px;">How can we help?</h2>
                <p class="mb-2" style="color:var(--color-muted);">Whether you have a question about our services, pricing, or need help booking, our team is ready to answer all your questions.</p>
                
                <form method="POST" action="contact.php" style="display: flex; flex-direction: column; gap: 1rem;">
                    <div class="form-group row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div>
                            <label for="name" style="display:block; margin-bottom:8px; font-weight:600;">Your Name *</label>
                            <input type="text" id="name" name="name" class="form-control" required placeholder="John Doe">
                        </div>
                        <div>
                            <label for="email" style="display:block; margin-bottom:8px; font-weight:600;">Email Address *</label>
                            <input type="email" id="email" name="email" class="form-control" required placeholder="john@example.com">
                        </div>
                    </div>
                    <div class="form-group mt-1">
                        <label for="subject" style="display:block; margin-bottom:8px; font-weight:600;">Subject</label>
                        <input type="text" id="subject" name="subject" class="form-control" placeholder="How can we help you?">
                    </div>
                    <div class="form-group mt-1">
                        <label for="message" style="display:block; margin-bottom:8px; font-weight:600;">Message *</label>
                        <textarea id="message" name="message" class="form-control" rows="5" required placeholder="Your message here..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary mt-2" style="width: 100%; border-radius: 16px;">Send Message</button>
                </form>
            </div>

            <!-- Right Side: Details -->
            <div style="display:flex; flex-direction:column; gap:24px; height:100%;">
                <div class="lux-card hover-animate" style="padding:0; overflow:hidden; border-radius: 24px;">
                    <img src="/salon-management/assets/images/salon_reception.jpg" alt="Salon Reception" style="height:250px; object-fit:cover; width: 100%;">
                </div>

                <div class="lux-card hover-animate" style="flex: 1;">
                    <h3 style="margin-bottom: 20px;">Contact Information</h3>
                    <div style="display:flex; flex-direction:column; gap:16px;">
                        <div>
                            <strong>Location</strong>
                            <p style="color:var(--color-muted); margin-top:4px;">123 Elegance Avenue, Suite 100<br>Karachi, Pakistan</p>
                        </div>
                        <div>
                            <strong>Call Us</strong>
                            <p style="color:var(--color-muted); margin-top:4px; font-size:1.1rem; color:var(--color-primary-dark); font-weight:600;">+92 300 1234567</p>
                        </div>
                        <div>
                            <strong>Email Us</strong>
                            <p style="color:var(--color-muted); margin-top:4px;">support@elegancedigital.dev</p>
                        </div>
                    </div>
                </div>

                <div class="lux-card hover-animate" style="background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark)); color: var(--color-black); border:none;">
                    <h3 style="margin-bottom: 12px; color: var(--color-black);">Follow Us</h3>
                    <p style="margin-bottom: 16px; color: rgba(0,0,0,0.75); font-size: 0.95rem;">Stay updated with our latest styles, exclusive offers, and beauty tips on all platforms.</p>
                    <div style="display: flex; gap: 12px;">
                        <a href="#" class="social-btn">Instagram</a>
                        <a href="#" class="social-btn">Facebook</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-shell about-showcase" style="padding-top:0;">
    <div class="container about-grid">
        <div class="highlight-panel">
            <span class="eyebrow">Working Hours</span>
            <h2>We are open 6 days a week</h2>
            <p>Our salon is ready to welcome you. Book an appointment online to secure your preferred time and stylist.</p>
            <div class="benefit-stack compact-stack">
                <div class="benefit-card">
                    <strong>Monday - Friday</strong>
                    <span>10:00 AM - 8:00 PM</span>
                </div>
                <div class="benefit-card" style="background: rgba(200, 162, 74, 0.1);">
                    <strong>Saturday</strong>
                    <span>9:00 AM - 7:00 PM</span>
                </div>
                <div class="benefit-card">
                    <strong>Sunday</strong>
                    <span>Closed</span>
                </div>
            </div>
            <a href="/salon-management/appointments.php" class="btn btn-primary" style="margin-top: 24px;">Book an Appointment</a>
        </div>
        
        <div class="about-visual" style="border-radius:16px; min-height: 400px; height: 100%;">
            <!-- Location Map -->
            <iframe 
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d115822.42747190013!2d67.00113635!3d24.8607343!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3eb33e06651d4bbf%3A0x9cf92f44555a0c23!2sKarachi%2C%20Karachi%20City%2C%20Sindh%2C%20Pakistan!5e0!3m2!1sen!2s!4v1700000000000!5m2!1sen!2s" 
                width="100%" height="100%" style="border:0; min-height:400px;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
