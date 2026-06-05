<?php
session_start();
include '../includes/db.php';

$pageTitle = "Asset Categories - Sogasu";
$activePage = "asset-categories";

/* TOGGLE STATUS */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {

    $stmt = $pdo->prepare("
        UPDATE asset_categories
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
        UPDATE asset_categories
        SET is_deleted = 1
        WHERE id = ?
    ");

    $stmt->execute([$_GET['delete']]);

    $_SESSION['success'] = "deleted";

    header("Location: asset-categories.php");
    exit;
}

$errors = [];

/* ADD / EDIT */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['toggle_id'])) {

    $id = $_POST['id'] ?? '';

    $name = trim($_POST['name'] ?? '');
    $type = trim($_POST['type'] ?? '');

    if ($name == '') {
        $errors['name'] = "Category Name field is required";
    }

    if ($type == '') {
        $errors['type'] = "Category Type field is required";
    }

    if (empty($errors)) {

        if ($id != '') {

            $stmt = $pdo->prepare("
                UPDATE asset_categories
                SET name = ?, type = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $name,
                $type,
                $id
            ]);

            $_SESSION['success'] = "updated";

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO asset_categories
                (name, type, status, created_at, is_deleted)
                VALUES (?, ?, 1, NOW(), 0)
            ");

            $stmt->execute([
                $name,
                $type
            ]);

            $_SESSION['success'] = "added";
        }

        header("Location: asset-categories.php");
        exit;
    }
}

/* FETCH */
$stmt = $pdo->query("
    SELECT *
    FROM asset_categories
    WHERE is_deleted = 0
    ORDER BY id DESC
");

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<main class="main-content">

    <?php include 'includes/topbar.php'; ?>

    <div style="padding:1rem;">

        <div style="display:flex;
                    justify-content:space-between;
                    align-items:center;
                    margin-bottom:2rem;">

            <div>

                <h2 style="font-size:1.5rem;
                           font-weight:700;
                           color:#1e293b;">

                    Asset Categories

                </h2>

                <p class="text-muted">
                    Group your assets into logical categories
                </p>

            </div>

            <button class="btn btn-primary"
                    onclick="openModal()">

                <i class="ri-add-line"></i>
                Add Category

            </button>

        </div>

        <div style="background:white;
                    border:1px solid #e2e8f0;
                    border-radius:8px;
                    padding:1rem;">

            <table id="categoriesTable"
                   style="width:100%;
                          border-collapse:collapse;">

                <thead>

                    <tr style="border-bottom:1px solid #e2e8f0;">

                        <th style="padding:1rem;">
                            S.No
                        </th>

                        <th style="padding:1rem;">
                            Category Name
                        </th>

                        <th style="padding:1rem;">
                            Category Type
                        </th>

                        <th style="padding:1rem;">
                            Status
                        </th>

                        <th style="padding:1rem;text-align:right;">
                            Actions
                        </th>

                    </tr>

                </thead>

                <tbody>

                    <?php if(!empty($categories)): ?>

                        <?php $i = 1; ?>

                        <?php foreach($categories as $category): ?>

                        <tr>

                            <td style="padding:1rem;">
                                <?= $i++ ?>
                            </td>

                            <td style="padding:1rem;">
                                <?= htmlspecialchars($category['name']) ?>
                            </td>

                            <td style="padding:1rem;">
                                <?= htmlspecialchars($category['type']) ?>
                            </td>

                            <td style="padding:1rem;">

                                <label class="toggle-switch">

                                    <input type="checkbox"
                                           <?= $category['status'] == 1 ? 'checked' : '' ?>
                                           onchange="toggleStatus(this, <?= $category['id'] ?>)">

                                    <span class="toggle-slider"></span>

                                </label>

                            </td>

                            <td style="padding:1rem;text-align:right;">

                              <button class="btn-icon edit-btn"
        style="color:#4f46e5;"
        data-id="<?= $category['id'] ?>"
        data-name="<?= htmlspecialchars($category['name'], ENT_QUOTES) ?>"
        data-type="<?= htmlspecialchars($category['type'], ENT_QUOTES) ?>">

    <i class="ri-edit-line"></i>

</button>

                                <button class="btn-icon"
                                        style="color:#ef4444;"
                                        onclick="deleteCategory(<?= $category['id'] ?>)">

                                    <i class="ri-delete-bin-line"></i>

                                </button>

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

    <div class="modal-card"
         style="width:400px;">

        <div style="background:var(--primary);
                    color:white;
                    padding:1rem;
                    display:flex;
                    justify-content:space-between;
                    align-items:center;">

            <h3 id="modalTitle" style="margin:0;">
                Add Asset Category
            </h3>

            <i class="ri-close-line"
               style="cursor:pointer;font-size:1.5rem;"
               onclick="closeModal()"></i>

        </div>

        <form method="POST" novalidate>

            <div style="padding:1.5rem;">

                <input type="hidden"
                       name="id"
                       id="category_id">

                <div class="form-group"
                     style="margin-bottom:1rem;">

                    <label class="form-label">
                        Category Name
                    </label>

                    <input type="text"
                           name="name"
                           id="category_name"
                           class="form-control"
                           placeholder="e.g. Machines"
                           value="<?= $name ?? '' ?>">

                    <?php if(isset($errors['name'])): ?>

                        <small style="color:red;">
                            <?= $errors['name'] ?>
                        </small>

                    <?php endif; ?>

                </div>

                <div class="form-group">

                    <label class="form-label">
                        Category Type
                    </label>

                <select name="type"
        id="category_type"
        class="form-control">

    <option value="">
        Select Category Type
    </option>

    <option value="Machine"
        <?= (($type ?? '') == 'Machine') ? 'selected' : '' ?>>
        Machine
    </option>

    <option value="Material"
        <?= (($type ?? '') == 'Material') ? 'selected' : '' ?>>
        Material
    </option>

    <option value="Electronics"
        <?= (($type ?? '') == 'Electronics') ? 'selected' : '' ?>>
        Electronics
    </option>

    <option value="Other"
        <?= (($type ?? '') == 'Other') ? 'selected' : '' ?>>
        Other
    </option>

</select>

                    <?php if(isset($errors['type'])): ?>

                        <small style="color:red;">
                            <?= $errors['type'] ?>
                        </small>

                    <?php endif; ?>

                </div>

                <button type="submit"
                        class="btn btn-primary"
                        style="width:100%;
                               margin-top:1.5rem;">

                    Save Category

                </button>

            </div>

        </form>

    </div>

</div>

<style>

.btn-icon{
    background:none;
    border:none;
    cursor:pointer;
    font-size:1.1rem;
}

.modal{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,0.5);
    z-index:1000;
    justify-content:center;
    align-items:center;
}

