<?php
session_start();
include '../includes/db.php';

$pageTitle = "Inventory Categories - Sogasu";
$activePage = "inventory-categories";

/* TOGGLE STATUS */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    $stmt = $pdo->prepare("
        UPDATE inventory_categories
        SET status = ?
        WHERE id = ?
    ");

    echo json_encode([
        'success' => $stmt->execute([
            $_POST['status'],
            $_POST['toggle_id']
        ])
    ]);
    exit;
}

/* DELETE */
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("
        UPDATE inventory_categories
        SET is_deleted = 1
        WHERE id = ?
    ");

    $stmt->execute([$_GET['delete']]);
    $_SESSION['success'] = "deleted";

    header("Location: inventory-categories.php");
    exit;
}

$errors = [];

/* ADD / EDIT */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['toggle_id'])) {
    $id = $_POST['id'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');

    if ($name == '') {
        $errors['name'] = "Category Name is required";
    }

    // Auto-generate code if empty
    if ($code == '' && $name != '') {
        $code = strtolower(preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $name)));
    }

    if ($code == '') {
        $errors['code'] = "Category Code is required";
    }

    // Check unique constraints
    if (empty($errors)) {
        if ($id != '') {
            // Check unique for name
            $chk = $pdo->prepare("SELECT COUNT(*) FROM inventory_categories WHERE name = ? AND id != ? AND is_deleted = 0");
            $chk->execute([$name, $id]);
            if ($chk->fetchColumn() > 0) {
                $errors['name'] = "Category name already exists";
            }

            // Check unique for code
            $chk2 = $pdo->prepare("SELECT COUNT(*) FROM inventory_categories WHERE code = ? AND id != ? AND is_deleted = 0");
            $chk2->execute([$code, $id]);
            if ($chk2->fetchColumn() > 0) {
                $errors['code'] = "Category code already exists";
            }
        } else {
            // Check unique for name
            $chk = $pdo->prepare("SELECT COUNT(*) FROM inventory_categories WHERE name = ? AND is_deleted = 0");
            $chk->execute([$name]);
            if ($chk->fetchColumn() > 0) {
                $errors['name'] = "Category name already exists";
            }

            // Check unique for code
            $chk2 = $pdo->prepare("SELECT COUNT(*) FROM inventory_categories WHERE code = ? AND is_deleted = 0");
            $chk2->execute([$code]);
            if ($chk2->fetchColumn() > 0) {
                $errors['code'] = "Category code already exists";
            }
        }
    }

    if (empty($errors)) {
        if ($id != '') {
            $stmt = $pdo->prepare("
                UPDATE inventory_categories
                SET name = ?, code = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $code, $id]);
            $_SESSION['success'] = "updated";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO inventory_categories
                (name, code, status, created_at, is_deleted)
                VALUES (?, ?, 1, NOW(), 0)
            ");
            $stmt->execute([$name, $code]);
            $_SESSION['success'] = "added";
        }

        header("Location: inventory-categories.php");
        exit;
    }
}

/* FETCH ALL ACTIVE */
$stmt = $pdo->query("
    SELECT *
    FROM inventory_categories
    WHERE is_deleted = 0
    ORDER BY id DESC
");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="padding:1rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
            <div>
                <h2 style="font-size:1.5rem; font-weight:700; color:#1e293b;">Inventory Categories</h2>
                <p class="text-muted">Manage stock item categories for raw materials and assets.</p>
            </div>
            <button class="btn btn-primary" onclick="openModal()" style="background: #4f46e5; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; color: white; display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                <i class="ri-add-line"></i> Add Category
            </button>
        </div>

        <div style="background:white; border:1px solid #e2e8f0; border-radius:8px; padding:1.5rem;">
            <table id="categoriesTable" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="border-bottom:2px solid #f1f5f9; color: #64748b; font-size: 0.875rem; text-transform: uppercase;">
                        <th style="padding:1rem; text-align: left;">S.No</th>
                        <th style="padding:1rem; text-align: left;">Category Name</th>
                        <th style="padding:1rem; text-align: left;">System Code (Slug)</th>
                        <th style="padding:1rem; text-align: left;">Status</th>
                        <th style="padding:1rem; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($categories)): ?>
                        <?php $i = 1; ?>
                        <?php foreach($categories as $category): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding:1rem; font-weight: 500; color: #64748b;"><?= $i++ ?></td>
                            <td style="padding:1rem; font-weight: 600; color: #1e293b;">
                                <?= htmlspecialchars($category['name']) ?>
                            </td>
                            <td style="padding:1rem;">
                                <span style="font-weight: 600; color: #475569; background: #f1f5f9; padding: 4px 10px; border-radius: 8px; font-size: 0.8rem;">
                                    <?= htmlspecialchars($category['code']) ?>
                                </span>
                            </td>
                            <td style="padding:1rem;">
                                <label class="toggle-switch">
                                    <input type="checkbox" <?= $category['status'] == 1 ? 'checked' : '' ?> onchange="toggleStatus(this, <?= $category['id'] ?>)">
                                    <span class="toggle-slider"></span>
                                </label>
                            </td>
                            <td style="padding:1rem; text-align:right;">
                                <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                    <button class="btn-icon-p edit-btn" style="color:#4f46e5; border: none; background: transparent; cursor: pointer;"
                                            data-id="<?= $category['id'] ?>"
                                            data-name="<?= htmlspecialchars($category['name'], ENT_QUOTES) ?>"
                                            data-code="<?= htmlspecialchars($category['code'], ENT_QUOTES) ?>"
                                            title="Edit Category">
                                        <i class="ri-edit-line"></i>
                                    </button>
                                    <button class="btn-icon-p" style="color:#ef4444; border: none; background: transparent; cursor: pointer;"
                                            onclick="deleteCategory(<?= $category['id'] ?>)"
                                            title="Delete Category">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- MODAL -->
<div id="categoryModal" class="modal">
    <div class="modal-card" style="width:450px;">
        <div style="background:#4f46e5; color:white; padding:1.25rem; display:flex; justify-content:space-between; align-items:center;">
            <h3 id="modalTitle" style="margin:0; font-size: 1.15rem; font-weight: 600;">Add Inventory Category</h3>
            <i class="ri-close-line" style="cursor:pointer; font-size:1.5rem;" onclick="closeModal()"></i>
        </div>
        <form method="POST" novalidate>
            <div style="padding:1.5rem; display: flex; flex-direction: column; gap: 1.25rem;">
                <input type="hidden" name="id" id="category_id">

                <div class="form-group">
                    <label class="form-label">Category Name</label>
                    <input type="text" name="name" id="category_name" class="form-control" placeholder="e.g. Buttons & Zips" value="<?= htmlspecialchars($name ?? '') ?>">
                    <?php if(isset($errors['name'])): ?>
                        <small style="color:#ef4444; font-weight: 500;"><?= $errors['name'] ?></small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label">System Code / Slug (Optional)</label>
                    <input type="text" name="code" id="category_code" class="form-control" placeholder="e.g. buttons-zips" value="<?= htmlspecialchars($code ?? '') ?>">
                    <small style="color:#64748b; font-size: 0.78rem;">Used in database records. If left blank, it will be auto-generated from the name.</small>
                    <?php if(isset($errors['code'])): ?>
                        <br><small style="color:#ef4444; font-weight: 500;"><?= $errors['code'] ?></small>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary" style="background: #4f46e5; border: none; padding: 12px; border-radius: 8px; color: white; font-weight: 600; width:100%; margin-top: 0.5rem; cursor: pointer;">
                    Save Category
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.btn-icon-p {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #f1f5f9;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 1.1rem;
}
.btn-icon-p:hover {
    background: white;
    transform: translateY(-2px);
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    border-color: #cbd5e1;
}

.modal {
    display:none;
    position:fixed;
    inset:0;
    background:rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    z-index:1000;
    justify-content:center;
    align-items:center;
}
.modal-card {
    background:white;
    border-radius:12px;
    overflow:hidden;
    box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
    animation: modalFadeIn 0.2s ease-out;
}

@keyframes modalFadeIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.form-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #334155;
}
.form-control {
    padding: 0.75rem;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 0.95rem;
    width: 100%;
    outline: none;
    transition: border-color 0.2s;
}
.form-control:focus {
    border-color: #4f46e5;
}

