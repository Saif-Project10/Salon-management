<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireLogin();

$error = '';
$success = '';
$user_role = $_SESSION['user_role'] ?? 'client';
$user_id = $_SESSION['user_id'];

// Get client_id if role is client
$client_id = null;
if ($user_role === 'client') {
    $stmt = $pdo->prepare("SELECT id FROM clients WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $client = $stmt->fetch();
    if ($client) {
        $client_id = $client['id'];
    } else {
        $error = "Client profile not found. Please contact support.";
    }
}

// Handle Cancel Appointment
if (isset($_GET['cancel'])) {
    $app_id = (int)$_GET['cancel'];
    // Check permission
    $stmt = $pdo->prepare("SELECT client_id FROM appointments WHERE id = ?");
    $stmt->execute([$app_id]);
    $app = $stmt->fetch();
    
    if ($app) {
        if ($user_role !== 'client' || $app['client_id'] == $client_id) {
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$app_id]);
            $success = "Appointment cancelled successfully.";
        } else {
            $error = "Permission denied.";
        }
    }
}

// Handle Book Appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book'])) {
    $sel_client_id = isset($_POST['client_id']) ? (int)$_POST['client_id'] : $client_id;
    $service_id = (int)$_POST['service_id'];
    $stylist_id = (int)$_POST['stylist_id'];
    $date = trim($_POST['date']);
    $time = trim($_POST['time']);

    if (empty($date) || empty($time) || empty($service_id) || empty($stylist_id)) {
        $error = "Please fill all required fields.";
    } elseif (!$sel_client_id) {
        $error = "Invalid client selection.";
    } else {
        // Simple conflict check
        $stmt = $pdo->prepare("SELECT id FROM appointments WHERE stylist_id=? AND appointment_date=? AND appointment_time=? AND status != 'cancelled'");
        $stmt->execute([$stylist_id, $date, $time]);
        if ($stmt->fetch()) {
            $error = "The selected stylist is already booked at that time. Please choose another slot.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO appointments (client_id, service_id, stylist_id, appointment_date, appointment_time, status) VALUES (?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$sel_client_id, $service_id, $stylist_id, $date, $time]);
            $success = "Appointment booked successfully! We will confirm it shortly.";
            // Optionally, simulate email/SMS here...
        }
    }
}

// Fetch form data
$services = $pdo->query("SELECT id, name, price, duration FROM services ORDER BY name ASC")->fetchAll();
$stylists = $pdo->query("SELECT u.id, u.name, s.services FROM users u JOIN staff s ON u.id = s.user_id WHERE u.role IN ('stylist', 'receptionist')")->fetchAll();

$all_clients = [];
if ($user_role === 'admin' || $user_role === 'receptionist' || $user_role === 'stylist') {
    $all_clients = $pdo->query("SELECT id, name, phone FROM clients ORDER BY name ASC")->fetchAll();
}

// Fetch their appointments
$wherePrefix = "";
$params = [];
if ($user_role === 'client') {
    $wherePrefix = "WHERE a.client_id = ?";
    $params[] = $client_id;
} elseif ($user_role === 'stylist') {
    $wherePrefix = "WHERE a.stylist_id = ?";
    $params[] = $user_id;
}

