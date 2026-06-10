<?php

session_start();

$pageTitle = "Expense Categories - Sogasu";
$activePage = "expense-categories";

include '../includes/db.php';
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['id'], $_POST['status'])
) {

    header('Content-Type: application/json');

    try {

        $stmt = $pdo->prepare("
            UPDATE expense_categories
            SET
                status = ?,
                updated_at = NOW()
            WHERE id = ?
            AND deleted_at IS NULL
        ");

        $success = $stmt->execute([
            $_POST['status'],
            $_POST['id']
        ]);

        echo json_encode([
            'success' => $success,
            'message' => $_POST['status'] === 'active'
                ? 'Activated successfully'
                : 'Deactivated successfully'
        ]);

    } catch (Exception $e) {

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);

    }

    exit;
}
/* =========================
   INSERT / UPDATE
========================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['save_category'])
) {

    $id = $_POST['category_id'] ?? '';

$category_name = trim($_POST['category_name'] ?? '');
$status = $_POST['status'] ?? '';

$errors = [];

if ($category_name === '') {
    $errors['category_name'] = 'Category Name is required';
}

if ($status === '') {
    $errors['status'] = 'Status is required';
}
    /* =========================
       DUPLICATE CHECK
    ========================= */

    $checkQuery = "
        SELECT id
        FROM expense_categories
        WHERE LOWER(category_name) = LOWER(?)
        AND deleted_at IS NULL
    ";

    $params = [$category_name];

    if (!empty($id)) {

        $checkQuery .= " AND id != ?";
        $params[] = $id;
    }

    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute($params);
$currentDateTime = date('Y-m-d H:i:s');

if (empty($errors) && !$checkStmt->fetch()) {

    /* ===== UPDATE ===== */

    if (!empty($id)) {

        $stmt = $pdo->prepare("
            UPDATE expense_categories
            SET
                category_name = ?,
                status = ?,
                updated_at = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $category_name,
            $status,
            $currentDateTime,
            $id
        ]);

        $_SESSION['category_success']
            = "Expense Category updated successfully";

    } else {

        /* ===== INSERT ===== */

        $stmt = $pdo->prepare("
            INSERT INTO expense_categories
            (
                category_name,
                status,
                created_at
            )
            VALUES
            (
                ?, ?, ?
            )
        ");

        $stmt->execute([
            $category_name,
            $status,
            $currentDateTime
        ]);

        $_SESSION['category_success']
            = "Expense Category created successfully";
    }

    // IMPORTANT FIX
    header("Location: expense-categories.php");
    exit;

} else {

    $_SESSION['category_error']
        = "Expense Category already exists";

    // IMPORTANT FIX
    header("Location: expense-categories.php");
    exit;
}
}

/* =========================
   FETCH CATEGORIES
========================= */

