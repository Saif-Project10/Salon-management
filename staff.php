<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireRole(['admin', 'receptionist']);

$error = '';
$success = '';

// Handle Add/Edit Staff Profile
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)$_POST['user_id'];
    $commission_rate = (float)$_POST['commission_rate'];
    $services = isset($_POST['services']) ? implode(',', $_POST['services']) : '';

    // Check if staff record exists
    $stmt = $pdo->prepare("SELECT id FROM staff WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $pdo->prepare("UPDATE staff SET commission_rate=?, services=? WHERE user_id=?");
        $stmt->execute([$commission_rate, $services, $user_id]);
        $success = "Staff profile updated successfully.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO staff (user_id, commission_rate, services) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $commission_rate, $services]);
        $success = "Staff profile created successfully.";
    }
}

// Fetch all staff users
$staffUsers = $pdo->query("SELECT u.id, u.name, u.role, s.commission_rate, s.services FROM users u LEFT JOIN staff s ON u.id = s.user_id WHERE u.role IN ('stylist', 'receptionist')")->fetchAll();

// Fetch all services
$allServices = $pdo->query("SELECT id, name FROM services")->fetchAll();

include 'includes/header.php';
?>

<div class="container py-2">
    <div class="flex flex-between mb-2">
        <h2>Staff Management</h2>
        <a href="/salon-management/admin/dashboard.php" class="btn btn-outline-gold">&larr; Dashboard</a>
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
            <h3 class="mb-1" id="form-title">Assign Staff Details</h3>
            <form action="staff.php" method="POST">
                <div class="form-group">
                    <label>Select Staff Member</label>
                    <select name="user_id" id="user_id" class="form-control" required onchange="loadStaffData(this)">
                        <option value="">-- Select --</option>
                        <?php foreach ($staffUsers as $su): ?>
                            <option value="<?php echo $su['id']; ?>" 
                                data-commission="<?php echo $su['commission_rate'] ?? 0; ?>" 
                                data-services="<?php echo $su['services'] ?? ''; ?>">
                                <?php echo htmlspecialchars($su['name']); ?> (<?php echo ucfirst($su['role']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Commission Rate (%)</label>
                    <input type="number" step="0.01" name="commission_rate" id="commission_rate" class="form-control" required min="0" max="100" value="0">
                </div>
                
                <div class="form-group">
                    <label>Assigned Services</label>
                    <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
                        <?php foreach ($allServices as $serv): ?>
                            <label style="display: block; margin-bottom: 5px; font-weight: normal;">
                                <input type="checkbox" name="services[]" value="<?php echo $serv['id']; ?>" class="service-cb"> 
                                <?php echo htmlspecialchars($serv['name']); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Staff Details</button>
            </form>
        </div>

        <!-- Table Panel -->
        <div class="table-responsive" style="grid-column: span 2;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Commission</th>
                        <th>Services Assigned</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staffUsers as $st): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($st['name']); ?></strong></td>
                        <td><?php echo ucfirst(htmlspecialchars($st['role'])); ?></td>
                        <td><?php echo number_format($st['commission_rate'] ?? 0, 2); ?>%</td>
                        <td>
                            <?php 
                            if (!empty($st['services'])) {
                                $s_ids = explode(',', $st['services']);
                                $s_names = [];
                                foreach($allServices as $aS) {
                                    if(in_array($aS['id'], $s_ids)) {
                                        $s_names[] = $aS['name'];
                                    }
                                }
                                echo htmlspecialchars(implode(', ', $s_names));
                            } else {
                                echo '<span style="color:#888;">None</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function loadStaffData(selectElement) {
    if (!selectElement.value) {
        document.getElementById('commission_rate').value = 0;
        document.querySelectorAll('.service-cb').forEach(cb => cb.checked = false);
        return;
    }
    
    const option = selectElement.options[selectElement.selectedIndex];
    const comm = option.getAttribute('data-commission');
    const servs = option.getAttribute('data-services').split(',');
    
    document.getElementById('commission_rate').value = comm || 0;
    
    document.querySelectorAll('.service-cb').forEach(cb => {
        cb.checked = servs.includes(cb.value);
    });
}
</script>

<?php include 'includes/footer.php'; ?>
