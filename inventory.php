<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireRole(['admin', 'receptionist']);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_purchase_orders'])) {
    verifyCsrfToken();
    try {
        $pdo->beginTransaction();
        $created = salonCheckAndGenerateAutoPO($pdo, (int) $_SESSION['user_id']);
        $pdo->commit();

        if ($created > 0) {
            $success = $created . " purchase order(s) generated from low-stock items.";
        } else {
            $error = "No new purchase orders were needed. Existing draft/ordered items may already be covered.";
        }
    } catch (Exception $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Purchase order generation failed.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    verifyCsrfToken();
    try {
        $pdo->beginTransaction();
        $id = (int) ($_POST['delete_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
        $stmt->execute([$id]);
        salonCheckAndGenerateAutoPO($pdo, (int) $_SESSION['user_id']);
        $pdo->commit();
        $success = "Item deleted successfully.";
    } catch (Exception $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Item could not be deleted.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_item'])) {
    verifyCsrfToken();
    $item_id = isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0;
    $product_name = trim($_POST['product_name']);
    $quantity = (int) $_POST['quantity'];
    $price = (float) $_POST['price'];
    $supplier = trim($_POST['supplier']);
    $min_stock = (int) $_POST['min_stock'];

    if (empty($product_name)) {
        $error = "Product name is required.";
    } else {
        try {
            $pdo->beginTransaction();
            if ($item_id > 0) {
                $stmt = $pdo->prepare("UPDATE inventory SET product_name=?, quantity=?, price=?, supplier=?, min_stock=? WHERE id=?");
                $stmt->execute([$product_name, $quantity, $price, $supplier, $min_stock, $item_id]);
                $success = "Item updated successfully.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO inventory (product_name, quantity, price, supplier, min_stock) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$product_name, $quantity, $price, $supplier, $min_stock]);
                $success = "New item added successfully.";
            }

            salonCheckAndGenerateAutoPO($pdo, (int) $_SESSION['user_id']);
            $pdo->commit();
        } catch (Exception $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Inventory could not be saved.";
        }
    }
}

$inventory = $pdo->query("SELECT * FROM inventory ORDER BY product_name ASC")->fetchAll();
$openPurchaseOrders = (int) $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('draft', 'ordered')")->fetchColumn();

include 'includes/header.php';
?>

<div class="container py-2">
    <div class="flex flex-between mb-2">
        <div>
            <h2>Inventory Management</h2>
            <small><?php echo $openPurchaseOrders; ?> open purchase order(s)</small>
        </div>
        <div class="filter-row">
            <form method="POST">
                <?php echo csrfInput(); ?>
                <button type="submit" name="generate_purchase_orders" class="btn btn-primary">Generate Purchase Orders</button>
            </form>
            <a href="/salon-management/purchase_orders.php" class="btn btn-outline-gold">View Purchase Orders</a>
            <a href="/salon-management/admin/dashboard.php" class="btn btn-outline-gold">&larr; Dashboard</a>
        </div>
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
            <h3 class="mb-1" id="form-title">Add New Item</h3>
            <form action="inventory.php" method="POST">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="item_id" id="item_id">
                
                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" name="product_name" id="product_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Quantity in Stock</label>
                    <input type="number" name="quantity" id="quantity" class="form-control" required min="0">
                </div>
                <div class="form-group">
                    <label>Price / Cost</label>
                    <input type="number" step="0.01" name="price" id="price" class="form-control" required min="0">
                </div>
                <div class="form-group">
                    <label>Supplier</label>
                    <input type="text" name="supplier" id="supplier" class="form-control">
                </div>
                <div class="form-group">
                    <label>Minimum Stock Alert Level</label>
                    <input type="number" name="min_stock" id="min_stock" class="form-control" required value="5" min="0">
                </div>
                <button type="submit" name="save_item" class="btn btn-primary" style="width: 100%;">Save Item</button>
                <button type="button" class="btn btn-outline-gold mt-1" style="width: 100%; display:none;" id="btn-cancel" onclick="resetForm()">Cancel Edit</button>
            </form>
        </div>

        <!-- Table View -->
        <div class="table-responsive" style="grid-column: span 2;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Stock</th>
                        <th>Price</th>
                        <th>Supplier</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventory as $item): ?>
                    <?php $isLow = $item['quantity'] <= $item['min_stock']; ?>
                    <tr style="<?php echo $isLow ? 'background-color: #fff3cd;' : ''; ?>">
                        <td><strong><?php echo htmlspecialchars($item['product_name']); ?></strong></td>
                        <td>
                            <?php echo $item['quantity']; ?> 
                            <span style="font-size: 0.8rem; color: #666;">(Min: <?php echo $item['min_stock']; ?>)</span>
                        </td>
                        <td>$<?php echo number_format($item['price'], 2); ?></td>
                        <td><?php echo htmlspecialchars($item['supplier']); ?></td>
                        <td>
                            <?php if($isLow): ?>
                                <span class="badge badge-cancelled">Low Stock!</span>
                            <?php else: ?>
                                <span class="badge badge-completed">In Stock</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button onclick="editItem(<?php echo $item['id']; ?>, '<?php echo addslashes(htmlspecialchars($item['product_name'])); ?>', <?php echo $item['quantity']; ?>, <?php echo $item['price']; ?>, '<?php echo addslashes(htmlspecialchars($item['supplier'])); ?>', <?php echo $item['min_stock']; ?>)" class="btn btn-outline-gold" style="padding: 5px 10px; font-size: 0.8rem;">Edit</button>
                            <form method="POST" style="display:inline;">
                                <?php echo csrfInput(); ?>
                                <input type="hidden" name="delete_id" value="<?php echo (int) $item['id']; ?>">
                                <button type="submit" name="delete_item" class="btn btn-danger" style="padding: 5px 10px; font-size: 0.8rem;" onclick="return confirm('Delete this product?');">Del</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function editItem(id, name, qty, price, supplier, minStock) {
    document.getElementById('item_id').value = id;
    document.getElementById('product_name').value = name;
    document.getElementById('quantity').value = qty;
    document.getElementById('price').value = price;
    document.getElementById('supplier').value = supplier;
    document.getElementById('min_stock').value = minStock;
    document.getElementById('form-title').innerText = 'Edit Item';
    document.getElementById('btn-cancel').style.display = 'block';
}

function resetForm() {
    document.getElementById('item_id').value = '';
    document.getElementById('product_name').value = '';
    document.getElementById('quantity').value = '';
    document.getElementById('price').value = '';
    document.getElementById('supplier').value = '';
    document.getElementById('min_stock').value = '5';
    document.getElementById('form-title').innerText = 'Add New Item';
    document.getElementById('btn-cancel').style.display = 'none';
}
</script>

<?php include 'includes/footer.php'; ?>
