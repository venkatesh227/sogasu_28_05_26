<?php
session_start();
include '../includes/db.php';
$errors = [];
$old = [];
$id = $_GET['id'] ?? null;

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM branches WHERE id = ? AND is_deleted = 0");
    $stmt->execute([$id]);
    $branch = $stmt->fetch();

    if (!$branch) {
        die("Branch not found");
    }

    $old = $branch;
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // SANITIZE
    $old['branch_name'] = trim($_POST['branch_name'] ?? '');
    $old['branch_code'] = trim($_POST['branch_code'] ?? '');
    $old['description'] = trim($_POST['description'] ?? '');
    $old['phone'] = trim($_POST['phone'] ?? '');
    $old['city'] = trim($_POST['city'] ?? '');
    $old['address'] = trim($_POST['address'] ?? '');
    $old['status'] = $_POST['status'] ?? 'active';
    $old['color_theme'] = $_POST['color_theme'] ?? '';
    $old['measurement_mode'] = $_POST['measurement_mode'] ?? 'CMS';

    // ===== VALIDATIONS =====

    if ($old['branch_name'] == '') {
        $errors['branch_name'] = "Branch name required";
    } elseif (strlen($old['branch_name']) > 100) {
        $errors['branch_name'] = "Max 100 characters allowed";
    }

    if ($old['branch_code'] == '') {
        $errors['branch_code'] = "Branch code required";
    } elseif (strlen($old['branch_code']) > 50) {
        $errors['branch_code'] = "Max 50 characters allowed";
    }

    if ($old['phone'] == '') {
        $errors['phone'] = "Phone required";
    } elseif (!preg_match("/^[6-9][0-9]{9}$/", $old['phone'])) {
        $errors['phone'] = "Invalid mobile number";
    }
    if (!isset($errors['phone'])) {
        $checkPhone = $pdo->prepare("
        SELECT id FROM branches 
        WHERE phone = ? 
        AND id != ? 
        AND is_deleted = 0
    ");
        $checkPhone->execute([$old['phone'], $id ?? 0]);

        if ($checkPhone->rowCount() > 0) {
            $errors['phone'] = "Mobile number already exists";
        }
    }

    if ($old['city'] == '') {
        $errors['city'] = "City required";
    } elseif (!preg_match("/^[a-zA-Z ]+$/", $old['city'])) {
        $errors['city'] = "Only letters allowed";
    }

    if ($old['address'] == '') {
        $errors['address'] = "Address required";
    } elseif (!preg_match("/^[a-zA-Z0-9 ,.-]+$/", $old['address'])) {
        $errors['address'] = "Invalid address format";
    }

    if ($old['color_theme'] == '') {
        $errors['color_theme'] = "Select a color";
    }
    $validStatus = ['active', 'inactive', 'maintenance'];

    if (!in_array($old['status'], $validStatus, true)) {
        $errors['status'] = "Invalid status";
    }

    $validMeasurementModes = ['CMS', 'Inches'];
    if (!in_array($old['measurement_mode'], $validMeasurementModes, true)) {
        $errors['measurement_mode'] = "Invalid measurement mode";
    }

    if (!isset($errors['branch_code'])) {
        $check = $pdo->prepare("SELECT id FROM branches WHERE branch_code = ? AND id != ? AND is_deleted = 0");
        $check->execute([$old['branch_code'], $id ?? 0]);

        if ($check->rowCount() > 0) {
            $errors['branch_code'] = "Branch code already exists";
        }
    }

    // ===== INSERT / UPDATE =====

    if (empty($errors)) {

        if ($id) {
            // UPDATE
            $stmt = $pdo->prepare("
                UPDATE branches SET 
                branch_name=?, branch_code=?, description=?, 
                phone=?, city=?, address=?, 
                status=?, color_theme=?, measurement_mode=?, updated_at=NOW(), updated_by=?
                WHERE id=?
            ");

            $stmt->execute([
                $old['branch_name'],
                $old['branch_code'],
                $old['description'],
                $old['phone'],
                $old['city'],
                $old['address'],
                $old['status'],
                $old['color_theme'],
                $old['measurement_mode'],
                $_SESSION['user_id'] ?? null,
                $id
            ]);
            $_SESSION['success'] = "updated";

        } else {
            $stmt = $pdo->prepare("
                INSERT INTO branches 
                (branch_name, branch_code, description, phone, city, address, status, color_theme, measurement_mode, created_at, created_by, is_deleted)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, 0)
            ");

            $stmt->execute([
                $old['branch_name'],
                $old['branch_code'],
                $old['description'],
                $old['phone'],
                $old['city'],
                $old['address'],
                $old['status'],
                $old['color_theme'],
                $old['measurement_mode'],
                $_SESSION['user_id'] ?? null
            ]);

            $_SESSION['success'] = "added";
        }

        header("Location: branches.php");
        exit;
    }
}
$pageTitle = "Add New Branch - Sogasu";
$activePage = "branches";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="margin-bottom: 1rem;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Add New Branch</h2>
                <p class="text-muted">Register a new store location</p>
            </div>
            <button class="btn" onclick="history.back()"
                style="background: white; border: 1px solid #e2e8f0; color: #64748b;">
                <i class="ri-arrow-left-line"></i> Back
            </button>
        </div>
    </div>

    <form method="POST" style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">

        <!-- Left Column: Branch Info -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">

            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Branch Details
                </h3>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Branch Name <span style="color:red">*</span></label>
                        <input type="text" name="branch_name" class="form-control" placeholder="e.g. Jayanagar Branch"
                            maxlength="100" value="<?= $old['branch_name'] ?? '' ?>">
                        <?php if (isset($errors['branch_name'])): ?>
                            <small style="color:red"><?= $errors['branch_name'] ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Branch Code <span style="color:red">*</span></label>
                        <input type="text" name="branch_code" class="form-control" placeholder="e.g. BLR-JAY-01"
                            maxlength="50" value="<?= $old['branch_code'] ?? '' ?>">
                        <?php if (isset($errors['branch_code'])): ?>
                            <small style="color:red"><?= $errors['branch_code'] ?></small>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="margin-top: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2" maxlength="400"
                            placeholder="Brief description of the branch location or type"><?= $old['description'] ?? '' ?></textarea>
                    </div>
                </div>
            </div>

            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Location & Contact
                </h3>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Phone Number <span style="color:red">*</span></label>
                        <input type="text" name="phone" value="<?= $old['phone'] ?? '' ?>" maxlength="10"
                            placeholder="Shop Contact number" class="form-control">
                        <?php if (isset($errors['phone'])): ?>
                            <small style="color:red"><?= $errors['phone'] ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">City <span style="color:red">*</span></label>
                        <input type="text" name="city" value="<?= $old['city'] ?? '' ?>" placeholder="e.g. Bangalore"
                            maxlength="50" class="form-control">
                        <?php if (isset($errors['city'])): ?>
                            <small style="color:red"><?= $errors['city'] ?></small>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="margin-top: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Full Address <span style="color:red">*</span></label>
                        <textarea name="address" class="form-control" rows="3" maxlength="200"
                            placeholder="Complete postal address"><?= $old['address'] ?? '' ?></textarea>
                        <?php if (isset($errors['address'])): ?>
                            <small style="color:red"><?= $errors['address'] ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column: Settings -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">

            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Configuration</h3>


                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= ($old['status'] ?? '') == 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($old['status'] ?? '') == 'inactive' ? 'selected' : '' ?>>Inactive
                        </option>
                        <option value="maintenance" <?= ($old['status'] ?? '') == 'maintenance' ? 'selected' : '' ?>>Under
                            Maintenance</option>
                    </select>
                    <?php if (isset($errors['status'])): ?>
                        <small style="color:red"><?= $errors['status'] ?></small>
                    <?php endif; ?>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Measurement Unit Mode</label>
                    <select name="measurement_mode" class="form-select">
                        <option value="CMS" <?= ($old['measurement_mode'] ?? 'CMS') == 'CMS' ? 'selected' : '' ?>>CMS</option>
                        <option value="Inches" <?= ($old['measurement_mode'] ?? 'CMS') == 'Inches' ? 'selected' : '' ?>>Inches</option>
                    </select>
                    <?php if (isset($errors['measurement_mode'])): ?>
                        <small style="color:red"><?= $errors['measurement_mode'] ?></small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label">Color Theme <span style="color:red">*</span></label>
                    <div style="display:flex; gap:10px;">

                        <label>
                            <input type="radio" name="color_theme" value="#1e293b" <?= ($old['color_theme'] ?? '') == '#1e293b' ? 'checked' : '' ?>>
                            <span
                                style="background:#1e293b; width:24px; height:24px; display:inline-block; border-radius:50%;"></span>
                        </label>

                        <label>
                            <input type="radio" name="color_theme" value="#4f46e5" <?= ($old['color_theme'] ?? '') == '#4f46e5' ? 'checked' : '' ?>>
                            <span
                                style="background:#4f46e5; width:24px; height:24px; display:inline-block; border-radius:50%;"></span>
                        </label>

                        <label>
                            <input type="radio" name="color_theme" value="#059669" <?= ($old['color_theme'] ?? '') == '#059669' ? 'checked' : '' ?>>
                            <span
                                style="background:#059669; width:24px; height:24px; display:inline-block; border-radius:50%;"></span>
                        </label>

                        <label>
                            <input type="radio" name="color_theme" value="#b91c1c" <?= ($old['color_theme'] ?? '') == '#b91c1c' ? 'checked' : '' ?>>
                            <span
                                style="background:#b91c1c; width:24px; height:24px; display:inline-block; border-radius:50%;"></span>
                        </label>

                    </div>

                    <?php if (isset($errors['color_theme'])): ?>
                        <small style="color:red"><?= $errors['color_theme'] ?></small>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Actions -->
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Actions</h3>
                <button type="submit" class="btn btn-primary w-full"
                    style="justify-content: center; width: 100%; margin-bottom: 1rem;"><?= isset($id) ? 'Update Branch' : 'Save Branch' ?></button>
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
<script>
    function resetForm() {
        window.location.href = window.location.pathname + window.location.search;
    }
</script>

<?php include 'includes/footer.php'; ?>