<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireRole(['admin', 'receptionist', 'stylist']); // Stylists can view clients

$error = '';
$success = '';

// Handle Delete (Only Admin/Receptionist)
if (isset($_GET['delete']) && hasRole(['admin', 'receptionist'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
    $stmt->execute([$id]);
    $success = "Client deleted successfully.";
}

// Handle Add/Edit (Only Admin/Receptionist)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasRole(['admin', 'receptionist'])) {
    $client_id = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $preferences = trim($_POST['preferences']);

    if (empty($name)) {
        $error = "Client name is required.";
    } else {
        if ($client_id > 0) {
            $stmt = $pdo->prepare("UPDATE clients SET name=?, email=?, phone=?, preferences=? WHERE id=?");
            $stmt->execute([$name, $email, $phone, $preferences, $client_id]);
            $success = "Client updated successfully.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO clients (name, email, phone, preferences) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone, $preferences]);
            $success = "New client added successfully.";
        }
    }
}

// Search Logic
$search = $_GET['search'] ?? '';
$whereClause = "";
$params = [];
if (!empty($search)) {
    $whereClause = "WHERE name LIKE ? OR email LIKE ? OR phone LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}

$stmt = $pdo->prepare("SELECT * FROM clients $whereClause ORDER BY name ASC");
$stmt->execute($params);
$clients = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container py-2">
    <div class="flex flex-between mb-2">
        <h2>Client Management</h2>
        <?php if (hasRole(['admin', 'receptionist'])): ?>
            <a href="/salon-management/admin/dashboard.php" class="btn btn-outline-gold">&larr; Dashboard</a>
        <?php else: ?>
            <a href="/salon-management/stylist/dashboard.php" class="btn btn-outline-gold">&larr; Dashboard</a>
        <?php endif; ?>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="dashboard-grid">
        <?php if (hasRole(['admin', 'receptionist'])): ?>
        <!-- Form Panel -->
        <div class="form-card" style="margin-top:0;">
            <h3 class="mb-1" id="form-title">Add New Client</h3>
            <form action="clients.php" method="POST">
                <input type="hidden" name="client_id" id="client_id">
                
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="name" id="client_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" id="client_email" class="form-control">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" id="client_phone" class="form-control">
                </div>
                <div class="form-group">
                    <label>Preferences / Formulas / Notes</label>
                    <textarea name="preferences" id="client_preferences" class="form-control" rows="3"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Client</button>
                <button type="button" class="btn btn-outline-gold mt-1" style="width: 100%; display:none;" id="btn-cancel" onclick="resetForm()">Cancel Edit</button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Table View -->
        <div class="table-responsive" style="<?php echo hasRole(['admin', 'receptionist']) ? 'grid-column: span 2;' : 'grid-column: 1 / -1;'; ?>">
            
            <form action="clients.php" method="GET" class="mb-2" style="display: flex; gap: 10px;">
                <input type="text" name="search" class="form-control" placeholder="Search by name, email, or phone..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 1;">
                <button type="submit" class="btn btn-dark">Search</button>
                <?php if(!empty($search)): ?>
                    <a href="clients.php" class="btn btn-outline-gold">Clear</a>
                <?php endif; ?>
            </form>

            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Preferences</th>
                        <?php if (hasRole(['admin', 'receptionist'])): ?>
                        <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($client['name']); ?></strong><br><small style="color: #888;">Added: <?php echo date('M d, Y', strtotime($client['created_at'])); ?></small></td>
                        <td>
                            <?php echo htmlspecialchars($client['phone']); ?><br>
                            <?php echo htmlspecialchars($client['email']); ?>
                        </td>
                        <td>
                            <div style="font-size:0.9rem; color:#555; max-width: 200px; max-height: 60px; overflow:hidden; text-overflow: ellipsis;">
                                <?php echo nl2br(htmlspecialchars($client['preferences'])); ?>
                            </div>
                        </td>
                        <?php if (hasRole(['admin', 'receptionist'])): ?>
                        <td>
                            <button onclick="editClient(<?php echo $client['id']; ?>, '<?php echo addslashes(htmlspecialchars($client['name'])); ?>', '<?php echo addslashes(htmlspecialchars($client['email'])); ?>', '<?php echo addslashes(htmlspecialchars($client['phone'])); ?>', '<?php echo addslashes(preg_replace('/\r|\n/', '\\n', htmlspecialchars($client['preferences']))); ?>')" class="btn btn-outline-gold" style="padding: 5px 10px; font-size: 0.8rem;">Edit</button>
                            <a href="clients.php?delete=<?php echo $client['id']; ?>" class="btn btn-danger" style="padding: 5px 10px; font-size: 0.8rem;" onclick="return confirm('Delete this client? History will be kept via user links or nullified.');">Del</a>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function editClient(id, name, email, phone, pref) {
    document.getElementById('client_id').value = id;
    document.getElementById('client_name').value = name;
    document.getElementById('client_email').value = email;
    document.getElementById('client_phone').value = phone;
    document.getElementById('client_preferences').value = pref.replace(/\\n/g, '\n');
    document.getElementById('form-title').innerText = 'Edit Client';
    document.getElementById('btn-cancel').style.display = 'block';
}

function resetForm() {
    document.getElementById('client_id').value = '';
    document.getElementById('client_name').value = '';
    document.getElementById('client_email').value = '';
    document.getElementById('client_phone').value = '';
    document.getElementById('client_preferences').value = '';
    document.getElementById('form-title').innerText = 'Add New Client';
    document.getElementById('btn-cancel').style.display = 'none';
}
</script>

<?php include 'includes/footer.php'; ?>
