<?php
session_start();
include '../includes/db.php';

// ===== HANDLE TOGGLE STATUS (AJAX) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['status'])) {
    header('Content-Type: application/json');
    $id = (int) $_POST['id'];
    $status = $_POST['status'] === 'active' ? 'active' : 'inactive';

    $stmt = $pdo->prepare("UPDATE sub_categories SET status = ? WHERE id = ?");

    if ($stmt->execute([$status, $id])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }

    exit;
}

// JOIN query (important)
$stmt = $pdo->query("
    SELECT sc.*, c.category_name 
    FROM sub_categories sc
    LEFT JOIN categories c ON sc.category_id = c.id
    WHERE sc.is_deleted = 0
    ORDER BY sc.id DESC
");

$sub_categories = $stmt->fetchAll();
$pageTitle = "Sub Categories - Sogasu";
$activePage = "sub-categories";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="display: flex; justify-content: space-between; align-items: center; ">
        <div>
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Sub Categories</h2>
            <p class="text-muted">Detailed garment types and base prices</p>
        </div>
        <button class="btn btn-primary" onclick="window.location.href='add-sub-category.php'"><i
                class="ri-add-line"></i> Add Sub Category</button>
    </div>


    <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem;">
        <table id="subCategoryTable" style="text-align: left;">
            <thead>
                <tr
                    style="border-bottom: 2px solid #f1f5f9; color: #64748b; font-size: 0.875rem; text-transform: uppercase;">
                    <th>S.NO</th>
                    <th style="padding: 1rem;">Image</th>
                    <th style="padding: 1rem;">Name</th>
                    <th style="padding: 1rem;">Category</th>
                    <th style="padding: 1rem;">Description</th>
                    <th style="padding: 1rem;">Base Price</th>
                    <th style="padding: 1rem;">Default Fabric</th>
                    <th style="padding: 1rem;">Status</th>
                    <th style="padding: 1rem; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($sub_categories)): ?>
                    <?php $i = 1; ?>
                    <?php foreach ($sub_categories as $row): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td><?= $i++; ?></td>
                            <!-- IMAGE -->
                            <td style="padding: 1rem;">
                                <?php if (!empty($row['image'])): ?>
                                    <img src="uploads/<?= $row['image'] ?>" width="50" height="50"
                                        style="border-radius:6px; object-fit:cover;">
                                <?php endif; ?>
                            </td>

                            <!-- NAME -->
                            <td style="padding: 1rem; font-weight: 600; color: #0f172a;">
                                <?= $row['name'] ?>
                            </td>

                            <!-- CATEGORY -->
                            <td style="padding: 1rem;">
                                <span class="badge" style="background: #eef2ff; color: #4f46e5;">
                                    <?= $row['category_name'] ?>
                                </span>
                            </td>
                            <!-- DESCRIPTION -->
                            <td style="padding: 1rem; color: #64748b;">
                                <?= $row['description'] ?>
                            </td>

                            <!-- PRICE -->
                            <td style="padding: 1rem; font-weight: 600;">
                                ₹ <?= number_format($row['price']) ?>
                            </td>

                            <!-- FABRIC -->
                            <td style="padding: 1rem; color: #64748b;">
                                <?= $row['fabric'] ?>
                            </td>

                            <!-- STATUS -->
                            <td style="padding: 1rem;">
                                <label class="toggle-switch">
                                    <input type="checkbox" <?= strtolower($row['status']) == 'active' ? 'checked' : '' ?>
                                        onchange="toggleStatus(this, <?= $row['id'] ?>)">
                                    <span class="toggle-slider"></span>
                                </label>
                            </td>

                            <!-- ACTION -->
                            <td style="padding: 1rem; text-align: right;">
                                <!-- EDIT -->
                                <a href="add-sub-category.php?id=<?= $row['id'] ?>"
                                    style="color:#64748b; margin-right:12px; font-size:18px;">
                                    <i class="ri-pencil-line"></i>
                                </a>
                                <!-- DELETE -->
                                <a href="javascript:void(0);" onclick="deleteSubCategory(<?= $row['id'] ?>)"
                                    style="color:#dc2626; font-size:18px;">
                                    <i class="ri-delete-bin-line"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding:20px;">No Data Found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
<style>
    td a:hover {
        transform: scale(1.2);
        transition: 0.2s;
    }

    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 36px;
        height: 18px;
    }

    .toggle-switch input {
        opacity: 0;
    }

    .toggle-slider {
        position: absolute;
        background: #e5e7eb;
        border-radius: 20px;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
    }

    .toggle-slider:before {
        content: "";
        position: absolute;
        height: 14px;
        width: 14px;
        left: 2px;
        top: 2px;
        background: white;
        border-radius: 50%;
    }

    .toggle-switch input:checked+.toggle-slider {
        background: #22c55e;
    }

    .toggle-switch input:checked+.toggle-slider:before {
        transform: translateX(18px);
    }

    .dataTables_length {
        margin-right: 15px;
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script>
    function deleteSubCategory(id) {
        Swal.fire({
            title: "Are you sure?",
            text: "This sub category will be removed!",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#dc2626",
            cancelButtonColor: "#6b7280",
            confirmButtonText: "Yes, delete it!"
        }).then((result) => {
            if (result.isConfirmed) {

                // redirect to delete file
                window.location.href = "delete-sub-category.php?id=" + id;

            }
        });
    }
</script>
<?php if (isset($_GET['created'])): ?>
    <script>
        Swal.fire({
            title: "Success",
            text: "Sub Category created successfully",
            icon: "success",
            confirmButtonColor: "#6366f1"
        }).then(() => {
            window.history.replaceState(null, null, "sub-categories.php");
        });
    </script>
<?php endif; ?>

<?php if (isset($_GET['updated'])): ?>
    <script>
        Swal.fire({
            title: "Success",
            text: "Sub Category updated successfully",
            icon: "success",
            confirmButtonColor: "#6366f1"
        }).then(() => {
            window.history.replaceState(null, null, "sub-categories.php");
        });
    </script>
<?php endif; ?>

<?php if (isset($_GET['deleted'])): ?>
    <script>
        Swal.fire({
            title: "Success",
            text: "Sub Category deleted successfully",
            icon: "success",
            confirmButtonColor: "#6366f1"
        }).then(() => {
            window.history.replaceState(null, null, "sub-categories.php");
        });
    </script>
<?php endif; ?>
<script>
    $(document).ready(function () {

        let table = $('#subCategoryTable').DataTable({
            pageLength: 10,
            responsive: true,
            order: [],
            dom: "<'dt-top'<'dt-left'lB><'dt-right'f>>rtip",

            buttons: [
                {
                    extend: 'excelHtml5',
                    text: 'Export Excel',
                    exportOptions: {
                        columns: [0, 2, 3, 4, 5, 6, 7],
                        format: {
                            body: function (data, row, column, node) {

                                if (column === 6) {
                                    return $('input[type="checkbox"]', node).prop('checked')
                                        ? 'Active'
                                        : 'Inactive';
                                }

                                return $(node).text().trim();
                            }
                        }
                    }
                },
                {
                    text: 'Export PDF',
                    action: function () {

                        const table = document.querySelector('#subCategoryTable');
                        const clone = table.cloneNode(true);

                        // ❌ REMOVE ACTION COLUMN ONLY
                        clone.querySelectorAll('tr').forEach(row => {
                            row.deleteCell(-1);
                        });

                        // STATUS TEXT
                        clone.querySelectorAll('tbody tr').forEach(row => {
                            const checkbox = row.querySelector('input[type="checkbox"]');
                            if (checkbox) {
                                const td = checkbox.closest('td');
                                td.innerText = checkbox.checked ? 'Active' : 'Inactive';
                                td.style.textAlign = 'center';
                            }
                        });

                        // IMAGE FIX
                        clone.querySelectorAll('img').forEach(img => {

                            let src = img.getAttribute('src');

                            if (!src.startsWith('http')) {
                                img.src = window.location.origin + '/sogasu/sogasu/admin/' + src;
                            }

                            img.style.width = '40px';
                            img.style.height = '40px';
                        });

                        //  TABLE STYLE
                        clone.querySelectorAll('table, th, td').forEach(el => {
                            el.style.border = '1px solid black';
                        });

                        clone.style.borderCollapse = 'collapse';
                        clone.style.width = '100%';

                        clone.querySelectorAll('th, td').forEach(cell => {
                            cell.style.padding = '6px';
                            cell.style.fontSize = '12px';
                        });

                        //  WRAPPER
                        const wrapper = document.createElement('div');

                        const title = document.createElement('h2');
                        title.innerText = 'Sub Categories';
                        title.style.textAlign = 'center';
                        title.style.marginBottom = '10px';

                        wrapper.appendChild(title);
                        wrapper.appendChild(clone);

                        html2pdf().set({
                            margin: 10,
                            filename: 'SubCategories.pdf',
                            html2canvas: {
                                scale: 2,
                                useCORS: true
                            },
                            jsPDF: {
                                unit: 'mm',
                                format: 'a4',
                                orientation: 'landscape'
                            }
                        }).from(wrapper).save();
                    }
                }
            ]
        });



    });
</script>
<script>
    function toggleStatus(el, id) {

    const status = el.checked ? 'active' : 'inactive';

    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + id + '&status=' + status
    })
    .then(res => res.json())
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
        }
    });
}
</script>
<?php include 'includes/footer.php'; ?>