<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireRole('admin');

$error = '';
$success = '';

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id === $_SESSION['user_id']) {
        $error = "You cannot delete your own account.";
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $success = "User deleted successfully.";
    }
}

// Handle Add/Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    $password = $_POST['password'];

    if (empty($name) || empty($username) || empty($email) || empty($role)) {
        $error = "Name, username, email, and role are required.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $error = "Username must be 3-30 characters and use only letters, numbers, or underscores.";
    } else {
        if ($user_id > 0) {
            $check = $pdo->prepare("SELECT id FROM users WHERE (email = ? OR username = ?) AND id != ?");
            $check->execute([$email, $username, $user_id]);
            if ($check->fetch()) {
                $error = "Email or username already exists.";
            } else {
            // Update
                if (!empty($password)) {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET name=?, username=?, email=?, phone=?, role=?, password=? WHERE id=?");
                    $stmt->execute([$name, $username, $email, $phone, $role, $hashed, $user_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET name=?, username=?, email=?, phone=?, role=? WHERE id=?");
                    $stmt->execute([$name, $username, $email, $phone, $role, $user_id]);
                }
                $success = "User updated successfully.";
            }
        } else {
            // Insert
            if (empty($password)) {
                $error = "Password is required for new users.";
            } else {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
                $stmt->execute([$email, $username]);
                if ($stmt->fetch()) {
                    $error = "Email or username already exists.";
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (name, username, email, phone, role, password) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $username, $email, $phone, $role, $hashed]);
                    $success = "User added successfully.";
                }
            }
        }
    }
}

// Fetch all users
$users = $pdo->query("SELECT id, name, username, email, phone, role, created_at FROM users ORDER BY created_at DESC")->fetchAll();

include '../includes/header.php';
?>

<div class="container py-2">
    <div class="flex flex-between mb-2">
        <h2>Manage Users</h2>
        <a href="/salon-management/admin/dashboard.php" class="btn btn-outline-gold">&larr; Back to Dashboard</a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="dashboard-grid">
        <!-- Form Panel -->
        <div class="form-card" style="margin-top:0;">
            <h3 class="mb-1" id="form-title">Add New User</h3>
            <form action="manage_users.php" method="POST">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="user_id" id="user_id">
                
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" id="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" id="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" id="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" id="phone" class="form-control">
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" id="role" class="form-control" required>
                        <option value="client">Client</option>
                        <option value="stylist">Stylist</option>
                        <option value="receptionist">Receptionist</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Password <small style="color: #888;">(leave blank to keep current password if editing)</small></label>
                    <input type="password" name="password" id="password" class="form-control">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Save User</button>
                <button type="button" class="btn btn-outline-gold mt-1" style="width: 100%; display:none;" id="btn-cancel" onclick="resetForm()">Cancel Edit</button>
            </form>
        </div>

        <!-- Table Panel -->
        <div class="table-responsive" style="grid-column: span 2;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Joined Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($u['name']); ?></td>
                        <td><?php echo htmlspecialchars($u['username'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                        <td><span class="badge" style="background:var(--color-primary);color:var(--color-black);"><?php echo htmlspecialchars(ucfirst($u['role'])); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                        <td>
                            <button onclick="editUser(<?php echo $u['id']; ?>, '<?php echo addslashes(htmlspecialchars($u['name'])); ?>', '<?php echo addslashes(htmlspecialchars($u['username'])); ?>', '<?php echo addslashes(htmlspecialchars($u['email'])); ?>', '<?php echo addslashes(htmlspecialchars($u['phone'])); ?>', '<?php echo $u['role']; ?>')" class="btn btn-outline-gold" style="padding: 5px 10px; font-size: 0.8rem;">Edit</button>
                            <a href="manage_users.php?delete=<?php echo $u['id']; ?>" class="btn btn-danger" style="padding: 5px 10px; font-size: 0.8rem;" onclick="return confirm('Are you sure you want to delete this user?');">Del</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function editUser(id, name, username, email, phone, role) {
    document.getElementById('user_id').value = id;
    document.getElementById('name').value = name;
    document.getElementById('username').value = username;
    document.getElementById('email').value = email;
    document.getElementById('phone').value = phone;
    document.getElementById('role').value = role;
    document.getElementById('form-title').innerText = 'Edit User';
    document.getElementById('btn-cancel').style.display = 'block';
}

function resetForm() {
    document.getElementById('user_id').value = '';
    document.getElementById('name').value = '';
    document.getElementById('username').value = '';
    document.getElementById('email').value = '';
    document.getElementById('phone').value = '';
    document.getElementById('role').value = 'client';
    document.getElementById('form-title').innerText = 'Add New User';
    document.getElementById('btn-cancel').style.display = 'none';
}
</script>

<?php include '../includes/footer.php'; ?>
