<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

redirectLoggedInUser();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $identifier = trim($_POST['identifier']);
    $password = $_POST['password'];

    if (empty($identifier) || empty($password)) {
        $error = "Please enter your username or email and password.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            rotateCsrfToken();
            
            redirectLoggedInUser();
        } else {
            $error = "Invalid username/email or password.";
        }
    }
}

include 'includes/header.php';
?>

<style>
.auth-wrapper {
    min-height: calc(100vh - 80px); /* Adjust based on header height */
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(rgba(16,13,10,0.75), rgba(16,13,10,0.85)), url('/salon-management/assets/images/hairstyle.jpg') center/cover no-repeat;
    padding: 60px 20px;
}
</style>

<div class="auth-wrapper">
    <div class="form-card" style="max-width: 450px; width: 100%; margin: 0; box-shadow: 0 30px 60px rgba(0,0,0,0.4);">
        <h2 class="text-center mb-1">Welcome Back</h2>
        <p class="text-center mb-2" style="color: #666;">Sign in to Elegance Salon</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php showAlert(); // to show any messages generated through header ?error=... ?>

        <form action="login.php" method="POST" class="validate-form">
            <?php echo csrfInput(); ?>
            <div class="form-group">
                <label for="identifier">Username or Email</label>
                <input type="text" id="identifier" name="identifier" class="form-control" required placeholder="username or you@example.com">
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
