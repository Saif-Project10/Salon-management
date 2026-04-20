<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireRole(['admin', 'receptionist']);

$error = '';
$success = '';
$serviceForEdit = null;

$allowedCategories = ['Hair', 'Skin', 'Bridal', 'Nails'];
$allowedExtensions = ['jpg', 'jpeg', 'png', 'avif', 'webp'];
$allowedMimeTypes = [
    'image/jpeg',
    'image/png',
    'image/avif',
    'image/webp',
];

$imageWebBase = '/salon-management/assets/images/';
$imageUploadDir = dirname(__DIR__) . '/assets/images/';
// Place your fallback image at /salon-management/assets/images/service-default.jpg
$defaultServiceImage = $imageWebBase . 'service-default.jpg';
$dashboardUrl = dashboardUrlForRole();

if (isset($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];

    if ($deleteId > 0) {
        $stmt = $pdo->prepare("SELECT featured_image FROM services WHERE id = ?");
        $stmt->execute([$deleteId]);
        $service = $stmt->fetch();

        if ($service) {
            $deleteStmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
            $deleteStmt->execute([$deleteId]);
            $success = 'Service deleted successfully.';

            $oldImage = trim((string) ($service['featured_image'] ?? ''));
            if ($oldImage !== '') {
                $oldImagePath = $imageUploadDir . $oldImage;
                if (is_file($oldImagePath)) {
                    @unlink($oldImagePath);
                }
            }
        } else {
            $error = 'Service not found.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_service'])) {
    verifyCsrfToken();

    $serviceId = (int) ($_POST['service_id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $duration = (int) ($_POST['duration'] ?? 0);
    $price = (float) ($_POST['price'] ?? 0);
    $category = (string) ($_POST['category'] ?? '');
    $existingImage = '';
    $featuredImageToSave = $existingImage;
    $newUploadedImage = '';

    if ($serviceId > 0) {
        $currentStmt = $pdo->prepare("SELECT featured_image FROM services WHERE id = ?");
        $currentStmt->execute([$serviceId]);
        $existingService = $currentStmt->fetch();

        if (!$existingService) {
            $error = 'Service not found.';
        } else {
            $existingImage = trim((string) ($existingService['featured_image'] ?? ''));
            $featuredImageToSave = $existingImage;
        }
    }

    if ($error === '' && ($name === '' || $duration <= 0 || $price <= 0)) {
        $error = 'Service name, duration, and price are required.';
    } elseif ($error === '' && !in_array($category, $allowedCategories, true)) {
        $error = 'Please choose a valid category.';
    } else {
        if (isset($_FILES['image']) && (int) $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadError = (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($uploadError !== UPLOAD_ERR_OK) {
                $error = 'Image upload failed. Please try again.';
            } else {
                $fileSize = (int) ($_FILES['image']['size'] ?? 0);
                if ($fileSize <= 0 || $fileSize > (5 * 1024 * 1024)) {
                    $error = 'Image must be less than or equal to 5MB.';
                }

                $originalName = (string) ($_FILES['image']['name'] ?? '');
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                if (!in_array($extension, $allowedExtensions, true)) {
                    $error = 'Only jpg, jpeg, png, avif, and webp images are allowed.';
                }

                $tmpName = (string) ($_FILES['image']['tmp_name'] ?? '');
                if ($error === '' && $tmpName !== '' && function_exists('finfo_open')) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $detectedMime = $finfo ? finfo_file($finfo, $tmpName) : false;
                    if ($finfo) {
                        finfo_close($finfo);
                    }
                    if ($detectedMime !== false && !in_array($detectedMime, $allowedMimeTypes, true)) {
                        $error = 'Invalid image file type.';
                    }
                }

                if ($error === '') {
                    if (!is_dir($imageUploadDir) && !mkdir($imageUploadDir, 0755, true)) {
                        $error = 'Image directory is not writable.';
                    } else {
                        $newFilename = time() . '_' . preg_replace('/[^a-zA-Z0-9\.]/', '_', $_FILES["image"]["name"]);
                        $targetPath = $imageUploadDir . $newFilename;

                        if (!move_uploaded_file($tmpName, $targetPath)) {
                            $error = 'Failed to save uploaded image.';
                        } else {
                            $featuredImageToSave = $newFilename;
                            $newUploadedImage = $newFilename;
                        }
                    }
                }
            }
        }
    }

    if ($error === '') {
        if ($serviceId > 0) {
            $stmt = $pdo->prepare("
                UPDATE services
                SET name = ?, description = ?, duration = ?, price = ?, category = ?, featured_image = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $description, $duration, $price, $category, $featuredImageToSave, $serviceId]);
            $success = 'Service updated successfully.';

            if ($newUploadedImage !== '' && $existingImage !== '' && $existingImage !== $newUploadedImage) {
                $oldImagePath = $imageUploadDir . $existingImage;
                if (is_file($oldImagePath)) {
                    @unlink($oldImagePath);
                }
            }
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO services (name, description, duration, price, category, featured_image)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $description, $duration, $price, $category, $featuredImageToSave]);
            $success = 'Service added successfully.';
        }
    }
}

