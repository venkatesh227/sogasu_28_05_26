<?php
session_start();
include '../includes/db.php';
$icons = $pdo->query("SELECT * FROM icons")->fetchAll(PDO::FETCH_ASSOC);

$message = '';
$category = null;
$errors = [];
$categoryId = isset($_GET['id']) ? $_GET['id'] : null;

if (!$categoryId) {
    header('Location: categories.php');
    exit;
}

// Fetch category
$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->execute([$categoryId]);
$category = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$category) {
    header('Location: categories.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['category_name']);
    $normalizedName = strtolower(preg_replace('/\s+/', ' ', $name));
    $description = trim($_POST['description']);
    $status = $_POST['status'];
    $icon = "";

    if (isset($_FILES['category_image']) && $_FILES['category_image']['error'] == 0) {
        $targetDir = "../uploads/categories/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $fileName = time() . '_' . basename($_FILES['category_image']['name']);
        $targetFilePath = $targetDir . $fileName;
        $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);

        $allowTypes = array('jpg', 'png', 'jpeg', 'gif');
        if (in_array(strtolower($fileType), $allowTypes)) {
            if (move_uploaded_file($_FILES['category_image']['tmp_name'], $targetFilePath)) {
                $icon = "uploads/categories/" . $fileName;
            } else {
                $errors['icon'] = "Failed to upload image.";
            }
        } else {
            $errors['icon'] = "Only JPG, JPEG, PNG, & GIF files are allowed.";
        }
    }

    if (empty($name)) {
        $errors['category_name'] = "Category Name is required";
    }

    if (empty($status)) {
        $errors['status'] = "Status is required";
    }

    // image required only for create
    if (!$categoryId && empty($icon)) {
        $errors['icon'] = "Category Image is required";
    }
    if (empty($description)) {
        $errors['description'] = "Description is required";
    }
    // ===== DUPLICATE CHECK =====
    if (empty($errors)) {

        // ===== DUPLICATE CHECK (FIXED CLEAN VERSION) =====
        if (!empty($normalizedName)) {

            $query = "SELECT id FROM categories 
              WHERE LOWER(TRIM(category_name)) = ? 
              AND is_deleted = 0";

            $params = [$normalizedName];

            if (!empty($categoryId)) {
                $query .= " AND id != ?";
                $params[] = $categoryId;
            }

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);

            if ($stmt->fetch()) {
                $errors['category_name'] = "Category already exists";
            }
        }
    }
    $name = trim($_POST['category_name']);
    $description = trim($_POST['description']);
    $status = $_POST['status'];
    $icon = $_POST['icon'] ?? $category['icon'];

    if (isset($_FILES['category_image']) && $_FILES['category_image']['error'] == 0) {
        $targetDir = "../uploads/categories/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $fileName = time() . '_' . basename($_FILES['category_image']['name']);
        $targetFilePath = $targetDir . $fileName;
        $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);

        $allowTypes = array('jpg', 'png', 'jpeg', 'gif');
        if (in_array(strtolower($fileType), $allowTypes)) {
            if (move_uploaded_file($_FILES['category_image']['tmp_name'], $targetFilePath)) {
                $icon = "uploads/categories/" . $fileName;
            } else {
                $message = "Failed to upload image.";
            }
        } else {
            $message = "Only JPG, JPEG, PNG, & GIF files are allowed.";
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE categories 
SET category_name=?, description=?, status=?, icon=?, updated_at=NOW(), updated_by=? 
WHERE id=?");

        $stmt->execute([
            $name,                    // ✅ FIXED
            $description,
            $status,
            $icon,
            $_SESSION['user_id'],     // ✅ user tracking
            $categoryId               // ✅ FIXED
        ]);
        $_SESSION['success_message'] = 'Category updated successfully!';
        header('Location: categories.php');
        exit;
    } else {
        $message = 'Category name is required.';
    }
}