$stmt = $pdo->query("
    SELECT *
    FROM expense_categories
    WHERE deleted_at IS NULL
    ORDER BY id DESC
");

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
include 'includes/header.php';
?>

<main class="main-content">

    <?php include 'includes/topbar.php'; ?>

    <!-- PAGE HEADER -->

    <div style="display:flex;justify-content:space-between;align-items:center;">

        <div>

            <h2 style="font-size:1.5rem;font-weight:700;color:#1e293b;">

                Expense Categories

            </h2>

            <p class="text-muted">

                Manage business expense categories

            </p>

        </div>
        <button class="btn btn-primary" onclick="openAddModal()">

            <i class="ri-add-line"></i>
            Add Expense Category

        </button>

    </div>

    <!-- TABLE -->

    <div style="background:white;border:1px solid #e2e8f0;padding:1.5rem;">

        <table id="expenseCategoriesTable" style="text-align:left;">

            <thead>

                <tr style="
                    border-bottom:2px solid #f1f5f9;
                    color:#64748b;
                    font-size:0.875rem;
                    text-transform:uppercase;
                ">

                    <th style="padding:1rem;">S.NO</th>

                    <th style="padding:1rem;">Category Name</th>

                    <th style="padding:1rem;">Created Date</th>

                    <th style="padding:1rem;">Status</th>

                    <th style="padding:1rem;text-align:right;">Action</th>

                </tr>

            </thead>

            <tbody>

                <?php $i = 1; ?>

                <?php foreach ($categories as $category): ?>

                    <tr style="border-bottom:1px solid #f1f5f9;">

                        <!-- SNO -->

                        <td style="padding:1rem;">

                            <?= $i++; ?>

                        </td>

                        <!-- CATEGORY NAME -->

                        <td style="
                            padding:1rem;
                            font-weight:600;
                            color:#0f172a;
                        ">

                            <?= htmlspecialchars($category['category_name']); ?>

                        </td>

                        <!-- CREATED DATE -->

                        <td style="
                            padding:1rem;
                            color:#64748b;
                        ">

                            <?= date('d M Y', strtotime($category['created_at'])); ?>

                        </td>

                        <!-- STATUS -->

                        <td style="padding:1rem;">

                            <label class="toggle-switch">

                                <input
                                type="checkbox"
                                <?= strtolower(trim($category['status'])) == 'active' ? 'checked' : '' ?>
                                onchange="toggleStatus(this, <?= $category['id']; ?>)">

                                <span class="toggle-slider"></span>

                            </label>

                        </td>

                        <!-- ACTIONS -->

                        <td style="padding:1rem;text-align:right;">

                            <!-- EDIT -->

                            <button onclick='openEditModal(
                                    <?= $category["id"] ?>,
                                    <?= json_encode($category["category_name"]) ?>,
                                    <?= json_encode($category["status"]) ?>
                                )' style="
                                    border:none;
                                    background:transparent;
                                    color:#3b82f6;
                                    cursor:pointer;
                                    margin-right:1rem;
                                ">

                                <i class="ri-pencil-line"></i>

                            </button>

                            <!-- DELETE -->

                            <button
                                type="button"
                                style="background:none; border:none; padding:0; color:#ef4444; cursor:pointer;"
                                onclick="deleteCategory(<?= $category['id']; ?>)">

                                <i class="ri-delete-bin-line"></i>

                            </button>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</main>

<!-- STYLES -->