$query = "
    SELECT a.id, a.appointment_date, a.appointment_time, a.status,
           c.name as client_name, s.name as service_name, s.duration, u.name as stylist_name
    FROM appointments a
    JOIN clients c ON a.client_id = c.id
    JOIN services s ON a.service_id = s.id
    JOIN users u ON a.stylist_id = u.id
    $wherePrefix
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$my_appointments = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container py-2">
    <div class="flex flex-between mb-2">
        <h2>Appointments</h2>
        <?php if ($user_role !== 'client'): ?>
        <a href="/salon-management/calendar.php" class="btn btn-outline-gold">View Calendar</a>
        <?php endif; ?>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="dashboard-grid">
        <!-- Booking Form Panel -->
        <?php if ($user_role !== 'stylist'): // Stylists usually don't book for others directly here ?>
        <div class="form-card" style="margin-top:0;">
            <h3 class="mb-1">Book New Appointment</h3>
            <form action="appointments.php" method="POST">
                
                <?php if ($user_role === 'admin' || $user_role === 'receptionist'): ?>
                <div class="form-group">
                    <label>Select Client *</label>
                    <select name="client_id" class="form-control" required>
                        <option value="">-- Choose Client --</option>
                        <?php foreach($all_clients as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name'] . ' - ' . $c['phone']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Select Service *</label>
                    <select name="service_id" id="service_id" class="form-control" required onchange="filterStylists()">
                        <option value="">-- Choose Service --</option>
                        <?php foreach($services as $s): ?>
                            <?php $selected = (isset($_GET['service_id']) && $_GET['service_id'] == $s['id']) ? 'selected' : ''; ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($s['name']); ?> ($<?php echo $s['price']; ?> - <?php echo $s['duration']; ?> mins)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Select Stylist *</label>
                    <select name="stylist_id" id="stylist_id" class="form-control" required>
                        <option value="">-- Choose Stylist --</option>
                        <?php foreach($stylists as $st): ?>
                            <option value="<?php echo $st['id']; ?>" class="stylist-option" data-services="<?php echo $st['services'] ?? ''; ?>">
                                <?php echo htmlspecialchars($st['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #888;">Only stylists who perform the selected service are shown.</small>
                </div>

                <div class="form-group">
                    <label>Date *</label>
                    <input type="date" name="date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="form-group">
                    <label>Time *</label>
                    <input type="time" name="time" class="form-control" required min="09:00" max="19:00" step="1800">
                    <small style="color: #888;">Salon Hours: 9:00 AM - 7:00 PM</small>
                </div>

                <button type="submit" name="book" class="btn btn-primary" style="width: 100%;">Confirm Booking</button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Appointments List -->
        <div class="table-responsive" style="<?php echo $user_role === 'stylist' ? 'grid-column: 1 / -1;' : 'grid-column: span 2;'; ?>">
            <h3 class="mb-1">Appointment History</h3>
            <?php if (count($my_appointments) > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <?php if($user_role !== 'client'): ?><th>Client</th><?php endif; ?>
                        <th>Service Details</th>
                        <th>Stylist</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($my_appointments as $app): ?>
                    <tr>
                        <td>
                            <strong><?php echo date('M d, Y', strtotime($app['appointment_date'])); ?></strong><br>
                            <?php echo date('h:i A', strtotime($app['appointment_time'])); ?>
                        </td>
                        <?php if($user_role !== 'client'): ?>
                        <td><?php echo htmlspecialchars($app['client_name']); ?></td>
                        <?php endif; ?>
                        <td>
                            <?php echo htmlspecialchars($app['service_name']); ?><br>
                            <small style="color: #666;"><?php echo $app['duration']; ?> mins</small>
                        </td>
                        <td><?php echo htmlspecialchars($app['stylist_name']); ?></td>
                        <td>
                            <?php 
                                $badgeClass = 'badge-pending';
                                if ($app['status'] == 'confirmed') $badgeClass = 'badge-confirmed';
                                if ($app['status'] == 'completed') $badgeClass = 'badge-completed';
                                if ($app['status'] == 'cancelled') $badgeClass = 'badge-cancelled';
                            ?>
                            <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($app['status']); ?></span>
                        </td>
                        <td>
                            <?php if ($app['status'] === 'pending' || $app['status'] === 'confirmed'): ?>
                                <a href="appointments.php?cancel=<?php echo $app['id']; ?>" class="btn btn-danger" style="padding: 5px 10px; font-size: 0.8rem;" onclick="return confirm('Are you sure you want to cancel this appointment?');">Cancel</a>
                            <?php endif; ?>
                            
                            <?php if (($user_role === 'admin' || $user_role === 'receptionist') && $app['status'] !== 'completed' && $app['status'] !== 'cancelled'): ?>
                                <a href="payments.php?checkout=<?php echo $app['id']; ?>" class="btn btn-outline-gold" style="padding: 5px 10px; font-size: 0.8rem; margin-top:5px; display:inline-block;">Checkout</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p>No appointments found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Filter stylists based on selected service
function filterStylists() {
    const serviceId = document.getElementById('service_id').value;
    const stylistOptions = document.querySelectorAll('.stylist-option');
    const stylistSelect = document.getElementById('stylist_id');
    
    // Reset selection
    stylistSelect.value = "";
    
    stylistOptions.forEach(opt => {
        const services = opt.getAttribute('data-services');
        if (!serviceId) {
            opt.style.display = '';
        } else {
            if (services && services.split(',').includes(serviceId)) {
                opt.style.display = '';
            } else {
                opt.style.display = 'none';
            }
        }
    });
}
// Run once on load in case a service is pre-selected (via GET)
document.addEventListener('DOMContentLoaded', filterStylists);
</script>

<?php include 'includes/footer.php'; ?>