$pageTitle = "Edit Category - Sogasu";
$activePage = "categories";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="margin-bottom: 1rem;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Edit Category</h2>
                <p class="text-muted">Update garment category details</p>
            </div>
            <button class="btn" onclick="history.back()"
                style="background: white; border: 1px solid #e2e8f0; color: #64748b;">
                <i class="ri-arrow-left-line"></i> Back
            </button>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data" style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">

        <!-- Left Column: Category Details -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">

            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Category
                    Information</h3>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Category Name</label>
                    <input type="text" name="category_name" class="form-control" placeholder="e.g. Blouses"
                        value="<?php echo htmlspecialchars($category['category_name']); ?>" required>
                    <?php if (!empty($errors['category_name'])): ?>
                        <small style="color:red;">
                            <?php echo $errors['category_name']; ?>
                        </small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4"
                        placeholder="Describe this category..."><?php echo htmlspecialchars($category['description']); ?></textarea>
                </div>
            </div>

        </div>

        <!-- Right Column: Settings -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">

            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Status & Display
                </h3>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?php echo $category['status'] == 'active' ? 'selected' : ''; ?>>Active
                        </option>
                        <option value="inactive" <?php echo $category['status'] == 'inactive' ? 'selected' : ''; ?>>
                            Inactive</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Category Image</label>
                    <div onclick="document.getElementById('category_image').click()"
                        style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 2rem; text-align: center; cursor: pointer; position: relative; overflow: hidden; min-height: 150px; display: flex; flex-direction: column; align-items: center; justify-content: center;">

                        <?php if (strpos($category['icon'], 'uploads/') === 0): ?>
                            <img id="imagePreview" src="../<?php echo htmlspecialchars($category['icon']); ?>"
                                style="max-width: 100%; max-height: 120px; border-radius: 4px; object-fit: contain;">
                            <div id="uploadPlaceholder" style="display: none;">
                                <i class="ri-image-add-line" style="font-size: 2rem; color: #94a3b8;"></i>
                                <div style="font-size: 0.85rem; color: #64748b; margin-top: 0.5rem;">Click to change image
                                </div>
                            </div>
                        <?php else: ?>
                            <img id="imagePreview" src=""
                                style="display: none; max-width: 100%; max-height: 120px; border-radius: 4px; object-fit: contain;">
                            <div id="uploadPlaceholder">
                                <i class="<?php echo $category['icon']; ?>" style="font-size: 2rem; color: #3085d6;"></i>
                                <div style="font-size: 0.85rem; color: #64748b; margin-top: 0.5rem;">Click to change image
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <input type="file" name="category_image" id="category_image" style="display: none;" accept="image/*"
                        onchange="previewImage(this)">
                    <input type="hidden" name="icon" value="<?php echo htmlspecialchars($category['icon']); ?>">
                </div>

                <!-- Actions -->
                <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                    <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Actions</h3>
                    <button type="submit" class="btn btn-primary w-full"
                        style="justify-content: center; width: 100%; margin-bottom: 1rem;">Update Category</button>
                    <button type="button" class="btn w-full" onclick="window.location.href='categories.php'"
                        style="justify-content: center; width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b;">Cancel</button>
                </div>

            </div>

    </form>
</main>

<style>
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .form-label {
        font-size: 0.875rem;
        font-weight: 500;
        color: #334155;
    }

    .form-control,
    .form-select {
        padding: 0.75rem;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 0.95rem;
        width: 100%;
        outline: none;
        transition: border-color 0.2s;
        font-family: inherit;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--primary);
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function previewImage(input) {
        const preview = document.getElementById('imagePreview');
        const placeholder = document.getElementById('uploadPlaceholder');

        if (input.files && input.files[0]) {
            const reader = new FileReader();

            reader.onload = function (e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
                placeholder.style.display = 'none';
            }

            reader.readAsDataURL(input.files[0]);
        }
    }
</script>