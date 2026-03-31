<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireRole(['admin', 'receptionist', 'stylist']);

$error = '';
$success = '';

if (isset($_GET['delete']) && hasRole(['admin', 'receptionist'])) {
    $id = (int) $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
    $stmt->execute([$id]);
    $success = 'Client deleted successfully.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasRole(['admin', 'receptionist'])) {
    $client_id = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $preferences = trim($_POST['preferences']);

    if ($name === '') {
        $error = 'Client name is required.';
    } else {
        if ($client_id > 0) {
            $stmt = $pdo->prepare("UPDATE clients SET name = ?, email = ?, phone = ?, preferences = ? WHERE id = ?");
            $stmt->execute([$name, $email, $phone, $preferences, $client_id]);
            $success = 'Client updated successfully.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO clients (name, email, phone, preferences) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone, $preferences]);
            $success = 'New client added successfully.';
        }
    }
}

$search = trim($_GET['search'] ?? '');
$whereClause = '';
$params = [];
if ($search !== '') {
    $whereClause = "WHERE c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}

$stmt = $pdo->prepare("
    SELECT c.*, COUNT(a.id) AS total_visits, MAX(a.appointment_date) AS last_visit
    FROM clients c
    LEFT JOIN appointments a ON a.client_id = c.id AND a.status != 'cancelled'
    $whereClause
    GROUP BY c.id
    ORDER BY c.name ASC
");
$stmt->execute($params);
$clients = $stmt->fetchAll();

$historyStmt = $pdo->query("
    SELECT c.id AS client_id, c.name AS client_name, a.appointment_date, s.name AS service_name, u.name AS stylist_name, a.status
    FROM clients c
    LEFT JOIN appointments a ON a.client_id = c.id
    LEFT JOIN services s ON s.id = a.service_id
    LEFT JOIN users u ON u.id = a.stylist_id
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$historyMap = [];
foreach ($historyStmt as $row) {
    if (!$row['appointment_date']) {
        continue;
    }
    $historyMap[$row['client_id']][] = $row;
}

include 'includes/header.php';
?>

<div class="container py-2">
    <div class="flex flex-between mb-2">
        <div>
            <span class="eyebrow">Client CRM</span>
            <h2>Profiles, preferences, and service history</h2>
        </div>
        <a href="<?php echo dashboardUrlForRole(currentUserRole()); ?>" class="btn btn-outline-gold">Back to Dashboard</a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="dashboard-grid">
        <?php if (hasRole(['admin', 'receptionist'])): ?>
        <div class="form-card" style="margin-top:0;">
            <h3 class="mb-1">Client profile editor</h3>
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
                    <label>Preferences / Notes</label>
                    <textarea name="preferences" id="client_preferences" class="form-control" rows="4"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Save Client</button>
            </form>
        </div>
        <?php endif; ?>

        <div class="detail-card" style="<?php echo hasRole(['admin', 'receptionist']) ? 'grid-column: span 2;' : 'grid-column: 1 / -1;'; ?>">
            <div class="flex flex-between mb-1">
                <h3>Client Directory</h3>
                <form method="GET" class="filter-row">
                    <input type="text" name="search" class="form-control" placeholder="Search clients" value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-outline-gold">Search</button>
                </form>
            </div>
            <div class="history-list">
                <?php foreach ($clients as $client): ?>
                    <div class="history-item">
                        <div class="flex flex-between">
                            <div>
                                <strong><?php echo htmlspecialchars($client['name']); ?></strong>
                                <div><?php echo htmlspecialchars($client['phone'] ?: 'No phone'); ?> | <?php echo htmlspecialchars($client['email'] ?: 'No email'); ?></div>
                                <small><?php echo (int) $client['total_visits']; ?> visits | Last visit: <?php echo $client['last_visit'] ? date('M d, Y', strtotime($client['last_visit'])) : 'No visits yet'; ?></small>
                            </div>
                            <?php if (hasRole(['admin', 'receptionist'])): ?>
                            <div class="filter-row">
                                <button type="button" class="btn btn-outline-gold" onclick="editClient(<?php echo $client['id']; ?>, '<?php echo addslashes(htmlspecialchars($client['name'])); ?>', '<?php echo addslashes(htmlspecialchars($client['email'])); ?>', '<?php echo addslashes(htmlspecialchars($client['phone'])); ?>', '<?php echo addslashes(htmlspecialchars($client['preferences'])); ?>')">Edit</button>
                                <a href="clients.php?delete=<?php echo $client['id']; ?>" class="btn btn-danger" onclick="return confirm('Delete this client?');">Delete</a>
                            </div>
                            <?php endif; ?>
                        </div>
                        <p><?php echo htmlspecialchars($client['preferences'] ?: 'No preferences added yet.'); ?></p>
                        <?php if (!empty($historyMap[$client['id']])): ?>
                            <div class="notification-list">
                                <?php foreach (array_slice($historyMap[$client['id']], 0, 3) as $visit): ?>
                                    <div class="notification-item">
                                        <strong><?php echo htmlspecialchars($visit['service_name']); ?></strong>
                                        <p><?php echo htmlspecialchars($visit['stylist_name']); ?> | <?php echo ucfirst($visit['status']); ?></p>
                                        <small><?php echo date('M d, Y', strtotime($visit['appointment_date'])); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
function editClient(id, name, email, phone, preferences) {
    document.getElementById('client_id').value = id;
    document.getElementById('client_name').value = name;
    document.getElementById('client_email').value = email;
    document.getElementById('client_phone').value = phone;
    document.getElementById('client_preferences').value = preferences;
}
</script>

<?php include 'includes/footer.php'; ?>
