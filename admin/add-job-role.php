<?php
session_start();
include '../includes/db.php';
$errors = [];
$old = [];
$id = $_GET['id'] ?? null;

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM job_roles WHERE id = ? AND is_deleted = 0");
    $stmt->execute([$id]);
    $jobRole = $stmt->fetch();

    if (!$jobRole) {
        die("Job Role not found");
    }

    $old = $jobRole;
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // SANITIZE
    $old['role_name'] = trim($_POST['role_name'] ?? '');
    $old['description'] = trim($_POST['description'] ?? '');
    $old['status'] = $_POST['status'] ?? 'active';

    // ===== VALIDATIONS =====

    if ($old['role_name'] == '') {
        $errors['role_name'] = "Role name required";
    } elseif (strlen($old['role_name']) > 100) {
        $errors['role_name'] = "Max 100 characters allowed";
    }

    $validStatus = ['active', 'inactive'];

    if (!in_array($old['status'], $validStatus, true)) {
        $errors['status'] = "Invalid status";
    }

    // ===== INSERT / UPDATE =====

    if (empty($errors)) {

        if ($id) {
            // UPDATE
            $stmt = $pdo->prepare("
                UPDATE job_roles SET 
                role_name=?, description=?, status=? 
                WHERE id=?
            ");

            $stmt->execute([
                $old['role_name'],
                $old['description'],
                $old['status'],
                $id
            ]);
            $_SESSION['success'] = "updated";

        } else {
            // INSERT
            $stmt = $pdo->prepare("
                INSERT INTO job_roles 
                (role_name, description, status, is_deleted)
                VALUES (?, ?, ?, 0)
            ");

            $stmt->execute([
                $old['role_name'],
                $old['description'],
                $old['status']
            ]);

            $_SESSION['success'] = "added";
        }

        header("Location: job-roles.php");
        exit;
    }
}
$pageTitle = ($id ? "Edit" : "Add New") . " Job Role - Sogasu";
$activePage = "job-roles";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="margin-bottom: 1rem;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;"><?= $id ? 'Edit' : 'Add New' ?> Job Role</h2>
                <p class="text-muted"><?= $id ? 'Update existing job role' : 'Create a new job role' ?></p>
            </div>
            <button class="btn" onclick="history.back()"
                style="background: white; border: 1px solid #e2e8f0; color: #64748b;">
                <i class="ri-arrow-left-line"></i> Back
            </button>
        </div>
    </div>

    <form method="POST" style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">

        <!-- Left Column: Info -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Role Details</h3>
                <div class="form-group">
                    <label class="form-label">Role Name <span style="color:red">*</span></label>
                    <input type="text" name="role_name" class="form-control" placeholder="e.g. Senior Cutter"
                        maxlength="100" value="<?= htmlspecialchars($old['role_name'] ?? '') ?>">
                    <?php if (isset($errors['role_name'])): ?>
                        <small style="color:red"><?= $errors['role_name'] ?></small>
                    <?php endif; ?>
                </div>
                <div class="form-group" style="margin-top: 1rem;">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" maxlength="400"
                        placeholder="Brief description of the role's responsibilities"><?= htmlspecialchars($old['description'] ?? '') ?></textarea>
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
                        <option value="inactive" <?= ($old['status'] ?? '') == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                    <?php if (isset($errors['status'])): ?>
                        <small style="color:red"><?= $errors['status'] ?></small>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Actions -->
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Actions</h3>
                <button type="submit" class="btn btn-primary w-full"
                    style="justify-content: center; width: 100%; margin-bottom: 1rem;"><?= $id ? 'Update Job Role' : 'Save Job Role' ?></button>
                <button type="reset" class="btn w-full" onclick="history.back()"
                    style="justify-content: center; width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b;">Cancel</button>
            </div>
        </div>

    </form>
</main>

<style>
    .form-group { display: flex; flex-direction: column; gap: 0.5rem; }
    .form-label { font-size: 0.875rem; font-weight: 500; color: #334155; }
    .form-control, .form-select {
        padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem; width: 100%; outline: none; transition: border-color 0.2s; font-family: inherit;
    }
    .form-control:focus, .form-select:focus { border-color: var(--primary); }
</style>

<?php include 'includes/footer.php'; ?>
