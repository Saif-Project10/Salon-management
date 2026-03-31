<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

redirectLoggedInUser();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            
            redirectLoggedInUser();
        } else {
            $error = "Invalid email or password.";
        }
    }
}

include 'includes/header.php';
?>

<div class="auth-wrapper">
    <div class="form-card">
        <h2 class="text-center mb-1">Welcome Back</h2>
        <p class="text-center mb-2" style="color: #666;">Sign in to Elegance Salon</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php showAlert(); // to show any messages generated through header ?error=... ?>

        <form action="login.php" method="POST" class="validate-form">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" required placeholder="you@example.com">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required placeholder="••••••••">
            </div>

            <div class="form-group mb-2" style="display:flex; justify-content:space-between; font-size: 0.9rem;">
                <!-- <label><input type="checkbox"> Remember Me</label> -->
                <a href="#" style="color: var(--color-primary);">Forgot Password?</a>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">Sign In</button>
            <p class="text-center mt-2" style="font-size: 0.9rem;">
                Don't have an account? <a href="register.php" style="color: var(--color-primary); font-weight: 500;">Sign up</a>
            </p>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
