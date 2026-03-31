<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireRole(['admin', 'receptionist']);

$error = '';
$success = '';
$invoiceHTML = '';

// Handle Checkout Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay'])) {
    $appointment_id = (int)$_POST['appointment_id'];
    $amount = (float)$_POST['amount'];
    $payment_method = $_POST['payment_method'];

    try {
        $pdo->beginTransaction();

        // Check if already paid
        $stmt = $pdo->prepare("SELECT id FROM payments WHERE appointment_id = ? AND payment_status = 'completed'");
        $stmt->execute([$appointment_id]);
        if ($stmt->fetch()) {
            throw new Exception("This appointment has already been paid.");
        }

        // Insert Payment
        $stmt = $pdo->prepare("INSERT INTO payments (appointment_id, amount, payment_method, payment_status) VALUES (?, ?, ?, 'completed')");
        $stmt->execute([$appointment_id, $amount, $payment_method]);
        $payment_id = $pdo->lastInsertId();

        // Update Appointment Status
        $stmt = $pdo->prepare("UPDATE appointments SET status = 'completed' WHERE id = ?");
        $stmt->execute([$appointment_id]);

        $pdo->commit();
        $success = "Payment successful! Invoice #INV-$payment_id generated.";
        
        // Generate Invoice view
        $invoiceHTML = "
            <div id='invoice' style='padding:40px; border:1px solid #ddd; border-radius:8px; margin-top:20px; background:#fff;'>
                <div class='flex flex-between mb-2' style='border-bottom:2px solid var(--color-primary); padding-bottom:10px;'>
                    <h2>Elegance.</h2>
                    <div style='text-align:right;'>
                        <h3 style='margin:0;'>INVOICE #INV-$payment_id</h3>
                        <p style='color:#666;'>" . date('F d, Y h:i A') . "</p>
                    </div>
                </div>
                <div class='mb-2'>
                    <h4>Billed To:</h4>
                    <p>{$_POST['client_name']}<br>{$_POST['client_phone']}</p>
                </div>
                <table class='table' style='width:100%; border-collapse:collapse;'>
                    <tr style='background:#f4f4f4;'>
                        <th style='padding:10px;text-align:left;'>Description</th>
                        <th style='padding:10px;text-align:right;'>Amount</th>
                    </tr>
                    <tr>
                        <td style='padding:10px;border-bottom:1px solid #ddd;'>{$_POST['service_name']} Appointment on {$_POST['app_date']}</td>
                        <td style='padding:10px;border-bottom:1px solid #ddd; text-align:right;'>$" . number_format($amount, 2) . "</td>
                    </tr>
                    <tr>
                        <th style='padding:10px;text-align:right;'>Total Paid ($payment_method)</th>
                        <th style='padding:10px;text-align:right; color:var(--color-primary);'>$" . number_format($amount, 2) . "</th>
                    </tr>
                </table>
                <p class='text-center mt-2' style='color:#888;'>Thank you for choosing Elegance Salon!</p>
                <div class='text-center mt-1 no-print'>
                    <button class='btn btn-primary' onclick='window.print()'>Print Invoice</button>
                    <a href='/salon-management/appointments.php' class='btn btn-outline-gold'>Return to Appointments</a>
                </div>
            </div>
            <style>
                @media print {
                    body * { visibility: hidden; }
                    #invoice, #invoice * { visibility: visible; }
                    #invoice { position: absolute; left: 0; top: 0; width: 100%; border: none; }
                    .no-print { display: none !important; }
                    .header, .footer { display: none !important; }
                }
            </style>
        ";

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Fetch Appointment Details for GET
$appDetails = null;
if (isset($_GET['checkout'])) {
    $app_id = (int)$_GET['checkout'];
    $stmt = $pdo->prepare("
        SELECT a.id, a.appointment_date, a.appointment_time, 
               c.name as client_name, c.phone as client_phone,
               s.name as service_name, s.price as amount
        FROM appointments a
        JOIN clients c ON a.client_id = c.id
        JOIN services s ON a.service_id = s.id
        WHERE a.id = ? AND a.status != 'completed' AND a.status != 'cancelled'
    ");
    $stmt->execute([$app_id]);
    $appDetails = $stmt->fetch();
    
    if(!$appDetails) {
        $error = "Invalid appointment or already processed.";
    }
}

// Fetch pending payments list if not checking out
$pendingApps = [];
if (!isset($_GET['checkout']) && empty($invoiceHTML)) {
    $pendingApps = $pdo->query("
        SELECT a.id, a.appointment_date, a.appointment_time, 
               c.name as client_name, s.name as service_name, s.price
        FROM appointments a
        JOIN clients c ON a.client_id = c.id
        JOIN services s ON a.service_id = s.id
        WHERE a.status IN ('pending', 'confirmed') AND a.appointment_date <= CURDATE()
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ")->fetchAll();
}

include 'includes/header.php';
?>

<div class="container py-2">
    <?php if ($success): ?>
        <div class="alert alert-success mt-1 no-print"><?php echo htmlspecialchars($success); ?></div>
        <?php echo $invoiceHTML; ?>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error mt-1"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($appDetails && empty($invoiceHTML)): ?>
        <div class="flex flex-between mb-2">
            <h2>Process Payment</h2>
            <a href="payments.php" class="btn btn-outline-gold">&larr; Back to Pending List</a>
        </div>

        <div class="form-card mx-auto" style="max-width: 600px; margin: 0 auto;">
            <h3 class="mb-1">Checkout Summary</h3>
            <p><strong>Client:</strong> <?php echo htmlspecialchars($appDetails['client_name']); ?></p>
            <p><strong>Service:</strong> <?php echo htmlspecialchars($appDetails['service_name']); ?></p>
            <p><strong>Date:</strong> <?php echo date('M d, Y g:ia', strtotime($appDetails['appointment_date'] . ' ' . $appDetails['appointment_time'])); ?></p>
            
            <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">

            <form action="payments.php" method="POST">
                <input type="hidden" name="appointment_id" value="<?php echo $appDetails['id']; ?>">
                <input type="hidden" name="client_name" value="<?php echo htmlspecialchars($appDetails['client_name']); ?>">
                <input type="hidden" name="client_phone" value="<?php echo htmlspecialchars($appDetails['client_phone']); ?>">
                <input type="hidden" name="service_name" value="<?php echo htmlspecialchars($appDetails['service_name']); ?>">
                <input type="hidden" name="app_date" value="<?php echo $appDetails['appointment_date']; ?>">
                
                <div class="flex flex-between mb-1">
                    <h3 style="margin: 0;">Total Amount:</h3>
                    <h3 style="color: var(--color-primary); margin: 0;">$<?php echo number_format($appDetails['amount'], 2); ?></h3>
                    <input type="hidden" name="amount" value="<?php echo $appDetails['amount']; ?>">
                </div>

                <div class="form-group mb-2">
                    <label>Payment Method *</label>
                    <select name="payment_method" class="form-control" required>
                        <option value="Credit Card">Credit Card</option>
                        <option value="Cash">Cash</option>
                        <option value="Debit Card">Debit Card</option>
                        <option value="Digital Wallet">Digital Wallet (Apple/Google Pay)</option>
                    </select>
                </div>

                <button type="submit" name="pay" class="btn btn-primary" style="width: 100%;">Complete Payment & Generate Invoice</button>
            </form>
        </div>

    <?php elseif (empty($invoiceHTML)): ?>
        <div class="flex flex-between mb-2">
            <h2>Pending Checkout</h2>
            <a href="/salon-management/admin/dashboard.php" class="btn btn-outline-gold">&larr; Dashboard</a>
        </div>

        <div class="dashboard-grid">
            <div class="table-responsive" style="grid-column: 1 / -1;">
                <?php if (count($pendingApps) > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Client</th>
                            <th>Service</th>
                            <th>Amount Due</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingApps as $app): ?>
                        <tr>
                            <td>
                                <strong><?php echo date('M d, Y', strtotime($app['appointment_date'])); ?></strong><br>
                                <?php echo date('h:i A', strtotime($app['appointment_time'])); ?>
                            </td>
                            <td><?php echo htmlspecialchars($app['client_name']); ?></td>
                            <td><?php echo htmlspecialchars($app['service_name']); ?></td>
                            <td style="font-weight:bold; color:var(--color-primary);">$<?php echo number_format($app['price'], 2); ?></td>
                            <td>
                                <a href="payments.php?checkout=<?php echo $app['id']; ?>" class="btn btn-outline-gold" style="padding: 5px 10px; font-size: 0.8rem;">Checkout & Pay</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p>No pending appointments to checkout today.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
