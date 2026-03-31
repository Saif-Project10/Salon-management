<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

redirectLoggedInUser();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($name) || empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Please fill in all required fields.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $error = "Username must be 3-30 characters and use only letters, numbers, or underscores.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        if ($stmt->fetch()) {
            $error = "That email address or username is already registered.";
        } else {
            try {
                $pdo->beginTransaction();

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $role = 'client'; // Default registration is a client (user)

                $stmt = $pdo->prepare("INSERT INTO users (name, username, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $username, $email, $phone, $hashed_password, $role]);
                $user_id = $pdo->lastInsertId();

                // Create a client record linked to this user
                $clientStmt = $pdo->prepare("INSERT INTO clients (user_id, name, phone, email) VALUES (?, ?, ?, ?)");
                $clientStmt->execute([$user_id, $name, $phone, $email]);

                $pdo->commit();

                // Auto login
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_role'] = $role;
                $_SESSION['success'] = "Registration successful! Welcome to Elegance Salon.";
                rotateCsrfToken();
                
                redirectLoggedInUser();

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Registration failed. Please try again later.";
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="auth-wrapper">
    <div class="form-card" style="max-width: 500px;">
        <h2 class="text-center mb-1">Join the Club</h2>
        <p class="text-center mb-2" style="color: #666;">Create an account to book your next session</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="register.php" method="POST" class="validate-form">
            <?php echo csrfInput(); ?>
            <div class="form-group">
                <label for="name">Full Name *</label>
                <input type="text" id="name" name="name" class="form-control" required placeholder="Jane Doe" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="username">Username *</label>
                <input type="text" id="username" name="username" class="form-control" required placeholder="jane_doe" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email" class="form-control" required placeholder="you@example.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="text" id="phone" name="phone" class="form-control" placeholder="(555) 123-4567" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Password *</label>
                <input type="password" id="password" name="password" class="form-control" required placeholder="Create a strong password">
            </div>

            <div class="form-group mb-2">
                <label for="confirm_password">Confirm Password *</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required placeholder="Repeat password">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">Create Account</button>
            <p class="text-center mt-2" style="font-size: 0.9rem;">
                Already have an account? <a href="login.php" style="color: var(--color-primary); font-weight: 500;">Sign in</a>
            </p>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
