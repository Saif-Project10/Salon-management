<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireRole(['admin', 'receptionist']);

$error = '';
$success = '';
$invoiceHTML = '';
$appDetails = null;

if (salonTableExists($pdo, 'payments') && !salonColumnExists($pdo, 'payments', 'discount')) {
    $pdo->exec("ALTER TABLE payments ADD COLUMN discount DECIMAL(10,2) DEFAULT 0.00 AFTER amount");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay'])) {
    verifyCsrfToken();
    $appointment_id = (int) $_POST['appointment_id'];
    $postedAmount = (float) ($_POST['amount'] ?? 0);
    $discount = (float) ($_POST['discount'] ?? 0);
    if ($discount < 0) {
        $discount = 0;
    }
    $payment_method = trim($_POST['payment_method']);

    try {
        $pdo->beginTransaction();

        $amountStmt = $pdo->prepare("
            SELECT s.price AS amount
            FROM appointments a
            JOIN services s ON s.id = a.service_id
            WHERE a.id = ?
              AND a.status IN ('pending', 'confirmed')
            FOR UPDATE
        ");
        $amountStmt->execute([$appointment_id]);
        $amountRow = $amountStmt->fetch();
        if (!$amountRow) {
            throw new Exception('Invalid appointment or already processed.');
        }

        $originalAmount = (float) $amountRow['amount'];
        if ($postedAmount > 0 && abs($postedAmount - $originalAmount) > 0.01) {
            $originalAmount = $postedAmount;
        }
        if ($discount > $originalAmount) {
            $discount = $originalAmount;
        }
        $finalAmount = max(0, $originalAmount - $discount);

        $stmt = $pdo->prepare("SELECT id FROM payments WHERE appointment_id = ? AND payment_status = 'completed'");
        $stmt->execute([$appointment_id]);
        if ($stmt->fetch()) {
            throw new Exception('This appointment has already been paid.');
        }

        $stmt = $pdo->prepare("INSERT INTO payments (appointment_id, amount, discount, payment_method, payment_status) VALUES (?, ?, ?, ?, 'completed')");
        $stmt->execute([$appointment_id, $finalAmount, $discount, $payment_method]);
        $payment_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("UPDATE appointments SET status = 'completed' WHERE id = ?");
        $stmt->execute([$appointment_id]);

        salonCreateCommission($pdo, $appointment_id);

        $detailsStmt = $pdo->prepare("
            SELECT a.appointment_date, a.appointment_time, c.name AS client_name, c.phone AS client_phone, c.user_id AS client_user_id,
                   s.name AS service_name, s.price, u.name AS stylist_name
            FROM appointments a
            JOIN clients c ON c.id = a.client_id
            JOIN services s ON s.id = a.service_id
            JOIN users u ON u.id = a.stylist_id
            WHERE a.id = ?
        ");
        $detailsStmt->execute([$appointment_id]);
        $invoiceData = $detailsStmt->fetch();

        if (!empty($invoiceData['client_user_id'])) {
            salonNotifyUser(
                $pdo,
                (int) $invoiceData['client_user_id'],
                'Payment Confirmed',
                'Your payment was received and your invoice is ready for printing.',
                'success',
                'Elegance Salon Payment Confirmation'
            );
        }

        $pdo->commit();
        $success = "Payment successful. Invoice #INV-$payment_id generated.";

        $invoiceHTML = "
            <div id='invoice' class='detail-card' style='margin-top:20px;'>
                <div class='flex flex-between mb-2' style='border-bottom:1px solid rgba(19,17,16,0.1); padding-bottom:16px;'>
                    <div>
                        <span class='eyebrow'>Printable Invoice</span>
                        <h2 style='margin:0;'>Elegance Salon</h2>
                    </div>
                    <div style='text-align:right;'>
                        <strong>Invoice #INV-$payment_id</strong><br>
                        <small>" . date('F d, Y h:i A') . "</small>
                    </div>
                </div>
                <div class='dashboard-grid' style='grid-template-columns: repeat(2, minmax(0, 1fr)); gap:18px;'>
                    <div>
                        <h3>Billed To</h3>
                        <p>{$invoiceData['client_name']}<br>{$invoiceData['client_phone']}</p>
                    </div>
                    <div>
                        <h3>Appointment</h3>
                        <p>{$invoiceData['service_name']} with {$invoiceData['stylist_name']}<br>" . date('M d, Y h:i A', strtotime($invoiceData['appointment_date'] . ' ' . $invoiceData['appointment_time'])) . "</p>
                    </div>
                </div>
                <table class='table'>
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{$invoiceData['service_name']} appointment service</td>
                            <td>$" . number_format($originalAmount, 2) . "</td>
                        </tr>
                        <tr>
                            <td>Discount</td>
                            <td>-$" . number_format($discount, 2) . "</td>
                        </tr>
                    </tbody>
                </table>
                <div class='flex flex-between'>
                    <strong>Paid via " . htmlspecialchars($payment_method) . "</strong>
                    <strong style='color:var(--color-primary-dark);'>Total: $" . number_format($finalAmount, 2) . "</strong>
                </div>
                <div class='filter-row mt-2 no-print'>
                    <button id='print-btn' class='btn btn-primary' type='button'>Print Invoice</button>
                    <a href='/salon-management/appointments.php' class='btn btn-outline-gold'>Return to Appointments</a>
                </div>
            </div>
            <style>
                @media print {
                    body * { visibility: hidden; }
                    #invoice, #invoice * { visibility: visible; }
                    #invoice { position: absolute; left: 0; top: 0; width: 100%; box-shadow: none; }
                    .header, .footer, .no-print { display: none !important; }
                }
            </style>
        ";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

if (isset($_GET['checkout'])) {
    $appointment_id = (int) $_GET['checkout'];
    $stmt = $pdo->prepare("
        SELECT a.id, a.appointment_date, a.appointment_time,
               c.name AS client_name, c.phone AS client_phone,
               s.name AS service_name, s.price AS amount, u.name AS stylist_name
        FROM appointments a
        JOIN clients c ON c.id = a.client_id
        JOIN services s ON s.id = a.service_id
        JOIN users u ON u.id = a.stylist_id
        WHERE a.id = ?
          AND a.status IN ('pending', 'confirmed')
    ");
    $stmt->execute([$appointment_id]);
    $appDetails = $stmt->fetch();

    if (!$appDetails) {
        $error = 'Invalid appointment or already processed.';
    }
}

if (!isset($_GET['checkout']) && $invoiceHTML === '') {
    $pendingApps = $pdo->query("
        SELECT a.id, a.appointment_date, a.appointment_time,
               c.name AS client_name, s.name AS service_name, s.price, u.name AS stylist_name
        FROM appointments a
        JOIN clients c ON c.id = a.client_id
        JOIN services s ON s.id = a.service_id
        JOIN users u ON u.id = a.stylist_id
        WHERE a.status IN ('pending', 'confirmed') AND a.appointment_date <= CURDATE()
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ")->fetchAll();
}

include 'includes/header.php';
?>

<div class="container py-2">
    <div class="flex flex-between mb-2">
        <div>
            <span class="eyebrow">Billing</span>
            <h2>Checkout and printable invoice</h2>
        </div>
        <a href="/salon-management/appointments.php" class="btn btn-outline-gold">Back to Appointments</a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php echo $invoiceHTML; ?>

    <?php if ($appDetails && $invoiceHTML === ''): ?>
        <div class="form-card" style="max-width:680px; margin-top:0;">
            <h3 class="mb-1">Complete Payment</h3>
            <p><strong>Client:</strong> <?php echo htmlspecialchars($appDetails['client_name']); ?></p>
            <p><strong>Service:</strong> <?php echo htmlspecialchars($appDetails['service_name']); ?></p>
            <p><strong>Stylist:</strong> <?php echo htmlspecialchars($appDetails['stylist_name']); ?></p>
            <p><strong>Date:</strong> <?php echo date('M d, Y h:i A', strtotime($appDetails['appointment_date'] . ' ' . $appDetails['appointment_time'])); ?></p>
            <form method="POST" class="validate-form">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="appointment_id" value="<?php echo $appDetails['id']; ?>">
                <input type="hidden" name="amount" value="<?php echo $appDetails['amount']; ?>">
                <div class="form-group">
                    <label>Total Amount</label>
                    <input type="text" id="base_total_display" class="form-control" value="$<?php echo number_format($appDetails['amount'], 2); ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Discount Amount ($)</label>
                    <input type="number" name="discount" id="discount_amount" class="form-control" min="0" step="0.01" value="0">
                </div>
                <div class="form-group">
                    <label>Final Total</label>
                    <input type="text" id="final_total_display" class="form-control" value="$<?php echo number_format($appDetails['amount'], 2); ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Payment Method</label>
                    <select name="payment_method" class="form-control" required>
                        <option value="Cash">Cash</option>
                        <option value="Card">Card</option>
                        <option value="Transfer">Transfer</option>
                    </select>
                </div>
                <button type="submit" name="pay" class="btn btn-primary">Complete Payment & Generate Invoice</button>
            </form>
        </div>
    <?php elseif ($invoiceHTML === ''): ?>
        <div class="detail-card">
            <h3 class="mb-1">Pending checkouts</h3>
            <?php if ($pendingApps): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Service</th>
                            <th>Stylist</th>
                            <th>Amount</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingApps as $app): ?>
                            <tr>
                                <td><?php echo date('M d, Y h:i A', strtotime($app['appointment_date'] . ' ' . $app['appointment_time'])); ?></td>
                                <td><?php echo htmlspecialchars($app['client_name']); ?></td>
                                <td><?php echo htmlspecialchars($app['service_name']); ?></td>
                                <td><?php echo htmlspecialchars($app['stylist_name']); ?></td>
                                <td>$<?php echo number_format($app['price'], 2); ?></td>
                                <td><a href="payments.php?checkout=<?php echo $app['id']; ?>" class="btn btn-primary">Checkout</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No pending appointments to checkout today.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($appDetails && $invoiceHTML === ''): ?>
<script>
(function () {
    const baseAmount = <?php echo json_encode((float) $appDetails['amount']); ?>;
    const discountInput = document.getElementById('discount_amount');
    const finalTotalDisplay = document.getElementById('final_total_display');

    if (!discountInput || !finalTotalDisplay) {
        return;
    }

    function updateFinalTotal() {
        let discount = parseFloat(discountInput.value);
        if (isNaN(discount) || discount < 0) {
            discount = 0;
        }
        if (discount > baseAmount) {
            discount = baseAmount;
            discountInput.value = discount.toFixed(2);
        }

        const finalAmount = Math.max(0, baseAmount - discount);
        finalTotalDisplay.value = '$' + finalAmount.toFixed(2);
    }

    discountInput.addEventListener('input', updateFinalTotal);
    updateFinalTotal();
})();
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
