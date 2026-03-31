<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireRole(['admin', 'receptionist']);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $user_id = (int) $_POST['user_id'];
    $commission_rate = (float) $_POST['commission_rate'];
    $specialization = trim($_POST['specialization'] ?? '');
    $experience_years = (int) ($_POST['experience_years'] ?? 0);
    $bio = trim($_POST['bio'] ?? '');
    $services = isset($_POST['services']) ? implode(',', $_POST['services']) : '';
    $days = $_POST['availability_day'] ?? [];
    $start_times = $_POST['start_time'] ?? [];
    $end_times = $_POST['end_time'] ?? [];

    $stmt = $pdo->prepare("SELECT id FROM staff WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $pdo->prepare("UPDATE staff SET commission_rate = ?, services = ?, specialization = ?, experience_years = ?, bio = ? WHERE user_id = ?");
        $stmt->execute([$commission_rate, $services, $specialization, $experience_years, $bio, $user_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO staff (user_id, commission_rate, services, specialization, experience_years, bio) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $commission_rate, $services, $specialization, $experience_years, $bio]);
    }

    $pdo->prepare("DELETE FROM staff_availability WHERE user_id = ?")->execute([$user_id]);
    $availabilityStmt = $pdo->prepare("
        INSERT INTO staff_availability (user_id, work_day, start_time, end_time, is_available)
        VALUES (?, ?, ?, ?, ?)
    ");

    for ($day = 1; $day <= 7; $day++) {
        $available = isset($days[$day]) ? 1 : 0;
        $start = $start_times[$day] ?? '09:00';
        $end = $end_times[$day] ?? '19:00';
        if ($start === '') {
            $start = '09:00';
        }
        if ($end === '') {
            $end = '19:00';
        }
        $availabilityStmt->execute([$user_id, $day, $start . ':00', $end . ':00', $available]);
    }

    $success = 'Staff profile and schedule updated successfully.';
}

