<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireRole(['admin', 'receptionist']);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_po_status'])) {
    verifyCsrfToken();
    $purchaseOrderId = (int) ($_POST['purchase_order_id'] ?? 0);
    $status = $_POST['status'] ?? 'draft';
    $allowed = ['draft', 'ordered', 'received'];

    if (in_array($status, $allowed, true)) {
        $stmt = $pdo->prepare("UPDATE purchase_orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $purchaseOrderId]);
        $success = 'Purchase order status updated.';
    }
}

$orders = $pdo->query("
    SELECT po.*, u.name AS created_by_name
    FROM purchase_orders po
    LEFT JOIN users u ON u.id = po.created_by
    ORDER BY po.created_at DESC
")->fetchAll();

$itemsStmt = $pdo->query("
    SELECT poi.*, po.supplier
    FROM purchase_order_items poi
    JOIN purchase_orders po ON po.id = poi.purchase_order_id
    ORDER BY poi.purchase_order_id DESC, poi.product_name ASC
");
$itemsByOrder = [];
foreach ($itemsStmt as $row) {
    $itemsByOrder[$row['purchase_order_id']][] = $row;
}

include 'includes/header.php';
?>

<div class="container py-2">
    <div class="flex flex-between mb-2">
        <div>
            <span class="eyebrow">Purchase Orders</span>
            <h2>Auto-generated supplier orders</h2>
        </div>
        <a href="/salon-management/inventory.php" class="btn btn-outline-gold">Back to Inventory</a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="history-list">
        <?php foreach ($orders as $order): ?>
            <div class="detail-card">
                <div class="flex flex-between mb-1">
                    <div>
                        <h3 style="margin-bottom:6px;">PO #<?php echo (int) $order['id']; ?> - <?php echo htmlspecialchars($order['supplier']); ?></h3>
                        <small>Created <?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?> by <?php echo htmlspecialchars($order['created_by_name'] ?: 'System'); ?></small>
                    </div>
                    <form method="POST" class="filter-row">
                        <?php echo csrfInput(); ?>
                        <input type="hidden" name="purchase_order_id" value="<?php echo (int) $order['id']; ?>">
                        <select name="status" class="form-control">
                            <option value="draft" <?php echo $order['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="ordered" <?php echo $order['status'] === 'ordered' ? 'selected' : ''; ?>>Ordered</option>
                            <option value="received" <?php echo $order['status'] === 'received' ? 'selected' : ''; ?>>Received</option>
                        </select>
                        <button type="submit" name="update_po_status" class="btn btn-outline-gold">Update</button>
                    </form>
                </div>

                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Quantity Needed</th>
                            <th>Unit Cost</th>
                            <th>Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itemsByOrder[$order['id']] ?? [] as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td><?php echo (int) $item['quantity_needed']; ?></td>
                                <td>$<?php echo number_format((float) $item['unit_cost'], 2); ?></td>
                                <td>$<?php echo number_format((float) $item['line_total'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="flex flex-between">
                    <span class="badge badge-<?php echo $order['status'] === 'received' ? 'completed' : ($order['status'] === 'ordered' ? 'confirmed' : 'pending'); ?>">
                        <?php echo ucfirst($order['status']); ?>
                    </span>
                    <strong>Total: $<?php echo number_format((float) $order['total_amount'], 2); ?></strong>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$orders): ?>
            <p>No purchase orders have been generated yet.</p>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
