<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireLogin();

$error = '';
$success = '';
$info = '';
$user_role = currentUserRole();
$user_id = $_SESSION['user_id'];
$selected_service_id = isset($_GET['service_id']) ? (int) $_GET['service_id'] : 0;
$selected_stylist_id = isset($_GET['stylist_id']) ? (int) $_GET['stylist_id'] : 0;
$selected_date = '';
$selected_time = '';
$selected_notes = '';
$reschedule_id = 0;
$base_slots = salonGetTimeSlots();
$availability_map = salonFetchAvailabilityMap($pdo);
$blocked_slots = salonFetchBlockedSlots($pdo);

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

if (isset($_GET['reschedule']) && $user_role !== 'stylist') {
    $reschedule_id = (int) $_GET['reschedule'];
    $stmt = $pdo->prepare("
        SELECT a.id, a.client_id, a.service_id, a.stylist_id, a.appointment_date, a.appointment_time, a.notes
        FROM appointments a
        WHERE a.id = ? AND a.status IN ('pending', 'confirmed')
    ");
    $stmt->execute([$reschedule_id]);
    $rescheduleData = $stmt->fetch();

    $can_reschedule = false;
    if ($rescheduleData) {
        if (hasRole(['admin', 'receptionist'])) {
            $can_reschedule = true;
        } elseif ($user_role === 'client' && (int) $rescheduleData['client_id'] === $client_id) {
            $can_reschedule = true;
        }
    }

    if (!$can_reschedule) {
        $error = "You do not have permission to reschedule this appointment.";
        $reschedule_id = 0;
    } else {
        $selected_service_id = (int) $rescheduleData['service_id'];
        $selected_stylist_id = (int) $rescheduleData['stylist_id'];
        $selected_date = $rescheduleData['appointment_date'];
        $selected_time = substr($rescheduleData['appointment_time'], 0, 5);
        $selected_notes = $rescheduleData['notes'] ?? '';
        $info = "You're editing an existing appointment. Pick a new date and time, then save the reschedule.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    verifyCsrfToken();
    $appointment_id = (int) $_POST['appointment_id'];
    $new_status = $_POST['status'] ?? 'pending';
    $allowed_statuses = ['pending', 'confirmed', 'completed', 'cancelled'];

    if (!in_array($new_status, $allowed_statuses, true)) {
        $error = "Invalid appointment status.";
    } else {
        $stmt = $pdo->prepare("
            SELECT a.id, a.client_id, a.stylist_id, a.appointment_date, a.appointment_time, c.user_id AS client_user_id
            FROM appointments a
            JOIN clients c ON c.id = a.client_id
            WHERE a.id = ?
        ");
        $stmt->execute([$appointment_id]);
        $appointment = $stmt->fetch();

        $can_manage = false;
        if ($appointment) {
            if (hasRole(['admin', 'receptionist'])) {
                $can_manage = true;
            } elseif ($user_role === 'stylist' && (int) $appointment['stylist_id'] === $user_id) {
                $can_manage = true;
            } elseif ($user_role === 'client' && (int) $appointment['client_id'] === $client_id && $new_status === 'cancelled') {
                $can_manage = true;
            }
        }

        if (!$appointment || !$can_manage) {
            $error = "You do not have permission to update this appointment.";
        // ADDED FOR LATE CANCELLATION POLICY
        } elseif ($user_role === 'client' && $new_status === 'cancelled') {
            $appointment_start = new DateTime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']);
            $now = new DateTime();
            $diff = $now->diff($appointment_start);
            $hours_until = $diff->h + ($diff->days * 24);
            if ($diff->invert === 1 || $hours_until <= 24) {
                $error = "Cancellations are only allowed up to 24 hours before the appointment.";
            }
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    UPDATE appointments
                    SET status = ?, completed_at = CASE WHEN ? = 'completed' THEN COALESCE(completed_at, NOW()) ELSE completed_at END
                    WHERE id = ?
                ");
                $stmt->execute([$new_status, $new_status, $appointment_id]);

                if ($new_status === 'completed') {
                    salonCreateCommission($pdo, $appointment_id);
                    salonDeductInventoryForService($pdo, $appointment_id);
                    salonCheckAndGenerateAutoPO($pdo, (int) $_SESSION['user_id']);
                }

                if (!empty($appointment['client_user_id'])) {
                    salonNotifyUser(
                        $pdo,
                        (int) $appointment['client_user_id'],
                        'Appointment Update',
                        'Your appointment status is now ' . ucfirst($new_status) . '.',
                        $new_status === 'cancelled' ? 'warning' : 'info',
                        'Elegance Salon Appointment Update',
                        $appointment_id
                    );
                }

                $pdo->commit();
                $success = "Appointment status updated to " . ucfirst($new_status) . ".";

                try {
                    salonSyncToGoogleCalendar($pdo, $appointment_id, $new_status === 'cancelled' ? 'cancel' : 'upsert');
                } catch (Throwable $exception) {
                    error_log('Google sync skipped after status update: ' . $exception->getMessage());
                }
            } catch (Exception $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Appointment status could not be updated.";
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book'])) {
    verifyCsrfToken();
    $reschedule_id = isset($_POST['reschedule_id']) ? (int) $_POST['reschedule_id'] : 0;
    $sel_client_id = isset($_POST['client_id']) ? (int) $_POST['client_id'] : $client_id;
    $service_id = (int) $_POST['service_id'];
    $stylist_id = (int) $_POST['stylist_id'];
    $date = trim($_POST['date']);
    $time = trim($_POST['time']);
    $notes = trim($_POST['notes'] ?? '');
    $selected_service_id = $service_id;
    $selected_stylist_id = $stylist_id;
    $selected_date = $date;
    $selected_time = $time;
    $selected_notes = $notes;

    if (empty($date) || empty($time) || empty($service_id) || empty($stylist_id)) {
        $error = "Please fill all required fields.";
    } elseif (!$sel_client_id) {
        $error = "Invalid client selection.";
    } elseif (!preg_match('/^\d{2}:\d{2}$/', $time)) {
        $error = "Please choose a valid appointment slot.";
    } elseif (!salonStylistWorksAt($availability_map, $stylist_id, $date, $time, $pdo)) {
        $error = "This stylist is not available for the selected date and time.";
    } else {
        try {
            $pdo->beginTransaction();
            $appointmentSyncId = 0;

            if ($reschedule_id > 0) {
                $ownerStmt = $pdo->prepare("
                    SELECT a.id, a.client_id, a.appointment_date, a.appointment_time, c.user_id AS client_user_id
                    FROM appointments a
                    JOIN clients c ON c.id = a.client_id
                    WHERE a.id = ? AND a.status IN ('pending', 'confirmed')
                    FOR UPDATE
                ");
                $ownerStmt->execute([$reschedule_id]);
                $existingAppointment = $ownerStmt->fetch();

                $can_reschedule = false;
                if ($existingAppointment) {
                    if (hasRole(['admin', 'receptionist'])) {
                        $can_reschedule = true;
                    } elseif ($user_role === 'client' && (int) $existingAppointment['client_id'] === $client_id) {
                        $can_reschedule = true;
                    }
                }

                if (!$can_reschedule) {
                    throw new Exception("You do not have permission to reschedule this appointment.");
                }

                // ADDED FOR LATE CANCELLATION POLICY
                if ($user_role === 'client') {
                    $appointment_start = new DateTime($existingAppointment['appointment_date'] . ' ' . $existingAppointment['appointment_time']);
                    $now = new DateTime();
                    $diff = $now->diff($appointment_start);
                    $hours_until = $diff->h + ($diff->days * 24);
                    if ($diff->invert === 1 || $hours_until <= 24) {
                        throw new Exception("Rescheduling is only allowed up to 24 hours before the appointment.");
                    }
                }
            }

            $stmt = $pdo->prepare("
                SELECT id
                FROM appointments
                WHERE stylist_id = ?
                  AND appointment_date = ?
                  AND appointment_time = ?
                  AND status IN ('pending', 'confirmed', 'completed')
                  AND id != ?
                FOR UPDATE
            ");
            $stmt->execute([$stylist_id, $date, $time . ':00', $reschedule_id]);
            if ($stmt->fetch()) {
                throw new Exception("The selected stylist is already booked at that time. Please choose another slot.");
            }

            if ($reschedule_id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE appointments
                    SET client_id = ?, service_id = ?, stylist_id = ?, appointment_date = ?, appointment_time = ?, notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([$sel_client_id, $service_id, $stylist_id, $date, $time . ':00', $notes, $reschedule_id]);
                $appointmentSyncId = $reschedule_id;
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO appointments (client_id, service_id, stylist_id, appointment_date, appointment_time, status, notes)
                    VALUES (?, ?, ?, ?, ?, 'pending', ?)
                ");
                $stmt->execute([$sel_client_id, $service_id, $stylist_id, $date, $time . ':00', $notes]);
                $appointmentSyncId = (int) $pdo->lastInsertId();
            }

            $clientStmt = $pdo->prepare("SELECT user_id, name FROM clients WHERE id = ?");
            $clientStmt->execute([$sel_client_id]);
            $selectedClient = $clientStmt->fetch();

            if (!empty($selectedClient['user_id'])) {
                salonNotifyUser(
                    $pdo,
                    (int) $selectedClient['user_id'],
                    $reschedule_id > 0 ? 'Appointment Rescheduled' : 'Appointment Confirmation',
                    $reschedule_id > 0
                        ? "Your appointment has been moved to {$date} at {$time}."
                        : "Your booking request for {$date} at {$time} has been received.",
                    'success',
                    $reschedule_id > 0 ? 'Your Elegance Salon Appointment Was Rescheduled' : 'Your Elegance Salon Booking Confirmation',
                    $appointmentSyncId
                );
                salonNotifyUser(
                    $pdo,
                    (int) $selectedClient['user_id'],
                    'Appointment Reminder',
                    "Reminder: please arrive 10 minutes early for your {$time} appointment on {$date}.",
                    'info',
                    'Elegance Salon Appointment Reminder',
                    $appointmentSyncId
                );
            }

            salonNotifyUser(
                $pdo,
                $stylist_id,
                $reschedule_id > 0 ? 'Appointment Rescheduled' : 'New Booking Assigned',
                $reschedule_id > 0
                    ? "An appointment has been moved to {$date} at {$time}."
                    : "A new appointment has been scheduled for {$date} at {$time}.",
                'info',
                'Elegance Salon Staff Notification',
                $appointmentSyncId
            );

            $pdo->commit();

            try {
                salonSyncToGoogleCalendar($pdo, $appointmentSyncId, 'upsert');
            } catch (Throwable $exception) {
                error_log('Google sync skipped after booking change: ' . $exception->getMessage());
            }

            $success = $reschedule_id > 0
                ? "Appointment rescheduled successfully. Updated notifications have been generated."
                : "Appointment booked successfully. Confirmation and reminder notifications have been generated.";
            $blocked_slots = salonFetchBlockedSlots($pdo);
            $reschedule_id = 0;
            $selected_date = '';
            $selected_time = '';
            $selected_notes = '';
        } catch (Exception $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $exception->getMessage();
        }
    }
}

$services = $pdo->query("
    SELECT id, name, price, duration, category
    FROM services
    ORDER BY name ASC
")->fetchAll();

$stylists = $pdo->query("
    SELECT u.id, u.name, u.avatar, st.services, st.specialization, st.experience_years
    FROM users u
    JOIN staff st ON u.id = st.user_id
    WHERE u.role = 'stylist'
    ORDER BY u.name ASC
")->fetchAll();

$all_clients = [];
if (hasRole(['admin', 'receptionist'])) {
    $all_clients = $pdo->query("SELECT id, name, phone FROM clients ORDER BY name ASC")->fetchAll();
}

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
    SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.notes,
           c.name AS client_name, s.name AS service_name, s.duration, s.price, u.name AS stylist_name
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

$client_history = [];
if ($user_role === 'client' && $client_id) {
    $stmt = $pdo->prepare("
        SELECT a.appointment_date, a.status, s.name AS service_name, u.name AS stylist_name, s.price
        FROM appointments a
        JOIN services s ON s.id = a.service_id
        JOIN users u ON u.id = a.stylist_id
        WHERE a.client_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 5
    ");
    $stmt->execute([$client_id]);
    $client_history = $stmt->fetchAll();
}

$recent_notifications = salonFetchNotifications($pdo, $user_id, 6);

include 'includes/header.php';
?>

<div class="container py-2">
    <div class="flex flex-between mb-2">
        <h2>Appointments</h2>
        <div class="filter-row">
            <a href="/salon-management/calendar_export.php" class="btn btn-outline-gold">Export My Calendar (.ics)</a>
            <?php if ($user_role !== 'client'): ?>
            <a href="/salon-management/calendar.php" class="btn btn-outline-gold">View Calendar</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($info): ?>
        <div class="alert alert-info"><?php echo htmlspecialchars($info); ?></div>
    <?php endif; ?>

    <div class="booking-layout">
        <?php if ($user_role !== 'stylist'): ?>
        <div class="form-card" style="margin-top:0;">
            <span class="eyebrow">Online Booking</span>
            <h3 class="mb-1"><?php echo $reschedule_id > 0 ? 'Reschedule appointment' : 'Reserve an available appointment slot'; ?></h3>
            <form action="appointments.php" method="POST" data-booking-form
                data-blocked-slots="<?php echo htmlspecialchars(json_encode($blocked_slots), ENT_QUOTES, 'UTF-8'); ?>"
                data-availability="<?php echo htmlspecialchars(json_encode($availability_map), ENT_QUOTES, 'UTF-8'); ?>"
                data-base-slots="<?php echo htmlspecialchars(json_encode($base_slots), ENT_QUOTES, 'UTF-8'); ?>"
                data-initial-time="<?php echo htmlspecialchars($selected_time, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="reschedule_id" value="<?php echo $reschedule_id; ?>">

                <?php if (hasRole(['admin', 'receptionist'])): ?>
                <div class="form-group">
                    <label>Select Client *</label>
                    <select name="client_id" class="form-control" required>
                        <option value="">-- Choose Client --</option>
                        <?php foreach ($all_clients as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo ($reschedule_id > 0 && isset($rescheduleData) && (int) $rescheduleData['client_id'] === (int) $c['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['name'] . ' - ' . $c['phone']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Select Service *</label>
                    <select name="service_id" id="service_id" class="form-control" required>
                        <option value="">-- Choose Service --</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?php echo $service['id']; ?>" <?php echo $selected_service_id === (int) $service['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($service['name']); ?> | $<?php echo number_format($service['price'], 2); ?> | <?php echo (int) $service['duration']; ?> mins
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Select Stylist *</label>
                    <select name="stylist_id" id="stylist_id" class="form-control" required>
                        <option value="">-- Choose Stylist --</option>
                        <?php foreach ($stylists as $stylist): ?>
                            <option value="<?php echo $stylist['id']; ?>"
                                data-services="<?php echo htmlspecialchars($stylist['services'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo $selected_stylist_id === (int) $stylist['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($stylist['name']); ?> | <?php echo htmlspecialchars($stylist['specialization'] ?: 'Stylist'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #888;">Only stylists assigned to the selected service remain visible.</small>
                </div>

                <div class="form-group">
                    <label>Date *</label>
                    <input type="date" name="date" id="appointment_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($selected_date); ?>">
                </div>

                <div class="form-group">
                    <div class="flex flex-between">
                        <label>Select Time Slot *</label>
                        <div class="slot-legend">
                            <span class="legend-available">Available</span>
                            <span class="legend-booked">Booked</span>
                            <span class="legend-unavailable">Unavailable</span>
                        </div>
                    </div>
                    <input type="hidden" name="time" id="appointment_time" required>
                    <div class="slot-grid" data-slot-grid></div>
                    <small data-slot-message style="color: #888;">Select a stylist and date to view available times.</small>
                </div>

                <div class="form-group">
                    <label>Booking Notes</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Preferred look, treatment notes, or special requests"><?php echo htmlspecialchars($selected_notes); ?></textarea>
                </div>

                <button type="submit" name="book" class="btn btn-primary" style="width: 100%;"><?php echo $reschedule_id > 0 ? 'Save Reschedule' : 'Confirm Booking'; ?></button>
                <?php if ($reschedule_id > 0): ?>
                    <a href="appointments.php" class="btn btn-outline-gold" style="width: 100%;">Cancel Reschedule</a>
                <?php endif; ?>
            </form>
        </div>
        <?php endif; ?>

        <div class="booking-summary profile-stack">
            <div class="detail-card">
                <span class="eyebrow">Booking Summary</span>
                <h3>Your current selection</h3>
                <div class="history-list">
                    <div class="history-item"><strong>Service</strong><div data-summary-service>Choose a service</div></div>
                    <div class="history-item"><strong>Stylist</strong><div data-summary-stylist>Choose a stylist</div></div>
                    <div class="history-item"><strong>Date</strong><div data-summary-date>Select a date</div></div>
                    <div class="history-item"><strong>Time</strong><div data-summary-time>Choose an available slot</div></div>
                </div>
            </div>

            <?php if ($client_history): ?>
            <div class="detail-card">
                <span class="eyebrow">Customer History</span>
                <h3>Recent visits</h3>
                <div class="history-list">
                    <?php foreach ($client_history as $visit): ?>
                        <div class="history-item">
                            <strong><?php echo htmlspecialchars($visit['service_name']); ?></strong>
                            <div><?php echo htmlspecialchars($visit['stylist_name']); ?> | $<?php echo number_format($visit['price'], 2); ?></div>
                            <small><?php echo date('M d, Y', strtotime($visit['appointment_date'])); ?> | <?php echo ucfirst($visit['status']); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="detail-card">
                <span class="eyebrow">Notifications</span>
                <h3>Simulated alerts</h3>
                <div class="notification-list">
                    <?php foreach ($recent_notifications as $notification): ?>
                        <div class="notification-item">
                            <strong><?php echo htmlspecialchars($notification['title']); ?></strong>
                            <p><?php echo htmlspecialchars($notification['message']); ?></p>
                            <small><?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?></small>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$recent_notifications): ?>
                        <p>No notifications yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive mt-2">
        <h3 class="mb-1">Appointment History</h3>
        <?php if (count($my_appointments) > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <?php if ($user_role !== 'client'): ?><th>Client</th><?php endif; ?>
                    <th>Service Details</th>
                    <th>Stylist</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($my_appointments as $app): ?>
                <?php
                $badgeClass = 'badge-pending';
                if ($app['status'] === 'confirmed') $badgeClass = 'badge-confirmed';
                if ($app['status'] === 'completed') $badgeClass = 'badge-completed';
                if ($app['status'] === 'cancelled') $badgeClass = 'badge-cancelled';
                // ADDED FOR LATE CANCELLATION POLICY
                $client_within_24h = false;
                if ($user_role === 'client' && in_array($app['status'], ['pending', 'confirmed'], true)) {
                    $appointment_start = new DateTime($app['appointment_date'] . ' ' . $app['appointment_time']);
                    $now = new DateTime();
                    $diff = $now->diff($appointment_start);
                    $hours_until = $diff->h + ($diff->days * 24);
                    $client_within_24h = ($diff->invert === 1 || $hours_until <= 24);
                }
                ?>
                <tr>
                    <td>
                        <strong><?php echo date('M d, Y', strtotime($app['appointment_date'])); ?></strong><br>
                        <?php echo date('h:i A', strtotime($app['appointment_time'])); ?>
                    </td>
                    <?php if ($user_role !== 'client'): ?>
                    <td><?php echo htmlspecialchars($app['client_name']); ?></td>
                    <?php endif; ?>
                    <td>
                        <strong><?php echo htmlspecialchars($app['service_name']); ?></strong><br>
                        <small><?php echo (int) $app['duration']; ?> mins | $<?php echo number_format($app['price'], 2); ?></small>
                        <?php if (!empty($app['notes'])): ?>
                            <br><small>Note: <?php echo htmlspecialchars($app['notes']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($app['stylist_name']); ?></td>
                    <td><span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($app['status']); ?></span></td>
                    <td>
                        <?php if (($user_role === 'client' && in_array($app['status'], ['pending', 'confirmed'], true)) || hasRole(['admin', 'receptionist']) || ($user_role === 'stylist' && $app['status'] !== 'cancelled')): ?>
                            <form method="POST" style="display:grid; gap:8px;">
                                <?php echo csrfInput(); ?>
                                <input type="hidden" name="appointment_id" value="<?php echo $app['id']; ?>">
                                <?php if (hasRole(['admin', 'receptionist']) || $user_role === 'stylist'): ?>
                                    <select name="status" class="form-control">
                                        <option value="pending" <?php echo $app['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="confirmed" <?php echo $app['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                        <option value="completed" <?php echo $app['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo $app['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                    <button type="submit" name="update_status" class="btn btn-outline-gold" style="padding: 10px 14px;">Update</button>
                                <?php else: ?>
                                    <input type="hidden" name="status" value="cancelled">
                                    <button
                                        type="submit"
                                        name="update_status"
                                        class="btn btn-danger"
                                        onclick="return confirm('Cancel this appointment?');"
                                        <?php echo $client_within_24h ? 'disabled' : ''; ?>
                                        <?php if ($client_within_24h): ?>title="Cannot modify within 24 hours. Please call salon."<?php endif; ?>
                                        style="<?php echo $client_within_24h ? 'opacity:0.6; cursor:not-allowed;' : ''; ?>"
                                    >Cancel</button>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>

                        <?php if (($user_role === 'client' || hasRole(['admin', 'receptionist'])) && in_array($app['status'], ['pending', 'confirmed'], true)): ?>
                            <?php if ($user_role === 'client' && $client_within_24h): ?>
                                <button class="btn btn-outline-gold" disabled title="Cannot modify within 24 hours. Please call salon." style="margin-top:8px; opacity:0.6; cursor:not-allowed;">Reschedule</button>
                                <small style="display:block; color:#888; margin-top:6px;">Cannot modify within 24 hours. Please call salon.</small>
                            <?php else: ?>
                                <a href="appointments.php?reschedule=<?php echo $app['id']; ?>" class="btn btn-outline-gold" style="margin-top:8px;">Reschedule</a>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if (hasRole(['admin', 'receptionist']) && !in_array($app['status'], ['completed', 'cancelled'], true)): ?>
                            <a href="payments.php?checkout=<?php echo $app['id']; ?>" class="btn btn-primary" style="margin-top:8px;">Checkout</a>
                        <?php endif; ?>

                        <a href="calendar_export.php?appointment_id=<?php echo $app['id']; ?>" class="btn btn-outline-gold" style="margin-top:8px;">Add to Calendar</a>
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

<?php include 'includes/footer.php'; ?>