$staffUsers = $pdo->query("
    SELECT u.id, u.name, u.role, s.commission_rate, s.services, s.specialization, s.experience_years, s.bio
    FROM users u
    LEFT JOIN staff s ON u.id = s.user_id
    WHERE u.role IN ('stylist', 'receptionist')
    ORDER BY u.role ASC, u.name ASC
")->fetchAll();

$allServices = $pdo->query("SELECT id, name FROM services ORDER BY name ASC")->fetchAll();
$availabilityRows = $pdo->query("SELECT user_id, work_day, start_time, end_time, is_available FROM staff_availability ORDER BY user_id, work_day")->fetchAll();
$availabilityMap = [];
foreach ($availabilityRows as $row) {
    $availabilityMap[$row['user_id']][$row['work_day']] = [
        'start' => substr($row['start_time'], 0, 5),
        'end' => substr($row['end_time'], 0, 5),
        'available' => (int) $row['is_available']
    ];
}
$dayLabels = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

include 'includes/header.php';
?>

<div class="container py-2">
    <div class="flex flex-between mb-2">
        <div>
            <span class="eyebrow">Staff Scheduling</span>
            <h2>Assign services, commissions, and weekly shifts</h2>
        </div>
        <a href="/salon-management/admin/dashboard.php" class="btn btn-outline-gold">Back to Dashboard</a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="dashboard-grid">
        <div class="form-card" style="grid-column: span 2; margin-top:0;">
            <h3 class="mb-1">Staff profile editor</h3>
            <form action="staff.php" method="POST" id="staff-form">
                <?php echo csrfInput(); ?>
                <div class="dashboard-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr)); gap:18px;">
                    <div class="form-group">
                        <label>Select Staff Member</label>
                        <select name="user_id" id="user_id" class="form-control" required>
                            <option value="">-- Select --</option>
                            <?php foreach ($staffUsers as $su): ?>
                                <option value="<?php echo $su['id']; ?>"
                                    data-commission="<?php echo (float) ($su['commission_rate'] ?? 0); ?>"
                                    data-services="<?php echo htmlspecialchars($su['services'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-specialization="<?php echo htmlspecialchars($su['specialization'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-experience="<?php echo (int) ($su['experience_years'] ?? 0); ?>"
                                    data-bio="<?php echo htmlspecialchars($su['bio'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
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
                        <label>Specialization</label>
                        <input type="text" name="specialization" id="specialization" class="form-control" placeholder="Color correction, bridal styling, facials">
                    </div>

                    <div class="form-group">
                        <label>Experience (years)</label>
                        <input type="number" name="experience_years" id="experience_years" class="form-control" min="0" value="0">
                    </div>
                </div>

                <div class="form-group">
                    <label>Bio</label>
                    <textarea name="bio" id="bio" class="form-control" rows="3" placeholder="Short professional summary for stylist profile page"></textarea>
                </div>

                <div class="form-group">
                    <label>Assigned Services</label>
                    <div class="tag-row">
                        <?php foreach ($allServices as $service): ?>
                            <label class="tag"><input type="checkbox" name="services[]" value="<?php echo $service['id']; ?>" class="service-cb"> <?php echo htmlspecialchars($service['name']); ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label>Weekly Availability</label>
                    <div class="schedule-grid">
                        <?php foreach ($dayLabels as $dayNumber => $dayLabel): ?>
                            <div class="schedule-shift">
                                <strong><?php echo $dayLabel; ?></strong>
                                <label><input type="checkbox" name="availability_day[<?php echo $dayNumber; ?>]" id="available_<?php echo $dayNumber; ?>" checked> Available</label>
                                <div class="filter-row">
                                    <input type="time" name="start_time[<?php echo $dayNumber; ?>]" id="start_<?php echo $dayNumber; ?>" class="form-control" value="09:00">
                                    <input type="time" name="end_time[<?php echo $dayNumber; ?>]" id="end_<?php echo $dayNumber; ?>" class="form-control" value="19:00">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Save Staff Details</button>
            </form>
        </div>

        <div class="detail-card">
            <span class="eyebrow">Current Team Setup</span>
            <h3>Staff overview</h3>
            <div class="history-list">
                <?php foreach ($staffUsers as $staff): ?>
                    <div class="history-item">
                        <strong><?php echo htmlspecialchars($staff['name']); ?></strong>
                        <div><?php echo ucfirst($staff['role']); ?> | <?php echo htmlspecialchars($staff['specialization'] ?: 'Specialization pending'); ?></div>
                        <small><?php echo (float) ($staff['commission_rate'] ?? 0); ?>% commission | <?php echo (int) ($staff['experience_years'] ?? 0); ?> years</small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
const availabilityMap = <?php echo json_encode($availabilityMap); ?>;
const userSelect = document.getElementById('user_id');

function loadStaffData() {
    const option = userSelect.selectedOptions[0];
    if (!option || !option.value) return;

    document.getElementById('commission_rate').value = option.dataset.commission || '0';
    document.getElementById('specialization').value = option.dataset.specialization || '';
    document.getElementById('experience_years').value = option.dataset.experience || '0';
    document.getElementById('bio').value = option.dataset.bio || '';

    document.querySelectorAll('.service-cb').forEach((checkbox) => {
        checkbox.checked = (option.dataset.services || '').split(',').includes(checkbox.value);
    });

    const days = availabilityMap[option.value] || {};
    for (let day = 1; day <= 7; day++) {
        const rule = days[day] || {start: '09:00', end: '19:00', available: 0};
        document.getElementById(`available_${day}`).checked = Number(rule.available) === 1;
        document.getElementById(`start_${day}`).value = rule.start || '09:00';
        document.getElementById(`end_${day}`).value = rule.end || '19:00';
    }
}

userSelect.addEventListener('change', loadStaffData);
</script>

<?php include 'includes/footer.php'; ?>
