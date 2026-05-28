<?php
include '../includes/db.php';
session_start();
$user_id = $_SESSION['user_id'] ?? 1;
$editData = null;

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    $stmt = $pdo->prepare("SELECT * FROM sub_categories WHERE id=?");
    $stmt->execute([$id]);
    $editData = $stmt->fetch();
}
$categories = [];
$stmt = $pdo->query("SELECT id, category_name FROM categories WHERE status='active' AND is_deleted = 0");
$categories = $stmt->fetchAll();

$errors = [];

if (isset($_POST['submit'])) {

    $sub_category_name = trim($_POST['sub_category_name']);
    $parent_category = $_POST['parent_category'];
    $description = trim($_POST['description']);
    $price = $_POST['price'];
    $fabric = trim($_POST['fabric']);
    $preparation_days = $_POST['preparation_days'] ?: 0;
    $status = $_POST['status'];

    // VALIDATIONS
    if ($sub_category_name == "") {
        $errors['sub_category_name'] = "Sub Category Name is required";
    }

    if ($parent_category == "") {
        $errors['parent_category'] = "Category Name is required";
    }

    if ($description == "") {
        $errors['description'] = "Description is required";
    }

    if ($price == "") {
        $errors['price'] = "Price is required";
    }

    if ($fabric == "") {
        $errors['fabric'] = "Fabric is required";
    }

    if ($status == "") {
        $errors['status'] = "Status is required";
    }

    // IMAGE VALIDATION
    if (!isset($_GET['id']) && $_FILES['image']['name'] == "") {
        $errors['image'] = "Image is required";
    }
    // ===== DUPLICATE CHECK =====
    if (!isset($errors['sub_category_name']) && $sub_category_name != "" && $parent_category != "") {

        $checkQuery = "SELECT id FROM sub_categories 
                   WHERE name = ? AND category_id = ? AND is_deleted = 0";

        $params = [$sub_category_name, $parent_category];

        // EXCLUDE CURRENT RECORD (EDIT MODE)
        if (isset($_GET['id'])) {
            $checkQuery .= " AND id != ?";
            $params[] = $_GET['id'];
        }

        $stmt = $pdo->prepare($checkQuery);
        $stmt->execute($params);

        if ($stmt->fetch()) {
            $errors['sub_category_name'] = "Sub Category already exists";
        }
    }

    // IF NO ERRORS → INSERT
    if (empty($errors)) {

        date_default_timezone_set('Asia/Kolkata');
        $created_at = date("Y-m-d H:i:s");

        // IMAGE HANDLE
        if ($_FILES['image']['name'] != "") {
            $image_name = time() . "_" . $_FILES['image']['name'];
            $tmp = $_FILES['image']['tmp_name'];
            $upload_path = __DIR__ . "/uploads/" . $image_name;
            move_uploaded_file($tmp, $upload_path);
        } else {
            $image_name = $editData['image'] ?? '';
        }

        // UPDATE
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare("UPDATE sub_categories SET 
            name=?, category_id=?, description=?, price=?, preparation_days=?, fabric=?, status=?, image=?, updated_at=?, updated_by=? WHERE id=?");

            $stmt->execute([
                $sub_category_name,
                $parent_category,
                $description,
                $price,
                $preparation_days,
                $fabric,
                $status,
                $image_name,
                date("Y-m-d H:i:s"),
                $user_id,
                $_GET['id']
            ]);
            header("Location: sub-categories.php?updated=1");
            exit;
        } else {
            // INSERT
            $stmt = $pdo->prepare("INSERT INTO sub_categories 
            (name, category_id, description, price, preparation_days, fabric, status, image, created_at, created_by, is_deleted)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");

            $stmt->execute([
                $sub_category_name,
                $parent_category,
                $description,
                $price,
                $preparation_days,
                $fabric,
                $status,
                $image_name,
                $created_at,
                $user_id
            ]);
        }

        header("Location: sub-categories.php?created=1");
        exit;
    }
}
$pageTitle = "Add Sub Category - Sogasu";
$activePage = "sub-categories";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div >
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">
                    <?= isset($_GET['id']) ? 'Edit Sub Category' : 'Add Sub Category' ?>
                </h2>
                <p class="text-muted">
                    <?= isset($_GET['id']) ? 'Update your sub category details' : 'Define a specific garment type' ?>
                </p>
            </div>
            <button class="btn" onclick="window.location.href='sub-categories.php'"
                style="background: white; border: 1px solid #e2e8f0; color: #64748b;">
                <i class="ri-arrow-left-line"></i> Back
            </button>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data" style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
        <!-- Left Column: Primary Details -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Sub Category
                    Information</h3>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Sub Category Name <span style="color:red">*</span></label>
                        <input type="text" name="sub_category_name"
                            value="<?= $editData['name'] ?? $sub_category_name ?? '' ?>"
                            placeholder="e.g. Princess Cut Blouse">
                        <span class="text-red"><?= $errors['sub_category_name'] ?? '' ?></span>

                    </div>
                    <div class="form-group">
                        <label class="form-label">Category Name <span style="color:red">*</span></label>
                        <select name="parent_category" class="form-control">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ((isset($_POST['parent_category']) && $_POST['parent_category'] == $cat['id']) || (isset($editData) && $editData['category_id'] == $cat['id'])) ? 'selected' : '' ?>>
                                    <?= $cat['category_name'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="text-red"><?= $errors['parent_category'] ?? '' ?></span>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1rem;">
                    <label class="form-label">Description <span style="color:red">*</span></label>
                    <!-- <textarea name="description" placeholder="Brief description..."><?= $editData['description'] ?? $description ?? '' ?></textarea> -->
                    <textarea name="description"
                        placeholder="Brief description..."><?= $_POST['description'] ?? $editData['description'] ?? '' ?></textarea>
                    <span class="text-red"><?= $errors['description'] ?? '' ?></span>
                </div>
            </div>

            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Pricing & Fabric
                </h3>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Base Price / Starting From <span style="color:red">*</span></label>
                        <input type="number" name="price" step="0.01"
                            value="<?= $_POST['price'] ?? $editData['price'] ?? '' ?>" placeholder="0.00">
                        <span class="text-red"><?= $errors['price'] ?? '' ?></span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Default / Recommended Fabric <span style="color:red">*</span></label>
                        <input type="text" name="fabric" value="<?= $_POST['fabric'] ?? $editData['fabric'] ?? '' ?>"
                            placeholder="e.g. Cotton, Silk">
                        <span class="text-red"><?= $errors['fabric'] ?? '' ?></span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Preparation Time (Days)</label>
                        <input type="number" name="preparation_days"
                            value="<?= $_POST['preparation_days'] ?? $editData['preparation_days'] ?? '' ?>"
                            placeholder="e.g. 3">
                    </div>

                </div>
            </div>

        </div>

        <!-- Right Column: Settings -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">

            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Status & Display
                    <span style="color:red">*</span>
                </h3>

                <select name="status">
                    <option value="">Select Status</option>
                    <option value="active" <?= (($_POST['status'] ?? $editData['status'] ?? '') == 'active') ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= (($_POST['status'] ?? $editData['status'] ?? '') == 'inactive') ? 'selected' : '' ?>>Inactive</option>
                </select>
                <span class="text-red"><?= $errors['status'] ?? '' ?></span>

                <div class="form-group">
                    <label class="form-label">Visual Reference (Diagram) <span style="color:red">*</span></label>

                    <div style="background:#f8fafc;border:1px dashed #cbd5e1;border-radius:6px; padding:1.5rem;text-align:center;cursor:pointer;"
                        onclick="openFilePicker()">
                        <!-- TEXT -->
                        <div id="uploadText" <?= !empty($editData['image']) ? 'style="display:none;"' : '' ?>>
                            <i class="ri-upload-cloud-2-line" style="font-size:1.5rem;color:#94a3b8;"></i>
                            <div style="font-size:0.8rem;color:#64748b;margin-top:0.5rem;">
                                Click to upload (Max 5MB | JPG, PNG)
                            </div>
                        </div>

                        <!-- PREVIEW -->
                        <img id="previewImage"
                            src="<?= !empty($editData['image']) ? 'uploads/' . $editData['image'] : '' ?>"
                            style="max-width:120px;border-radius:6px; <?= empty($editData['image']) ? 'display:none;' : 'display:block;' ?>">

                        <input type="file" id="imageInput" name="image" accept="image/*" style="display:none;">
                        <div class="text-red"><?= $errors['image'] ?? '' ?></div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Actions</h3>
                <button type="submit" name="submit" class="btn btn-primary w-full"
                    style="justify-content: center; width: 100%; margin-bottom: 1rem;" <?= isset($_GET['id']) ? 'Update Sub Category' : 'Create Sub Category' ?>>Create Sub Category</button>
                <button type="reset" class="btn w-full" onclick="history.back()"
                    style="justify-content: center; width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b;">Cancel</button>
            </div>
        </div>
    </form>
</main>

<style>
    .text-red {
        color: #dc2626;
        font-size: 0.8rem;
    }

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
<script>
    function previewImage(event) {
        const file = event.target.files[0];
        const preview = document.getElementById('previewImage');
        const text = document.getElementById('uploadText');

        if (file) {
            preview.src = URL.createObjectURL(file);
            preview.style.display = "block";
            text.style.display = "none";
        }
    }

    function openFilePicker() {
        document.getElementById('imageInput').click();
    }

    document.getElementById('imageInput').addEventListener('change', function (e) {
        const file = e.target.files[0];
        const preview = document.getElementById('previewImage');
        const text = document.getElementById('uploadText');

        if (file) {
            preview.src = URL.createObjectURL(file);
            preview.style.display = "block";
            text.style.display = "none";
        }
    });
    function resetForm() {
        window.location.href = window.location.pathname;
    }
</script>
<?php include 'includes/footer.php'; ?>