<style>
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 36px;
        height: 18px;
    }

    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .toggle-slider {
        position: absolute;
        cursor: pointer;
        background-color: #e5e7eb;
        border-radius: 20px;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        transition: 0.3s;
    }

    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 14px;
        width: 14px;
        left: 2px;
        top: 2px;
        background-color: white;
        border-radius: 50%;
        transition: 0.3s;
    }

    .toggle-switch input:checked+.toggle-slider {
        background-color: #22c55e;
    }

    .toggle-switch input:checked+.toggle-slider:before {
        transform: translateX(18px);
    }

    /* DATATABLE */

    .dt-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .dt-left {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .dt-right {
        display: flex;
        align-items: center;
    }

    .dataTables_length {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .dataTables_length label {
        display: flex !important;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
    }

    .dataTables_length select {
        height: 32px;
        padding: 4px 8px;
    }

    .dt-buttons {
        display: flex;
        gap: 10px;
    }

    .dataTables_filter input {
        height: 32px;
        margin-left: 5px;
    }

    .modal-input {

        width: 100%;
        height: 48px;
        border: 1px solid #dbe3ef;
        border-radius: 10px;
        padding: 0 14px;
        font-size: 0.95rem;
        color: #1e293b;
        transition: 0.2s;
        outline: none;
        background: white;
    }

    .modal-input:focus {

        border-color: #4f46e5;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
    }

    @keyframes modalFade {

        from {
            opacity: 0;
            transform: translateY(15px) scale(0.96);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
</style>

<!-- SCRIPTS -->

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<script>

    /* =========================
       DATATABLE
    ========================= */

    $(document).ready(function () {

        if ($.fn.DataTable.isDataTable('#expenseCategoriesTable')) {

            $('#expenseCategoriesTable').DataTable().destroy();

        }

        $('#expenseCategoriesTable').DataTable({

            pageLength: 10,

            responsive: true,

            order: [],

            dom: "<'dt-top'<'dt-left'lB><'dt-right'f>>rtip",

            buttons: [

                {
                    extend: 'excelHtml5',
                    text: 'Export Excel',
                    title: 'Expense Categories'
                },

                {
                    extend: 'pdfHtml5',
                    text: 'Export PDF',
                    title: 'Expense Categories'
                }

            ]

        });

    });

    /* =========================
       DELETE
    ========================= */

    function deleteCategory(id) {

    Swal.fire({

        title: 'Are you sure?',

        text: "This category will be deleted.",

        icon: 'warning',

        showCancelButton: true,

        confirmButtonColor: '#d33',

        cancelButtonColor: '#6b7280',

        confirmButtonText: 'Yes, delete it!'

    }).then((result) => {

        if (result.isConfirmed) {

            $.ajax({

                url: 'delete-expense-category.php',

                type: 'POST',

                data: {
                    id: id
                },

                dataType: 'json',

                success: function(response) {

                    if (response.success) {

                        Swal.fire({

                            icon: 'success',

                            title: 'Deleted!',

                            text: response.message,

                            timer: 1500,

                            showConfirmButton: false

                        }).then(() => {

                            location.reload();

                        });

                    } else {

                        Swal.fire({

                            icon: 'error',

                            title: 'Error',

                            text: response.message

                        });

                    }

                },

                error: function() {

                    Swal.fire({

                        icon: 'error',

                        title: 'Error',

                        text: 'Something went wrong'

                    });

                }

            });

        }

    });

}

    /* =========================
       STATUS TOGGLE
    ========================= */

    function toggleStatus(toggle, id) {

    const status = toggle.checked
        ? 'active'
        : 'inactive';

    $.ajax({

        url: 'expense-categories.php',

        type: 'POST',

        data: {
            id: id,
            status: status
        },

        dataType: 'json',

        success: function (response) {

            if (response.success) {

                Swal.fire({

                    icon: 'success',

                    title: 'Success',

                    text: response.message,

                    timer: 1500,

                    showConfirmButton: false

                });

            } else {

                Swal.fire({

                    icon: 'error',

                    title: 'Error',

                    text: 'Failed to update status'

                });

                toggle.checked = !toggle.checked;

            }

        },

        error: function () {

            Swal.fire({

                icon: 'error',

                title: 'Error',

                text: 'Something went wrong'

            });

            toggle.checked = !toggle.checked;

        }

    });

}

</script>

<!-- CATEGORY MODAL -->

<div id="categoryModal" style="
        display:none;
        position:fixed;
        inset:0;
        background:rgba(15,23,42,0.6);
        z-index:9999;
        align-items:center;
        justify-content:center;
        padding:1rem;
    ">

    <div style="
        width:100%;
        max-width:420px;
        background:#fff;
        border-radius:18px;
        overflow:hidden;
        box-shadow:0 25px 50px rgba(0,0,0,0.25);
        animation:modalFade 0.25s ease;
    ">

        <!-- HEADER -->

        <div style="
            background:linear-gradient(135deg,#4f46e5,#4338ca);
            padding:1.4rem 1.5rem;
            display:flex;
            align-items:center;
            justify-content:space-between;
        ">

            <h3 id="modalTitle" style="
                    margin:0;
                    color:white;
                    font-size:1.65rem;
                    font-weight:700;
                ">

                Add Expense Category

            </h3>

            <button type="button" onclick="closeCategoryModal()" style="
                    border:none;
                    background:none;
                    color:white;
                    font-size:1.6rem;
                    cursor:pointer;
                    line-height:1;
                ">

                <i class="ri-close-line"></i>

            </button>

        </div>

        <!-- FORM -->

        <form method="POST" id="categoryForm">

            <input type="hidden" name="category_id" id="category_id">

            <div style="padding:1.5rem;">

                <!-- CATEGORY NAME -->

                <div style="margin-bottom:1.25rem;">

                    <label style="
                        display:block;
                        margin-bottom:0.5rem;
                        font-weight:600;
                        color:#1e293b;
                    ">

                        Category Name

                    </label>

                    <input type="text" name="category_name" id="category_name" class="modal-input" autocomplete="off">

<small id="categoryError" style="
    color:#ef4444;
    margin-top:0.35rem;
    display:block;
    font-size:0.82rem;
">
    <?= $errors['category_name'] ?? '' ?>
</small>

                </div>

                <!-- STATUS -->

                <div style="margin-bottom:0.5rem;">

<label style="
    display:block;
    margin-bottom:0.5rem;
    font-weight:600;
    color:#1e293b;
">
    Status
</label>

<select name="status" id="status" class="modal-input">

    <option value="">Select Status</option>

    <option value="active">
        Active
    </option>

    <option value="inactive">
        Inactive
    </option>

</select>

<?php if(isset($errors['status'])): ?>
<small style="
    color:#ef4444;
    margin-top:0.35rem;
    display:block;
    font-size:0.82rem;
">
    <?= $errors['status']; ?>
</small>
<?php endif; ?>

                </div>

            </div>

            <!-- FOOTER -->

            <div style="
                padding:1.25rem 1.5rem;
                display:flex;
                gap:0.75rem;
                justify-content:flex-end;
            ">

                <button type="submit" name="save_category" id="submitBtn" style="
                        border:none;
                        background:linear-gradient(135deg,#4f46e5,#4338ca);
                        color:white;
                        padding:0.85rem 1.5rem;
                        border-radius:10px;
                        font-weight:600;
                        cursor:pointer;
                        min-width:150px;
                        transition:0.2s;
                    ">

                    Save Category

                </button>

                <button type="button" onclick="closeCategoryModal()" style="
                        border:none;
                        background:#eef2f7;
                        color:#475569;
                        padding:0.85rem 1.5rem;
                        border-radius:10px;
                        font-weight:600;
                        cursor:pointer;
                        min-width:110px;
                    ">

                    Cancel

                </button>

            </div>

        </form>

    </div>

</div>


<script>

    /* =========================
       OPEN ADD MODAL
    ========================= */

    function openAddModal() {

        document.getElementById('categoryForm').reset();

        document.getElementById('modalTitle')
            .innerText = 'Add Expense Category';

        document.getElementById('submitBtn')
            .innerText = 'Save Category';

        document.getElementById('category_id').value = '';

        // IMPORTANT FIX
        document.getElementById('status').value = 'active';

        document.getElementById('categoryModal')
            .style.display = 'flex';
    }

    /* =========================
       OPEN EDIT MODAL
    ========================= */

    function openEditModal(id, name, status) {

        document.getElementById('modalTitle')
            .innerText = 'Edit Expense Category';

        document.getElementById('submitBtn')
            .innerText = 'Update Category';

        document.getElementById('category_id').value = id;

        document.getElementById('category_name').value = name;

        // IMPORTANT FIX
        document.getElementById('status').value =
            status.toLowerCase();

        document.getElementById('categoryModal')
            .style.display = 'flex';
    }

    /* =========================
       CLOSE MODAL
    ========================= */

    function closeCategoryModal() {

        document.getElementById('categoryModal')
            .style.display = 'none';
    }

</script>
<?php if (!empty($errors)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('categoryModal').style.display = 'flex';
});
</script>
<?php endif; ?>
<?php if (isset($_SESSION['category_error'])): ?>

    <script>

        document.addEventListener('DOMContentLoaded', function () {

            openAddModal();

            document.getElementById('categoryError')
                .innerText =
                "<?= $_SESSION['category_error']; ?>";

        });

    </script>

    <?php unset($_SESSION['category_error']); ?>

<?php endif; ?>
<?php if (isset($_SESSION['category_success'])): ?>

    <script>

        document.addEventListener('DOMContentLoaded', function () {

            Swal.fire({

                icon: 'success',

                title: 'Success',

                text: "<?= $_SESSION['category_success']; ?>",

                confirmButtonColor: '#4f46e5'

            });

        });

    </script>

    <?php unset($_SESSION['category_success']); ?>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>