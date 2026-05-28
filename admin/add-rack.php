<?php
session_start();
include '../includes/db.php';

$id = $_GET['id'] ?? null;
$rack = ['rack_name' => '', 'description' => '', 'status' => 'Available'];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM racks WHERE id = ?");
    $stmt->execute([$id]);
    $rack = $stmt->fetch();
    if (!$rack) {
        header("Location: racks.php");
        exit();
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $rack_name = trim($_POST['rack_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? '';

    // VALIDATION
    if ($rack_name === '') {
        $errors['rack_name'] = "Rack Name is required";
    }

    if ($status === '') {
        $errors['status'] = "Status is required";
    }

    // SAVE ONLY IF NO ERRORS
    if (empty($errors)) {

        if ($id) {
            $stmt = $pdo->prepare("UPDATE racks SET rack_name = ?, description = ?, status = ? WHERE id = ?");
            $stmt->execute([$rack_name, $description, $status, $id]);
            $_SESSION['success'] = "updated";
        } else {
            $stmt = $pdo->prepare("INSERT INTO racks (rack_name, description, status) VALUES (?, ?, ?)");
            $stmt->execute([$rack_name, $description, $status]);
            $_SESSION['success'] = "added";
        }

        header("Location: racks.php");
        exit();
    }
}

$pageTitle = ($id ? "Edit Rack" : "Add Rack") . " - Sogasu";
$activePage = "racks";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div >
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;"><?= $id ? 'Edit Rack' : 'Add New Rack' ?></h2>
                <p style="color: #64748b; margin-top: 0.25rem;">Define a storage location for finished or in-progress garments</p>
            </div>
            <button class="btn" onclick="window.location.href='racks.php'" style="background: white; border: 1px solid #e2e8f0; color: #64748b;">
                <i class="ri-arrow-left-line"></i> Back
            </button>
        </div>
    </div>

    <form method="POST" novalidate style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
        
        <!-- Left Column: Details -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Rack Details</h3>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Rack Name / Number <span style="color:red">*</span></label>
                    <input type="text" name="rack_name" class="form-control"
                        value="<?= htmlspecialchars($rack_name ?? $rack['rack_name']) ?>" placeholder="e.g. Rack A-1, Shelf 2"
                        style="border-color: <?= !empty($errors['rack_name']) ? '#dc2626' : '#cbd5e1' ?>;">
                    <?php if (!empty($errors['rack_name'])): ?>
                        <small style="color:#dc2626;"><?= $errors['rack_name'] ?></small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label">Description (Optional)</label>
                    <textarea name="description" rows="3" class="form-control" placeholder="e.g. Near the main window, for heavy garments"><?= htmlspecialchars($description ?? $rack['description']) ?></textarea>
                </div>
            </div>
        </div>

        <!-- Right Column: Settings & Actions -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Settings</h3>
                
                <div class="form-group">
                    <label class="form-label">Initial Status <span style="color:red">*</span></label>
                    <select name="status" class="form-select" style="border-color: <?= !empty($errors['status']) ? '#dc2626' : '#cbd5e1' ?>;">
                        <option value="Available" <?= ($status ?? $rack['status']) == 'Available' ? 'selected' : '' ?>>Available</option>
                        <option value="Occupied" <?= ($status ?? $rack['status']) == 'Occupied' ? 'selected' : '' ?>>Occupied</option>
                        <option value="Maintenance" <?= ($status ?? $rack['status']) == 'Maintenance' ? 'selected' : '' ?>>Maintenance</option>
                    </select>
                    <?php if (!empty($errors['status'])): ?>
                        <small style="color:#dc2626;"><?= $errors['status'] ?></small>
                    <?php endif; ?>
                </div>
            </div>

            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Actions</h3>
                <button type="submit" class="btn btn-primary w-full" style="justify-content: center; width: 100%; margin-bottom: 1rem;">
                    <?= $id ? 'Update Rack Details' : 'Create Rack' ?>
                </button>
                <button type="button" class="btn w-full" onclick="window.location.href='racks.php'" style="justify-content: center; width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b;">
                    Cancel
                </button>
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
