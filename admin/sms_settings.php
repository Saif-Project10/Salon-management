<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireRole('admin');

$error = '';
$success = '';
$keys = [
    'twilio_account_sid',
    'twilio_auth_token',
    'twilio_phone_number',
    'google_client_id',
    'google_client_secret',
    'google_redirect_uri',
    'google_calendar_default_id',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    verifyCsrfToken();

    try {
        foreach ($keys as $key) {
            salonSetSetting($pdo, $key, trim((string) ($_POST[$key] ?? '')));
        }
        $success = 'Communication and calendar settings saved.';
    } catch (Exception $exception) {
        $error = 'Settings could not be saved.';
    }
}

$settings = salonGetSettings($pdo, $keys);

include '../includes/header.php';
?>

<div class="container py-2">
    <div class="flex flex-between mb-2">
        <div>
            <span class="eyebrow">Communications</span>
            <h2>SMS and calendar settings</h2>
        </div>
        <a href="/salon-management/admin/dashboard.php" class="btn btn-outline-gold">&larr; Dashboard</a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="dashboard-grid">
        <div class="form-card" style="margin-top:0;">
            <h3 class="mb-1">Twilio SMS</h3>
            <form method="POST">
                <?php echo csrfInput(); ?>

                <div class="form-group">
                    <label>Twilio Account SID</label>
                    <input type="text" name="twilio_account_sid" class="form-control" value="<?php echo htmlspecialchars($settings['twilio_account_sid'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Twilio Auth Token</label>
                    <input type="password" name="twilio_auth_token" class="form-control" value="<?php echo htmlspecialchars($settings['twilio_auth_token'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Twilio Phone Number</label>
                    <input type="text" name="twilio_phone_number" class="form-control" value="<?php echo htmlspecialchars($settings['twilio_phone_number'] ?? ''); ?>">
                </div>

                <h3 class="mb-1 mt-2">Google Calendar</h3>

                <div class="form-group">
                    <label>Google Client ID</label>
                    <input type="text" name="google_client_id" class="form-control" value="<?php echo htmlspecialchars($settings['google_client_id'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Google Client Secret</label>
                    <input type="password" name="google_client_secret" class="form-control" value="<?php echo htmlspecialchars($settings['google_client_secret'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Google Redirect URI</label>
                    <input type="text" name="google_redirect_uri" class="form-control" value="<?php echo htmlspecialchars($settings['google_redirect_uri'] ?? salonGoogleRedirectUri($pdo)); ?>">
                </div>

                <div class="form-group">
                    <label>Default Calendar ID</label>
                    <input type="text" name="google_calendar_default_id" class="form-control" value="<?php echo htmlspecialchars($settings['google_calendar_default_id'] ?? 'primary'); ?>">
                </div>

                <button type="submit" name="save_settings" class="btn btn-primary" style="width:100%;">Save Settings</button>
            </form>
        </div>

        <div class="detail-card" style="grid-column: span 2;">
            <span class="eyebrow">Setup Notes</span>
            <h3>What these settings control</h3>
            <div class="history-list">
                <div class="history-item">
                    <strong>SMS delivery</strong>
                    <div>Booking, reschedule, cancellation, and reminder messages use your Twilio credentials.</div>
                </div>
                <div class="history-item">
                    <strong>Google sync</strong>
                    <div>OAuth tokens are stored per user after they connect from their dashboard.</div>
                </div>
                <div class="history-item">
                    <strong>Cron scripts</strong>
                    <div>Reminder and purchase-order automation uses the settings saved here plus the cron commands in <code>INSTALLATION.txt</code>.</div>
                </div>
            </div>
            <a href="/salon-management/google_calendar_auth.php" class="btn btn-outline-gold" style="margin-top:12px;">Open Google Calendar Connect</a>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
