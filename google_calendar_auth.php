<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireLogin();

$error = '';
$success = '';
$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disconnect'])) {
    verifyCsrfToken();
    $stmt = $pdo->prepare("DELETE FROM user_calendar_tokens WHERE user_id = ?");
    $stmt->execute([$userId]);
    $success = 'Google Calendar connection removed.';
}

try {
    $googleClient = salonBuildGoogleClient($pdo);
} catch (Throwable $exception) {
    $googleClient = null;
    $error = 'Google Calendar is not configured yet.';
}

if (isset($_GET['code']) && $googleClient) {
    try {
        $token = $googleClient->fetchAccessTokenWithAuthCode($_GET['code']);
        if (!empty($token['error'])) {
            throw new RuntimeException((string) $token['error']);
        }

        $calendarId = salonGetSetting($pdo, 'google_calendar_default_id', 'primary') ?: 'primary';
        salonStoreGoogleCalendarToken($pdo, $userId, $token, $calendarId);
        $success = 'Google Calendar connected successfully.';
    } catch (Throwable $exception) {
        $error = 'Google Calendar authorization failed.';
    }
}

if (isset($_GET['connect'])) {
    if (!$googleClient) {
        $error = 'Google client credentials are missing. Save them in SMS Settings first.';
    } else {
        header('Location: ' . $googleClient->createAuthUrl());
        exit();
    }
}

$isConnected = salonHasGoogleCalendarConnection($pdo, $userId);

include 'includes/header.php';
?>

<div class="container py-2">
    <div class="flex flex-between mb-2">
        <div>
            <span class="eyebrow">Google Calendar</span>
            <h2>Calendar connection</h2>
        </div>
        <a href="<?php echo htmlspecialchars(dashboardUrlForRole()); ?>" class="btn btn-outline-gold">&larr; Dashboard</a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="dashboard-grid">
        <div class="detail-card">
            <span class="eyebrow">Status</span>
            <h3><?php echo $isConnected ? 'Connected' : 'Not Connected'; ?></h3>
            <p>Connect your Google Calendar to automatically mirror booking, reschedule, and cancellation changes.</p>
            <div class="history-list">
                <a href="/salon-management/google_calendar_auth.php?connect=1" class="btn btn-primary">Connect Google Calendar</a>
                <?php if ($isConnected): ?>
                    <form method="POST">
                        <?php echo csrfInput(); ?>
                        <button type="submit" name="disconnect" class="btn btn-outline-gold">Disconnect</button>
                    </form>
                <?php endif; ?>
                <?php if (hasRole('admin')): ?>
                    <a href="/salon-management/admin/sms_settings.php" class="btn btn-outline-gold">Open Credentials</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
