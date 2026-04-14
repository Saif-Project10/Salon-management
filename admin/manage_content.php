<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireRole('admin');

$setting_keys = [
    'about_title' => 'Designed to feel luxurious from the first click to the final mirror check.',
    'about_desc' => 'Our experience blends modern salon aesthetics with practical booking tools, so clients enjoy visual confidence while staff stay organized behind the scenes.',
    'promo_title' => 'Reserve your next salon moment with confidence.',
    'promo_desc' => 'Explore premium services, choose your stylist, and confirm your preferred time from any device.',
    'contact_phone' => '+1-800-ELEGANCE',
    'contact_email' => 'hello@elegance.local'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    
    foreach ($setting_keys as $key => $default) {
        $val = trim($_POST[$key] ?? '');
        if ($val === '') $val = $default; // Fallback to default if erased
        $stmt->execute([$key, $val]);
    }
    
    $_SESSION['success'] = "Content settings updated successfully.";
    rotateCsrfToken();
    header("Location: manage_content.php");
    exit();
}

// Fetch current values
$settings_map = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
while ($row = $stmt->fetch()) {
    $settings_map[$row['setting_key']] = $row['setting_value'];
}

// Merge with defaults
foreach ($setting_keys as $key => $default) {
    if (!isset($settings_map[$key])) {
        $settings_map[$key] = $default;
    }
}

include '../includes/header.php';
?>

<div class="container py-2">
    <div class="flex flex-between mb-2">
        <div>
            <span class="eyebrow">CMS</span>
            <h2>Manage Content Settings</h2>
        </div>
        <a href="dashboard.php" class="btn btn-outline-gold">Back to Dashboard</a>
    </div>

    <div class="form-card" style="max-width: 800px; margin: 0 auto;">
        <?php showAlert(); ?>
        <p style="color:var(--color-muted); margin-bottom: 20px;">Use this form to update the text displayed on the public-facing pages of the salon website.</p>
        
        <form action="manage_content.php" method="POST">
            <?php echo csrfInput(); ?>
            
            <h3 style="margin-bottom: 15px; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Homepage "About" Section</h3>
            <div class="form-group">
                <label>About Heading</label>
                <input type="text" name="about_title" class="form-control" value="<?php echo htmlspecialchars($settings_map['about_title']); ?>">
            </div>
            <div class="form-group">
                <label>About Description</label>
                <textarea name="about_desc" class="form-control" rows="3"><?php echo htmlspecialchars($settings_map['about_desc']); ?></textarea>
            </div>
            
            <h3 style="margin-top: 30px; margin-bottom: 15px; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Homepage "Promo" Section</h3>
            <div class="form-group">
                <label>Promo Heading</label>
                <input type="text" name="promo_title" class="form-control" value="<?php echo htmlspecialchars($settings_map['promo_title']); ?>">
            </div>
            <div class="form-group">
                <label>Promo Description</label>
                <textarea name="promo_desc" class="form-control" rows="3"><?php echo htmlspecialchars($settings_map['promo_desc']); ?></textarea>
            </div>

            <h3 style="margin-top: 30px; margin-bottom: 15px; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Global Contact Info</h3>
            <div class="form-group">
                <label>Contact Phone</label>
                <input type="text" name="contact_phone" class="form-control" value="<?php echo htmlspecialchars($settings_map['contact_phone']); ?>">
            </div>
            <div class="form-group">
                <label>Contact Email</label>
                <input type="text" name="contact_email" class="form-control" value="<?php echo htmlspecialchars($settings_map['contact_email']); ?>">
            </div>
            
            <button type="submit" class="btn btn-primary" style="margin-top:20px; width:100%;">Save Changes</button>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
