<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireRole('receptionist');

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    
    if ($name) {
        $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?")->execute([$name, $phone, $user_id]);
        $_SESSION['user_name'] = $name;
        $_SESSION['success'] = "Profile updated successfully.";
        
        if (!empty($new_password)) {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $user_id]);
            $_SESSION['success'] = "Profile and password updated successfully.";
        }
    } else {
        $_SESSION['error'] = "Name is required.";
    }
    
    rotateCsrfToken();
    header("Location: profile.php");
    exit();
}

$user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$user_id]);
$user = $user->fetch();

include '../includes/header.php';
?>

<div class="container py-2">
    <div class="flex flex-between mb-2">
        <div>
            <span class="eyebrow">Settings</span>
            <h2>Profile Settings</h2>
        </div>
        <a href="dashboard.php" class="btn btn-outline-gold">Back to Dashboard</a>
    </div>

    <div class="form-card" style="max-width: 600px; margin: 0 auto;">
        <?php showAlert(); ?>
        <form action="profile.php" method="POST">
            <?php echo csrfInput(); ?>
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>New Password (leave blank to keep current)</label>
                <input type="password" name="new_password" class="form-control" placeholder="••••••••">
            </div>
            
            <button type="submit" class="btn btn-primary" style="margin-top:15px; width:100%;">Save Changes</button>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
