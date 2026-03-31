<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $message = trim($_POST['message']);

    if (empty($name) || empty($email) || empty($message)) {
        $error = "Please fill in all fields.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO feedback (name, email, message) VALUES (?, ?, ?)");
            $stmt->execute([$name, $email, $message]);
            $success = "Thank you for your feedback! We appreciate your thoughts.";
            // Clear inputs
            $_POST = [];
        } catch (PDOException $e) {
            $error = "An error occurred while submitting your feedback. Please try again later.";
        }
    }
}

include 'includes/header.php';
?>

<div class="auth-wrapper">
    <div class="form-card" style="max-width: 600px;">
        <h2 class="text-center mb-1">Your Feedback Matters</h2>
        <p class="text-center mb-2" style="color: #666;">Help us improve Elegance Salon</p>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="feedback.php" method="POST" class="validate-form">
            <div class="form-group">
                <label for="name">Your Name *</label>
                <input type="text" id="name" name="name" class="form-control" required placeholder="John Doe" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email" class="form-control" required placeholder="you@example.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="message">Message *</label>
                <textarea id="message" name="message" class="form-control" rows="5" required placeholder="Write your feedback here..."><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">Submit Feedback</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
