<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireRole('client');

$error = '';
$success = '';
$user_id = $_SESSION['user_id'];

// Get current details
$stmt = $pdo->prepare("
    SELECT u.name, u.email, u.phone, c.preferences 
    FROM users u 
    LEFT JOIN clients c ON u.id = c.user_id 
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];

    if (empty($name)) {
        $error = "Name is required.";
    } else {
        try {
            $pdo->beginTransaction();

            if (!empty($password)) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET name=?, phone=?, password=? WHERE id=?");
                $stmt->execute([$name, $phone, $hashed, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name=?, phone=? WHERE id=?");
                $stmt->execute([$name, $phone, $user_id]);
            }

            // Sync with clients table
            $stmt = $pdo->prepare("UPDATE clients SET name=?, phone=? WHERE user_id=?");
            $stmt->execute([$name, $phone, $user_id]);

            $pdo->commit();
            $_SESSION['user_name'] = $name; // Update session
            $success = "Profile updated successfully.";
            
            // Refresh data
            $profile['name'] = $name;
            $profile['phone'] = $phone;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to update profile. Please try again.";
        }
    }
}

include '../includes/header.php';
?>

<div class="container py-2">
    <div class="flex flex-between mb-2">
        <h2>My Profile</h2>
        <a href="dashboard.php" class="btn btn-outline-gold">&larr; Dashboard</a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="dashboard-grid">
        <div class="form-card mx-auto" style="max-width: 600px;">
            <form action="profile.php" method="POST">
                <div class="form-group">
                    <label>Email Address (Cannot be changed)</label>
                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($profile['email']); ?>" disabled style="background:#f4f4f4;">
                </div>
                
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($profile['name']); ?>">
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($profile['phone']); ?>">
                </div>
                
                <div class="form-group">
                    <label>Your Preferences / Notes</label>
                    <textarea class="form-control" rows="3" disabled style="background:#f4f4f4;"><?php echo htmlspecialchars($profile['preferences']); ?></textarea>
                    <small style="color: #888;">Only salon staff can update technical notes or history formulas.</small>
                </div>

                <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
                <h4 class="mb-1">Security</h4>

                <div class="form-group mb-2">
                    <label>New Password <small style="color: #888;">(Leave blank to keep current)</small></label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Changes</button>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