if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    if ($editId > 0) {
        $stmt = $pdo->prepare("SELECT id, name, description, duration, price, category, featured_image FROM services WHERE id = ?");
        $stmt->execute([$editId]);
        $serviceForEdit = $stmt->fetch();

        if (!$serviceForEdit && $error === '') {
            $error = 'Service not found for editing.';
        }
    }
}

$servicesStmt = $pdo->prepare("
    SELECT id, name, description, duration, price, category, featured_image
    FROM services
    ORDER BY FIELD(category, 'Hair', 'Skin', 'Bridal', 'Nails'), name ASC
");
$servicesStmt->execute();
$services = $servicesStmt->fetchAll();

include '../includes/header.php';
?>

<div class="container py-2">
    <div class="flex flex-between mb-2">
        <div>
            <span class="eyebrow">Service Management</span>
            <h2>Manage salon services</h2>
        </div>
        <a href="<?php echo htmlspecialchars($dashboardUrl); ?>" class="btn btn-outline-gold">&larr; Dashboard</a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="dashboard-grid">
        <div class="form-card" style="margin-top:0;">
            <h3 class="mb-1"><?php echo $serviceForEdit ? 'Edit Service' : 'Add New Service'; ?></h3>
            <form method="POST" enctype="multipart/form-data">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="service_id" value="<?php echo (int) ($serviceForEdit['id'] ?? 0); ?>">

                <div class="form-group">
                    <label>Service Name</label>
                    <input
                        type="text"
                        name="name"
                        class="form-control"
                        required
                        value="<?php echo htmlspecialchars((string) ($serviceForEdit['name'] ?? '')); ?>"
                    >
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars((string) ($serviceForEdit['description'] ?? '')); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Duration in Minutes</label>
                    <input
                        type="number"
                        name="duration"
                        class="form-control"
                        required
                        min="1"
                        value="<?php echo htmlspecialchars((string) ($serviceForEdit['duration'] ?? '')); ?>"
                    >
                </div>

                <div class="form-group">
                    <label>Price in $</label>
                    <input
                        type="number"
                        name="price"
                        class="form-control"
                        required
                        min="0.01"
                        step="0.01"
                        value="<?php echo htmlspecialchars((string) ($serviceForEdit['price'] ?? '')); ?>"
                    >
                </div>

                <div class="form-group">
                    <label>Category</label>
                    <select name="category" class="form-control" required>
                        <option value="">Select category</option>
                        <?php foreach ($allowedCategories as $cat): ?>
                            <option
                                value="<?php echo htmlspecialchars($cat); ?>"
                                <?php echo (($serviceForEdit['category'] ?? '') === $cat) ? 'selected' : ''; ?>
                            >
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Featured Image</label>
                    <input type="file" name="image" class="form-control" accept=".jpg,.jpeg,.png,.avif,.webp,image/jpeg,image/png,image/avif,image/webp">
                    <small style="color:#888;">Allowed: jpg, jpeg, png, avif, webp (max 5MB)</small>
                </div>

                <?php if (!empty($serviceForEdit['featured_image']) && is_file($imageUploadDir . $serviceForEdit['featured_image'])): ?>
                    <div class="form-group">
                        <label>Current Image</label>
                        <div>
                            <img
                                src="<?php echo htmlspecialchars($imageWebBase . $serviceForEdit['featured_image']); ?>"
                                alt="Current featured image"
                                style="width:70px; height:70px; object-fit:cover; border-radius:8px; border:1px solid #ddd;"
                            >
                        </div>
                    </div>
                <?php endif; ?>

                <button type="submit" name="save_service" class="btn btn-primary" style="width:100%;">Save Service</button>
                <?php if ($serviceForEdit): ?>
                    <a href="manage_services.php" class="btn btn-outline-gold mt-1" style="width:100%; display:inline-block; text-align:center;">Cancel Edit</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-responsive" style="grid-column: span 2;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Thumbnail</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                        <tr>
                            <td>
                                <?php
                                $thumbUrl = $defaultServiceImage;
                                if (!empty($service['featured_image']) && is_file($imageUploadDir . $service['featured_image'])) {
                                    $thumbUrl = $imageWebBase . $service['featured_image'];
                                }
                                ?>
                                <img
                                    src="<?php echo htmlspecialchars($thumbUrl); ?>"
                                    alt="<?php echo htmlspecialchars($service['name']); ?>"
                                    style="width:52px; height:52px; object-fit:cover; border-radius:8px; border:1px solid #ddd;"
                                >
                            </td>
                            <td><?php echo htmlspecialchars($service['name']); ?></td>
                            <td><?php echo htmlspecialchars($service['category']); ?></td>
                            <td>$<?php echo number_format((float) $service['price'], 2); ?></td>
                            <td>
                                <a href="manage_services.php?edit=<?php echo (int) $service['id']; ?>" class="btn btn-outline-gold" style="padding: 5px 10px; font-size: 0.8rem;">Edit</a>
                                <a href="manage_services.php?delete=<?php echo (int) $service['id']; ?>" class="btn btn-danger" style="padding: 5px 10px; font-size: 0.8rem;" onclick="return confirm('Delete this service?');">Del</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$services): ?>
                        <tr>
                            <td colspan="5">No services have been added yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