.modal-card{
    background:white;
    border-radius:12px;
    overflow:hidden;
}

/* TOGGLE */

.toggle-switch{
    position:relative;
    display:inline-block;
    width:42px;
    height:22px;
}

.toggle-switch input{
    opacity:0;
    width:0;
    height:0;
}

.toggle-slider{
    position:absolute;
    inset:0;
    background:#d1d5db;
    border-radius:30px;
    transition:.3s;
    cursor:pointer;
}

.toggle-slider:before{
    position:absolute;
    content:"";
    width:16px;
    height:16px;
    left:3px;
    top:3px;
    background:white;
    border-radius:50%;
    transition:.3s;
}

.toggle-switch input:checked + .toggle-slider{
    background:#22c55e;
}

.toggle-switch input:checked + .toggle-slider:before{
    transform:translateX(20px);
}

</style>




<script>


function openModal() {

    document.getElementById('modalTitle').innerText =
        'Add Asset Category';

    document.getElementById('category_id').value = '';

    document.getElementById('category_name').value = '';

    document.getElementById('category_type').value = '';

    document.getElementById('categoryModal').style.display =
        'flex';

}

function closeModal() {

    document.getElementById('categoryModal').style.display =
        'none';

}

function editCategory(id, name, type) {

    document.getElementById('modalTitle').innerText =
        'Edit Asset Category';

    document.getElementById('category_id').value = id;

    document.getElementById('category_name').value = name;

    document.getElementById('category_type').value = type;

    document.getElementById('categoryModal').style.display =
        'flex';

}
document.addEventListener('click', function(e){

    let btn = e.target.closest('.edit-btn');

    if(btn){

        editCategory(
            btn.dataset.id,
            btn.dataset.name,
            btn.dataset.type
        );

    }

});
function deleteCategory(id) {

    Swal.fire({
        title: 'Are you sure?',
        text: 'Category will be deleted!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626'
    }).then((result) => {

        if (result.isConfirmed) {

            window.location =
                'asset-categories.php?delete=' + id;

        }

    });

}

function toggleStatus(el, id) {

    let status = el.checked ? 1 : 0;

    fetch('asset-categories.php', {

        method:'POST',

        headers:{
            'Content-Type':
            'application/x-www-form-urlencoded'
        },

        body:
            'toggle_id=' + id +
            '&status=' + status

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

    document.getElementById('categoryModal').style.display =
        'flex';

});

</script>

<?php endif; ?>

<?php include __DIR__ . '/includes/datatable.php'; ?>

<script>

initializeDataTable(
    'categoriesTable',
    'Asset Categories',
    3
);

</script>
<?php if (!empty($_SESSION['success'])): ?>

<?php

$successMessage = '';

if ($_SESSION['success'] == 'added') {

    $successMessage = 'Asset Categories created successfully';

} elseif ($_SESSION['success'] == 'updated') {

    $successMessage = 'Asset Categories updated successfully';

} else {

    $successMessage = 'Asset Categories deleted successfully';

}

?>

<script>

window.onload = function () {

    Swal.fire({

        icon: 'success',

        title: 'Success',

        text: '<?= $successMessage ?>',

        confirmButtonText: 'OK',

        confirmButtonColor: '#6C63FF'

    });

};

</script>

<?php unset($_SESSION['success']); ?>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>
