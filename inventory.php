<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireRole(['admin', 'receptionist']);

$error = '';
$success = '';

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
    $stmt->execute([$id]);
    $success = "Item deleted successfully.";
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $product_name = trim($_POST['product_name']);
    $quantity = (int)$_POST['quantity'];
    $price = (float)$_POST['price'];
    $supplier = trim($_POST['supplier']);
    $min_stock = (int)$_POST['min_stock'];

    if (empty($product_name)) {
        $error = "Product name is required.";
    } else {
        if ($item_id > 0) {
            $stmt = $pdo->prepare("UPDATE inventory SET product_name=?, quantity=?, price=?, supplier=?, min_stock=? WHERE id=?");
            $stmt->execute([$product_name, $quantity, $price, $supplier, $min_stock, $item_id]);
            $success = "Item updated successfully.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO inventory (product_name, quantity, price, supplier, min_stock) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$product_name, $quantity, $price, $supplier, $min_stock]);
            $success = "New item added successfully.";
        }
    }
}

$inventory = $pdo->query("SELECT * FROM inventory ORDER BY product_name ASC")->fetchAll();

include 'includes/header.php';
?>

<div class="container py-2">
    <div class="flex flex-between mb-2">
        <h2>Inventory Management</h2>
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
            <h3 class="mb-1" id="form-title">Add New Item</h3>
            <form action="inventory.php" method="POST">
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
                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Item</button>
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
                            <a href="inventory.php?delete=<?php echo $item['id']; ?>" class="btn btn-danger" style="padding: 5px 10px; font-size: 0.8rem;" onclick="return confirm('Delete this product?');">Del</a>
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
