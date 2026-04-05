<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireRole(['admin', 'receptionist']);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_mapping'])) {
    verifyCsrfToken();

    $mappingId = (int) ($_POST['mapping_id'] ?? 0);
    $serviceId = (int) ($_POST['service_id'] ?? 0);
    $inventoryId = (int) ($_POST['inventory_id'] ?? 0);
    $quantityUsed = (float) ($_POST['quantity_used'] ?? 0);

    if ($serviceId <= 0 || $inventoryId <= 0 || $quantityUsed <= 0) {
        $error = 'Please choose a service, product, and positive usage quantity.';
    } else {
        try {
            if ($mappingId > 0) {
                $stmt = $pdo->prepare("
                    UPDATE service_inventory
                    SET service_id = ?, inventory_id = ?, quantity_used = ?
                    WHERE id = ?
                ");
                $stmt->execute([$serviceId, $inventoryId, $quantityUsed, $mappingId]);
                $success = 'Service inventory rule updated.';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO service_inventory (service_id, inventory_id, quantity_used)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE quantity_used = VALUES(quantity_used)
                ");
                $stmt->execute([$serviceId, $inventoryId, $quantityUsed]);
                $success = 'Service inventory rule saved.';
            }
        } catch (Exception $exception) {
            $error = 'The service inventory rule could not be saved.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_mapping'])) {
    verifyCsrfToken();
    try {
        $stmt = $pdo->prepare("DELETE FROM service_inventory WHERE id = ?");
        $stmt->execute([(int) ($_POST['mapping_id'] ?? 0)]);
        $success = 'Service inventory rule removed.';
    } catch (Exception $exception) {
        $error = 'The service inventory rule could not be removed.';
    }
}

$services = $pdo->query("SELECT id, name FROM services ORDER BY name ASC")->fetchAll();
$inventory = $pdo->query("SELECT id, product_name, quantity, min_stock FROM inventory ORDER BY product_name ASC")->fetchAll();
$mappings = $pdo->query("
    SELECT si.id, si.service_id, si.inventory_id, si.quantity_used,
           s.name AS service_name, i.product_name, i.quantity, i.min_stock
    FROM service_inventory si
    JOIN services s ON s.id = si.service_id
    JOIN inventory i ON i.id = si.inventory_id
    ORDER BY s.name ASC, i.product_name ASC
")->fetchAll();

include '../includes/header.php';
?>

<div class="container py-2">
    <div class="flex flex-between mb-2">
        <div>
            <span class="eyebrow">Service Inventory</span>
            <h2>Assign product usage to services</h2>
        </div>
        <a href="/salon-management/admin/dashboard.php" class="btn btn-outline-gold">&larr; Dashboard</a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="dashboard-grid">
        <div class="form-card" style="margin-top:0;">
            <h3 class="mb-1">Add Usage Rule</h3>
            <form method="POST">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="mapping_id" id="mapping_id">

                <div class="form-group">
                    <label>Service</label>
                    <select name="service_id" id="service_id" class="form-control" required>
                        <option value="">Select service</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?php echo (int) $service['id']; ?>"><?php echo htmlspecialchars($service['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Inventory Product</label>
                    <select name="inventory_id" id="inventory_id" class="form-control" required>
                        <option value="">Select product</option>
                        <?php foreach ($inventory as $item): ?>
                            <option value="<?php echo (int) $item['id']; ?>"><?php echo htmlspecialchars($item['product_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Quantity Used Per Appointment</label>
                    <input type="number" step="0.01" min="0.01" name="quantity_used" id="quantity_used" class="form-control" required>
                </div>

                <button type="submit" name="save_mapping" class="btn btn-primary" style="width:100%;">Save Rule</button>
                <button type="button" class="btn btn-outline-gold mt-1" id="cancel-edit" style="width:100%; display:none;" onclick="resetMappingForm()">Cancel Edit</button>
            </form>
        </div>

        <div class="table-responsive" style="grid-column: span 2;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Product</th>
                        <th>Qty Used</th>
                        <th>Current Stock</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mappings as $mapping): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($mapping['service_name']); ?></td>
                            <td><?php echo htmlspecialchars($mapping['product_name']); ?></td>
                            <td><?php echo number_format((float) $mapping['quantity_used'], 2); ?></td>
                            <td>
                                <?php echo (int) $mapping['quantity']; ?>
                                <small>(Min: <?php echo (int) $mapping['min_stock']; ?>)</small>
                            </td>
                            <td>
                                <button
                                    class="btn btn-outline-gold"
                                    style="padding: 5px 10px; font-size: 0.8rem;"
                                    onclick="editMapping(<?php echo (int) $mapping['id']; ?>, <?php echo (int) $mapping['service_id']; ?>, <?php echo (int) $mapping['inventory_id']; ?>, '<?php echo number_format((float) $mapping['quantity_used'], 2, '.', ''); ?>')"
                                >
                                    Edit
                                </button>
                                <form method="POST" style="display:inline;">
                                    <?php echo csrfInput(); ?>
                                    <input type="hidden" name="mapping_id" value="<?php echo (int) $mapping['id']; ?>">
                                    <button type="submit" name="delete_mapping" class="btn btn-danger" style="padding: 5px 10px; font-size: 0.8rem;" onclick="return confirm('Delete this usage rule?');">Del</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$mappings): ?>
                        <tr>
                            <td colspan="5">No service inventory rules have been created yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function editMapping(id, serviceId, inventoryId, quantityUsed) {
    document.getElementById('mapping_id').value = id;
    document.getElementById('service_id').value = serviceId;
    document.getElementById('inventory_id').value = inventoryId;
    document.getElementById('quantity_used').value = quantityUsed;
    document.getElementById('cancel-edit').style.display = 'block';
}

function resetMappingForm() {
    document.getElementById('mapping_id').value = '';
    document.getElementById('service_id').value = '';
    document.getElementById('inventory_id').value = '';
    document.getElementById('quantity_used').value = '';
    document.getElementById('cancel-edit').style.display = 'none';
}
</script>

<?php include '../includes/footer.php'; ?>
