<?php
session_start();
include '../includes/db.php';
$id = $_POST['id'] ?? $_GET['id'] ?? null;
$icons = $pdo->query("SELECT * FROM icons")->fetchAll(PDO::FETCH_ASSOC);

$message = '';
$errors = [];
// ===== LOAD EXISTING DATA FOR EDIT =====
if ($id && $_SERVER['REQUEST_METHOD'] != 'POST') {

    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ? AND is_deleted = 0");
    $stmt->execute([$id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($category) {
        $_POST['category_name'] = $category['category_name'];
        $_POST['description'] = $category['description'];
        $_POST['status'] = $category['status'];
    }
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
    if (!$id && empty($icon)) {
        $errors['icon'] = "Category Image is required";
    }
    // ===== DUPLICATE CHECK =====
    if (!empty($name)) {

        // ===== DUPLICATE CHECK (FIXED CLEAN VERSION) =====
        if (!empty($normalizedName)) {

            $query = "SELECT id FROM categories 
              WHERE LOWER(TRIM(category_name)) = ? 
              AND is_deleted = 0";

            $params = [$normalizedName];

            if (!empty($id)) {
                $query .= " AND id != ?";
                $params[] = $id;
            }

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);

            if ($stmt->fetch()) {
                $errors['category_name'] = "Category already exists";
            }
        }
    }

    if (empty($errors)) {

        // ===== EDIT MODE =====
        if ($id) {
            // keep old image if not uploaded
            if (empty($icon)) {
                $stmt = $pdo->prepare("SELECT icon FROM categories WHERE id = ?");
                $stmt->execute([$id]);
                $icon = $stmt->fetchColumn();
            }

            $stmt = $pdo->prepare("
                UPDATE categories 
                SET category_name = ?, description = ?, status = ?, icon = ?, updated_at = NOW(), updated_by = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $normalizedName,
                $description,
                $status,
                $icon,
                $_SESSION['user_id'] ?? 1,  // ✅ FIXED
                $id
            ]);

            $msg = "Category updated successfully";

        } else {

            // ===== CREATE MODE =====
            $stmt = $pdo->prepare("
                INSERT INTO categories 
                (category_name, description, status, icon, created_at, created_by) 
                VALUES (?, ?, ?, ?, NOW(), ?)
            ");

            $stmt->execute([
                $normalizedName,
                $description,
                $status,
                $icon,
                $_SESSION['user_id'] ?? 1,
            ]);

            $msg = "Category created successfully";
        }

        echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: 'Success!',
                text: '$msg',
                icon: 'success'
            }).then(() => {
                window.location.href = 'categories.php';
            });
        });
        </script>";
    }
}

$pageTitle = "Add New Category - Sogasu";
$activePage = "categories";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div >
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Add New Category</h2>
                <p class="text-muted">Create a main garment category</p>
            </div>
            <button class="btn" onclick="history.back()"
                style="background: white; border: 1px solid #e2e8f0; color: #64748b;">
                <i class="ri-arrow-left-line"></i> Back
            </button>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data" novalidate
        style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
        <input type="hidden" name="id" value="<?= $id ?? '' ?>">

        <!-- Left Column: Category Details -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">

            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Category
                    Information</h3>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Category Name <span style="color:red">*</span></label>
                    <input type="text" name="category_name" class="form-control" placeholder="e.g. Blouses"
                        maxlength="50" value="<?php echo $_POST['category_name'] ?? ''; ?>">

                    <?php if (!empty($errors['category_name'])): ?>
                        <small style="color:red;"><?php echo $errors['category_name']; ?></small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4" maxlength="300"
                        placeholder="Describe this category..."><?php echo $_POST['description'] ?? ''; ?></textarea>

                    
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
                        <?php if (!empty($errors['status'])): ?>
                            <small style="color:red;"><?php echo $errors['status']; ?></small>
                        <?php endif; ?>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Category Image <span style="color:red">*</span></label>
                    <div onclick="document.getElementById('category_image').click()"
                        style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 2rem; text-align: center; cursor: pointer; position: relative; overflow: hidden; min-height: 150px; display: flex; flex-direction: column; align-items: center; justify-content: center;">

                        <img id="imagePreview" src=""
                            style="display: none; max-width: 100%; max-height: 120px; border-radius: 4px; object-fit: contain;">

                        <div id="uploadPlaceholder">
                            <i class="ri-image-add-line" style="font-size: 2rem; color: #94a3b8;"></i>
                            <div style="font-size: 0.85rem; color: #64748b; margin-top: 0.5rem;">Click to upload image
                            </div>
                            <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem;">PNG, JPG or GIF (Max.
                                2MB)</div>
                        </div>
                    </div>
                    <input type="file" name="category_image" id="category_image" style="display: none;" accept="image/*"
                        onchange="previewImage(this)">
                    <?php if (!empty($errors['icon'])): ?>
                        <small style="color:red;"><?php echo $errors['icon']; ?></small>
                    <?php endif; ?>
                </div>

                <!-- Actions -->
                <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                    <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Actions</h3>
                    <button type="submit" class="btn btn-primary w-full">
                        <?= $id ? 'Update Category' : 'Create Category' ?>
                    </button>
                    <button type="button" onclick="resetForm()" class="btn w-full"
                        style="justify-content: center; width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b;">
                        Cancel
                    </button>
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
    function resetForm() {
        window.location.href = window.location.pathname;
    }
</script>
<?php include 'includes/footer.php'; ?>