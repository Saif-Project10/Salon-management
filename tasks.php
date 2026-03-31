<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireRole(['admin', 'receptionist', 'stylist']);

$error = '';
$success = '';
$userId = $_SESSION['user_id'];
$role = currentUserRole();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_task']) && hasRole(['admin', 'receptionist'])) {
    verifyCsrfToken();
    $taskId = (int) ($_POST['task_id'] ?? 0);
    $assignedTo = (int) ($_POST['assigned_to'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $dueDate = trim($_POST['due_date'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';

    if ($assignedTo <= 0 || $title === '') {
        $error = 'Task title and assigned staff member are required.';
    } else {
        if ($taskId > 0) {
            $stmt = $pdo->prepare("
                UPDATE staff_tasks
                SET assigned_to = ?, title = ?, description = ?, due_date = ?, priority = ?
                WHERE id = ?
            ");
            $stmt->execute([$assignedTo, $title, $description, $dueDate ?: null, $priority, $taskId]);
            $success = 'Task updated successfully.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO staff_tasks (assigned_to, assigned_by, title, description, due_date, priority)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$assignedTo, $userId, $title, $description, $dueDate ?: null, $priority]);
            salonNotifyUser($pdo, $assignedTo, 'New Task Assigned', "A new task has been assigned: {$title}.", 'info', 'Elegance Salon Staff Task Assignment');
            $success = 'Task assigned successfully.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task_status'])) {
    verifyCsrfToken();
    $taskId = (int) ($_POST['task_id'] ?? 0);
    $status = $_POST['status'] ?? 'pending';
    $allowed = ['pending', 'in_progress', 'completed'];

    if (in_array($status, $allowed, true)) {
        $stmt = $pdo->prepare("SELECT assigned_to FROM staff_tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();

        if ($task && (hasRole(['admin', 'receptionist']) || (int) $task['assigned_to'] === $userId)) {
            $stmt = $pdo->prepare("UPDATE staff_tasks SET status = ? WHERE id = ?");
            $stmt->execute([$status, $taskId]);
            $success = 'Task status updated.';
        } else {
            $error = 'You do not have permission to update that task.';
        }
    }
}

if (isset($_GET['delete']) && hasRole(['admin', 'receptionist'])) {
    $taskId = (int) $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM staff_tasks WHERE id = ?");
    $stmt->execute([$taskId]);
    $success = 'Task deleted successfully.';
}

$staffMembers = $pdo->query("
    SELECT id, name, role
    FROM users
    WHERE role IN ('stylist', 'receptionist')
    ORDER BY role ASC, name ASC
")->fetchAll();

$editTask = null;
if (isset($_GET['edit']) && hasRole(['admin', 'receptionist'])) {
    $stmt = $pdo->prepare("SELECT * FROM staff_tasks WHERE id = ?");
    $stmt->execute([(int) $_GET['edit']]);
    $editTask = $stmt->fetch();
}

$where = '';
$params = [];
if ($role === 'stylist') {
    $where = 'WHERE t.assigned_to = ?';
    $params[] = $userId;
}

$stmt = $pdo->prepare("
    SELECT t.*, u.name AS assigned_to_name, a.name AS assigned_by_name
    FROM staff_tasks t
    JOIN users u ON u.id = t.assigned_to
    LEFT JOIN users a ON a.id = t.assigned_by
    $where
    ORDER BY
        CASE t.status
            WHEN 'pending' THEN 1
            WHEN 'in_progress' THEN 2
            ELSE 3
        END,
        t.due_date IS NULL,
        t.due_date ASC,
        t.created_at DESC
");
$stmt->execute($params);
$tasks = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container py-2">
    <div class="flex flex-between mb-2">
        <div>
            <span class="eyebrow">Task Assignment</span>
            <h2><?php echo $role === 'stylist' ? 'My assigned tasks' : 'Assign and track staff tasks'; ?></h2>
        </div>
        <a href="<?php echo dashboardUrlForRole($role); ?>" class="btn btn-outline-gold">Back to Dashboard</a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="dashboard-grid">
        <?php if (hasRole(['admin', 'receptionist'])): ?>
        <div class="form-card" style="margin-top:0;">
            <h3 class="mb-1"><?php echo $editTask ? 'Edit Task' : 'Assign New Task'; ?></h3>
            <form method="POST">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="task_id" value="<?php echo (int) ($editTask['id'] ?? 0); ?>">
                <div class="form-group">
                    <label>Assigned To</label>
                    <select name="assigned_to" class="form-control" required>
                        <option value="">-- Select staff --</option>
                        <?php foreach ($staffMembers as $member): ?>
                            <option value="<?php echo $member['id']; ?>" <?php echo ((int) ($editTask['assigned_to'] ?? 0) === (int) $member['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($member['name'] . ' (' . ucfirst($member['role']) . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Task Title</label>
                    <input type="text" name="title" class="form-control" required value="<?php echo htmlspecialchars($editTask['title'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($editTask['description'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label>Due Date</label>
                    <input type="date" name="due_date" class="form-control" value="<?php echo htmlspecialchars($editTask['due_date'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Priority</label>
                    <select name="priority" class="form-control">
                        <?php foreach (['low', 'medium', 'high'] as $priority): ?>
                            <option value="<?php echo $priority; ?>" <?php echo (($editTask['priority'] ?? 'medium') === $priority) ? 'selected' : ''; ?>>
                                <?php echo ucfirst($priority); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="save_task" class="btn btn-primary" style="width:100%;">Save Task</button>
            </form>
        </div>
        <?php endif; ?>

        <div class="table-responsive" style="<?php echo hasRole(['admin', 'receptionist']) ? 'grid-column: span 2;' : 'grid-column: 1 / -1;'; ?>">
            <h3 class="mb-1">Task Board</h3>
            <?php if ($tasks): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <?php if ($role !== 'stylist'): ?><th>Assigned To</th><?php endif; ?>
                            <th>Due Date</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $task): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($task['title']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($task['description'] ?: 'No description'); ?></small>
                                </td>
                                <?php if ($role !== 'stylist'): ?>
                                    <td>
                                        <?php echo htmlspecialchars($task['assigned_to_name']); ?><br>
                                        <small>By <?php echo htmlspecialchars($task['assigned_by_name'] ?: 'System'); ?></small>
                                    </td>
                                <?php endif; ?>
                                <td><?php echo $task['due_date'] ? date('M d, Y', strtotime($task['due_date'])) : 'No deadline'; ?></td>
                                <td><span class="badge badge-<?php echo $task['priority'] === 'high' ? 'cancelled' : ($task['priority'] === 'medium' ? 'pending' : 'confirmed'); ?>"><?php echo ucfirst($task['priority']); ?></span></td>
                                <td>
                                    <form method="POST" style="display:grid; gap:8px;">
                                        <?php echo csrfInput(); ?>
                                        <input type="hidden" name="task_id" value="<?php echo (int) $task['id']; ?>">
                                        <select name="status" class="form-control">
                                            <option value="pending" <?php echo $task['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="in_progress" <?php echo $task['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="completed" <?php echo $task['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        </select>
                                        <button type="submit" name="update_task_status" class="btn btn-outline-gold">Update</button>
                                    </form>
                                </td>
                                <td>
                                    <?php if (hasRole(['admin', 'receptionist'])): ?>
                                        <a href="tasks.php?edit=<?php echo (int) $task['id']; ?>" class="btn btn-outline-gold" style="margin-bottom:8px;">Edit</a>
                                        <a href="tasks.php?delete=<?php echo (int) $task['id']; ?>" class="btn btn-danger" onclick="return confirm('Delete this task?');">Delete</a>
                                    <?php else: ?>
                                        <span class="badge badge-completed"><?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No tasks found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