/* TOGGLE SLIDER */
.toggle-switch {
    position:relative;
    display:inline-block;
    width:40px;
    height:20px;
}
.toggle-switch input {
    opacity:0;
    width:0;
    height:0;
}
.toggle-slider {
    position:absolute;
    inset:0;
    background:#cbd5e1;
    border-radius:30px;
    transition:.2s;
    cursor:pointer;
}
.toggle-slider:before {
    position:absolute;
    content:"";
    width:14px;
    height:14px;
    left:3px;
    top:3px;
    background:white;
    border-radius:50%;
    transition:.2s;
}
.toggle-switch input:checked + .toggle-slider {
    background:#22c55e;
}
.toggle-switch input:checked + .toggle-slider:before {
    transform:translateX(20px);
}
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function openModal() {
    document.getElementById('modalTitle').innerText = 'Add Inventory Category';
    document.getElementById('category_id').value = '';
    document.getElementById('category_name').value = '';
    document.getElementById('category_code').value = '';
    document.getElementById('categoryModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('categoryModal').style.display = 'none';
}

function editCategory(id, name, code) {
    document.getElementById('modalTitle').innerText = 'Edit Inventory Category';
    document.getElementById('category_id').value = id;
    document.getElementById('category_name').value = name;
    document.getElementById('category_code').value = code;
    document.getElementById('categoryModal').style.display = 'flex';
}

document.addEventListener('click', function(e) {
    let btn = e.target.closest('.edit-btn');
    if (btn) {
        editCategory(
            btn.dataset.id,
            btn.dataset.name,
            btn.dataset.code
        );
    }
});

function deleteCategory(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: 'This category will be deleted! Existing inventory items under this category will remain, but you won\'t be able to select it for new items.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location = 'inventory-categories.php?delete=' + id;
        }
    });
}

function toggleStatus(el, id) {
    let status = el.checked ? 1 : 0;
    fetch('inventory-categories.php', {
        method:'POST',
        headers:{
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'toggle_id=' + id + '&status=' + status
    })
    .then(res => res.json())
    .then(data => {
        if(data.success){
            Swal.fire({
                icon:'success',
                title:'Status Updated',
                timer:1000,
                showConfirmButton:false
            });
        }
    });
}
</script>

<?php if(!empty($errors)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('categoryModal').style.display = 'flex';
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/datatable.php'; ?>
<script>
$(document).ready(function() {
    initializeDataTable('categoriesTable', 'Inventory Categories', 4);
});
</script>

<?php if (!empty($_SESSION['success'])): ?>
<?php
$successMessage = '';
if ($_SESSION['success'] == 'added') {
    $successMessage = 'Inventory Category created successfully';
} elseif ($_SESSION['success'] == 'updated') {
    $successMessage = 'Inventory Category updated successfully';
} else {
    $successMessage = 'Inventory Category deleted successfully';
}
?>
<script>
window.onload = function () {
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: '<?= $successMessage ?>',
        confirmButtonText: 'OK',
        confirmButtonColor: '#4f46e5'
    });
};
</script>
<?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
