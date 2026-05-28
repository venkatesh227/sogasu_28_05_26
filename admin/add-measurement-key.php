<?php
session_start();
$user_id = $_SESSION['user_id'] ?? 1;
$pageTitle = isset($_GET['id'])
    ? "Edit Helper Key - Sogasu"
    : "Add Helper Key - Sogasu";
$activePage = "measurement";
include '../includes/db.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['status'])) {
    $stmt = $pdo->prepare("UPDATE measurement_keys SET status=? WHERE id=?");
    echo json_encode([
        'success' => $stmt->execute([$_POST['status'], $_POST['id']])
    ]);
    exit;
}
$stmt = $pdo->query("SELECT * FROM measurement_keys WHERE is_deleted = 0 ORDER BY id DESC");
$keys = $stmt->fetchAll();
$editData = null;

if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM measurement_keys WHERE id=?");
    $stmt->execute([$_GET['id']]);
    $editData = $stmt->fetch();
}
$errors = [];

if (isset($_POST['submit'])) {

    $key_name   = trim($_POST['key_name']);
    $description = trim($_POST['description']);
    $input_type = $_POST['input_type'];
    $status     = $_POST['status'];

    // VALIDATIONS
    if ($key_name == "") {
        $errors['key_name'] = "* Key Name is required";
    }

    if ($description == "") {
        $errors['description'] = "* Description is required";
    }

    if ($input_type == "") {
        $errors['input_type'] = "* Input Type is required";
    }

    if ($status == "") {
        $errors['status'] = "* Status is required";
    }
    // DUPLICATE CHECK
    if (empty($errors)) {
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare("SELECT id FROM measurement_keys WHERE key_name=? AND id!=?");
            $stmt->execute([$key_name, $_GET['id']]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM measurement_keys WHERE key_name=?");
            $stmt->execute([$key_name]);
        }

        if ($stmt->rowCount() > 0) {
            $errors['key_name'] = "* Key already exists";
        }
    }


        // INSERT
        if (empty($errors)) {

            date_default_timezone_set('Asia/Kolkata');
            $created_at = date("Y-m-d H:i:s");

            if (isset($_GET['id'])) {
                $stmt = $pdo->prepare("UPDATE measurement_keys SET key_name=?, description=?, input_type=?, status=?, updated_at=?, updated_by=? WHERE id=?");

                $stmt->execute([
                    $key_name,
                    $description,
                    $input_type,
                    $status,
                    date("Y-m-d H:i:s"),
                    $user_id,
                    $_GET['id']
                ]);

            header("Location: add-measurement-key.php?updated=1");
            exit;
            } else {
                // INSERT 
                $stmt = $pdo->prepare("INSERT INTO measurement_keys 
                (key_name, description, input_type, status, created_at, created_by, is_deleted)
                VALUES (?, ?, ?, ?, ?, ?, 0)");

                $stmt->execute([
                    $key_name,
                    $description,
                    $input_type,
                    $status,
                    $created_at,
                    $user_id
                ]);

            header("Location: add-measurement-key.php?created=1");
            exit;
        }
    }
}
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="margin-bottom: 1rem;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">
                    <?= isset($_GET['id']) ? 'Edit Helper Key' : 'Add Helper Key' ?>
                </h2>
                <p class="text-muted">
                    <?= isset($_GET['id'])
                        ? 'Update the measurement key details'
                        : 'Create a new measurement key for global use' ?>
                </p>
            </div>

            <button class="btn" onclick="window.location.href='measurement-reference.php'"
                style="background: white; border: 1px solid #e2e8f0; color: #64748b;">
                <i class="ri-arrow-left-line"></i> Back
            </button>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data" style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
        <!-- Left Column: Key Details -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">

            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Key Information</h3>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Key Name</label>
                    <input type="text" name="key_name" class="form-control" placeholder="e.g. Knee Round" value="<?= $_POST['key_name'] ?? $editData['key_name'] ?? '' ?>">
                    <span class="text-red"><?= $errors['key_name'] ?? '' ?></span>
                </div>

                <div class="form-group">
                    <label class="form-label">Description / Instructions</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Explain how to measure this..."><?= $_POST['description'] ?? $editData['description'] ?? '' ?></textarea>
                    <span class="text-red"><?= $errors['description'] ?? '' ?></span>
                </div>
            </div>


        </div>

        <!-- Right Column: Settings -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">

            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Settings</h3>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <select name="input_type">
                        <option value="">Select</option>
                        <option value="text" <?= (($_POST['input_type'] ?? $editData['input_type'] ?? '') == 'text') ? 'selected' : '' ?>>Number / Text</option>
                        <option value="select" <?= (($_POST['input_type'] ?? $editData['input_type'] ?? '') == 'select') ? 'selected' : '' ?>>Dropdown</option>
                        <option value="checkbox" <?= (($_POST['input_type'] ?? $editData['input_type'] ?? '') == 'checkbox') ? 'selected' : '' ?>>Checkbox</option>
                    </select>
                    <span class="text-red"><?= $errors['input_type'] ?? '' ?></span>
                </div>

                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status">
                        <option value="">Select</option>
                        <option value="active" <?= (($_POST['status'] ?? $editData['status'] ?? '') == 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= (($_POST['status'] ?? $editData['status'] ?? '') == 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                    <span class="text-red"><?= $errors['status'] ?? '' ?></span>
                </div>
            </div>

            <!-- Actions -->
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Actions</h3>
                <button class="btn btn-primary w-full" type="submit" name="submit" style="justify-content: center; width: 100%; margin-bottom: 1rem;"><?= isset($_GET['id']) ? 'Update Key' : 'Create Key' ?></button>
                <button type="button" onclick="window.location.href='add-measurement-key.php'" class="btn w-full" style="justify-content: center; width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b;">Cancel</button>
            </div>
        </div>
    </form>
    <div style="margin-top:2rem; background:white; border:1px solid #e2e8f0; padding:1.5rem; border-radius:8px;">
        <h3 style="font-size:1.1rem; font-weight:600; margin-bottom:1rem;">Created Keys</h3>
<table id="measurementKeysTable" style="width:100%; text-align:left;">
                <thead>
                <tr style="border-bottom:2px solid #f1f5f9; font-size:0.85rem; color:#64748b;">
                    <th style="padding:10px;">Key Name</th>
                    <th style="padding:10px;">Description</th>
                    <th style="padding:10px;">Input Type</th>
                    <th style="padding:10px;">Status</th>
                    <th style="padding:10px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($keys)): ?>
                    <?php foreach ($keys as $k): ?>
                        <tr style="border-bottom:1px solid #f1f5f9;">


                            <td style="padding:10px; font-weight:600;">
                                <?= $k['key_name'] ?>
                            </td>

                            <td style="padding:10px; color:#64748b;">
                                <?= $k['description'] ?>
                            </td>

                            <td style="padding:10px;">
                                <?= $k['input_type'] ?>
                            </td>

                            <td style="padding:10px;">
    <label class="toggle-switch">
        <input type="checkbox"
            <?= $k['status'] == 'active' ? 'checked' : '' ?>
            onchange="toggleKeyStatus(this, <?= $k['id']; ?>)">
        <span class="toggle-slider"></span>
    </label>
</td>
                            <td style="padding:10px;">
                                <!-- EDIT -->
                                <a href="add-measurement-key.php?id=<?= $k['id'] ?>"
                                    style="color:#64748b; margin-right:12px; font-size:18px;">
                                    <i class="ri-pencil-line"></i>
                                </a>
                                <!-- DELETE -->
                                <a href="javascript:void(0);"
                                    onclick="deleteKey(<?= $k['id'] ?>)"
                                    style="color:#dc2626; font-size:18px;">
                                    <i class="ri-delete-bin-line"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding:20px;">No Data Found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
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
    function deleteKey(id) {
        Swal.fire({
            title: "Are you sure?",
            text: "This key will be deleted!",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#dc2626",
            cancelButtonColor: "#6b7280",
            confirmButtonText: "Yes, delete it!"
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "delete-measurement-key.php?id=" + id;
            }
        });
    }
</script>
<?php if (isset($_GET['deleted'])): ?>
    <script>
        Swal.fire({
            title: "Deleted!",
            text: "Key deleted successfully",
            icon: "success",
            confirmButtonColor: "#6366f1"
        }).then(() => {
            window.history.replaceState(null, null, "add-measurement-key.php");
        });
    </script>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if (isset($success)): ?>
    <script>
        Swal.fire({
            title: "Success",
            text: "Key created successfully",
            icon: "success",
            confirmButtonColor: "#6366f1"
        });
    </script>
<?php endif; ?>
<?php if (isset($_GET['created'])): ?>
    <script>
        Swal.fire("Success", "Key created successfully", "success")
            .then(() => {
                window.history.replaceState(null, null, "add-measurement-key.php");
            });
    </script>
<?php endif; ?>

<?php if (isset($_GET['updated'])): ?>
    <script>
        Swal.fire("Success", "Key updated successfully", "success")
            .then(() => {
                window.history.replaceState(null, null, "add-measurement-key.php");
            });
    </script>
<?php endif; ?>
<?php include __DIR__ . '/includes/datatable.php'; ?>

<script>

initializeDataTable(
    'measurementKeysTable',
    'Measurement Keys',
    3
);

</script>
<script>
function toggleKeyStatus(el, id) {
    const status = el.checked ? 'active' : 'inactive';

    fetch('add-measurement-key.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'id=' + id + '&status=' + status
    })
    .then(res => res.json()) // ✅ directly parse JSON
    .then(data => {

        if (data.success) {

            Swal.fire({
                icon: 'success',
                title: status === 'active' ? 'Activated' : 'Deactivated',
                text: status === 'active'
                    ? 'Activated successfully'
                    : 'Deactivated successfully',
                timer: 1200,
                showConfirmButton: false
            });

        } else {
            el.checked = !el.checked;

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Update failed'
            });
        }

    })
    .catch(() => {
        el.checked = !el.checked;

        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Something went wrong'
        });
    });
}
</script>
<?php include 'includes/footer.php'; ?>