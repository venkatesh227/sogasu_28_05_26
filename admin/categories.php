<?php
session_start();
$pageTitle = "Categories - Sogasu";
$activePage = "categories";
include 'includes/header.php';
include '../includes/db.php';

$stmt = $pdo->query("
    SELECT c.*, COUNT(sc.id) as sub_count
    FROM categories c
    LEFT JOIN sub_categories sc ON sc.category_id = c.id
    WHERE c.deleted_at IS NULL
    GROUP BY c.id
    ORDER BY c.id DESC
");
$categories = $stmt->fetchAll();
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="display: flex; justify-content: space-between; align-items: center; ">
        <div>
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Categories</h2>
            <p class="text-muted">Master list of garment categories</p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <button class="btn" onclick="window.location.href='bulk-upload.php'"
                style="background: white; border: 1px solid #e2e8f0; color: #64748b;"><i
                    class="ri-upload-cloud-2-line"></i> Bulk Upload</button>
            <button class="btn btn-primary" onclick="window.location.href='add-category.php'"><i
                    class="ri-add-line"></i> Add Category</button>
        </div>
    </div>


    <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem;">
        <table id="categoriesTable" style="text-align: left;">
            <thead>
                <tr
                    style="border-bottom: 2px solid #f1f5f9; color: #64748b; font-size: 0.875rem; text-transform: uppercase;">
                    <th style="padding: 1rem;">ID</th>
                    <th style="padding: 1rem;">Icon</th>
                    <th style="padding: 1rem;">Category Name</th>
                    <th style="padding: 1rem;">Description</th>
                    <th style="padding: 1rem;">Sub-Categories</th>
                    <th style="padding: 1rem;">Status</th>
                    <th style="padding: 1rem; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; ?>
                <?php foreach ($categories as $category): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td><?= $i++; ?></td>
                        <td style="padding: 1rem;">
                            <?php if (strpos($category['icon'], 'uploads/') === 0): ?>
                                <img src="../<?php echo htmlspecialchars($category['icon']); ?>"
                                    style="width: 32px; height: 32px; object-fit: contain; border-radius: 4px;">
                            <?php else: ?>
                                <i class="<?php echo htmlspecialchars($category['icon']); ?>" style="font-size: 1.2rem;"></i>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 1rem; font-weight: 600; color: #0f172a;">
                            <?php echo htmlspecialchars($category['category_name']); ?>
                        </td>
                        <td style="padding: 1rem; color: #64748b;"><?php echo htmlspecialchars($category['description']); ?>
                        </td>
                        <td style="padding: 1rem; color: #475569;">
                            <span
                                style="background: #f1f5f9; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem;">
                                <?php echo $category['sub_count']; ?> Types
                            </span>
                        </td>
                        <td style="padding: 1rem;">
                            <label class="toggle-switch">
                                <input type="checkbox" <?= $category['status'] == 'active' ? 'checked' : '' ?>
                                    onchange="toggleStatus(this, <?= $category['id']; ?>)">
                                <span class="toggle-slider"></span>
                            </label>
                        </td>
                        <td style="padding: 1rem; text-align: right;">
                            <button onclick="editCategory(<?php echo $category['id']; ?>)"
                                style="border: none; background: transparent; color: #3b82f6; cursor: pointer; margin-right: 1rem;"
                                title="Edit"><i class="ri-pencil-line"></i></button>
                            <button onclick="deleteCategory(<?php echo $category['id']; ?>)"
                                style="border: none; background: transparent; color: #ef4444; cursor: pointer;"
                                title="Delete"><i class="ri-delete-bin-line"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<style>
    /* ===== EXACT SAME STYLE AS JOB ROLES ===== */

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

    /* ACTIVE (GREEN LIKE FIRST IMAGE) */
    .toggle-switch input:checked+.toggle-slider {
        background-color: #22c55e;
    }

    /* MOVE BALL */
    .toggle-switch input:checked+.toggle-slider:before {
        transform: translateX(18px);
    }

    /* Fix DataTable top alignment */
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

    /* Fix spacing for dropdown + buttons */
    .dataTables_length {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .dataTables_length select {
        height: 32px;
        padding: 4px 8px;
    }

    /* Buttons spacing */
    .dt-buttons {
        display: flex;
        gap: 10px;
    }

    /* Search box alignment */
    .dataTables_filter input {
        height: 32px;
        margin-left: 5px;
    }

    /* Keep "Show 10 entries" in one line */
    .dataTables_length label {
        display: flex !important;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
    }

    /* Fix dropdown alignment */
    .dataTables_length select {
        height: 32px;
        padding: 4px 8px;
    }
</style>

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
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js"></script>
<script>
    $(document).ready(function () {

        //  1. INITIALIZE DATATABLE
        if ($.fn.DataTable.isDataTable('#categoriesTable')) {
            $('#categoriesTable').DataTable().destroy();
        }

        window.table = $('#categoriesTable').DataTable({
            pageLength: 10,
            responsive: true,
            order: [],
            dom: "<'dt-top'<'dt-left'lB><'dt-right'f>>rtip",

            buttons: [
                {
                    extend: 'excelHtml5',
                    text: 'Export Excel',
                    title: 'Categories',
                    exportOptions: {
                        columns: [0, 2, 3, 4, 5],
                        format: {
                            body: function (data, row, column, node) {

                                //  FIXED STATUS COLUMN
                                if (column === 4) {
                                    return $(node).find('input[type="checkbox"]').is(':checked')
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

                        const table = document.querySelector('#categoriesTable');
                        const clone = table.cloneNode(true);

                        //  REMOVE ACTION COLUMN (last column)
                        clone.querySelectorAll('tr').forEach(row => {
                            row.deleteCell(-1);
                        });

                        //  FIX STATUS TO TEXT
                        clone.querySelectorAll('tbody tr').forEach(row => {
                            const checkbox = row.querySelector('input[type="checkbox"]');
                            if (checkbox) {
                                const td = checkbox.closest('td');
                                td.innerText = checkbox.checked ? 'Active' : 'Inactive';
                                td.style.textAlign = 'center';
                            }
                        });

                        // FIX IMAGE PATH (IMPORTANT)
                        clone.querySelectorAll('img').forEach(img => {

                            let src = img.getAttribute('src');

                            // Convert to absolute URL correctly
                            if (!src.startsWith('http')) {
                                const base = window.location.origin + window.location.pathname.replace('/admin/categories.php', '');
                                img.src = base + '/' + src.replace('../', '');
                            }

                            // Force size so PDF renders properly
                            img.style.width = '30px';
                            img.style.height = '30px';
                        });

                        //  STYLE CLEAN
                        clone.querySelectorAll('th, td').forEach(cell => {
                            cell.style.border = '1px solid black';
                            cell.style.padding = '6px';
                            cell.style.fontSize = '12px';
                        });

                        const wrapper = document.createElement('div');
                        wrapper.style.padding = '20px';

                        const title = document.createElement('h2');
                        title.innerText = 'Categories';
                        title.style.textAlign = 'center';
                        title.style.marginBottom = '10px';

                        wrapper.appendChild(title);
                        wrapper.appendChild(clone);

                        html2pdf().set({
                            margin: 10,
                            filename: 'Categories.pdf',
                            html2canvas: {
                                scale: 2,
                                useCORS: true   //  REQUIRED FOR IMAGES
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
    // Display success message if it exists
    <?php if (isset($_SESSION['success_message'])): ?>
        Swal.fire({
            title: 'Success!',
            text: '<?php echo $_SESSION['success_message']; ?>',
            icon: 'success',
            confirmButtonColor: '#3085d6'
        });
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    function toggleStatus(el, id) {
        const status = el.checked ? 'active' : 'inactive';

        fetch('update-category-status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + id + '&status=' + status
        })
            .then(res => res.text())
            .then(res => {
                let data;
                try {
                    data = JSON.parse(res);
                } catch {
                    data = { success: false };
                }

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
    function editCategory(categoryId) {
        window.location.href = 'edit-category.php?id=' + categoryId;
    }

    function deleteCategory(categoryId) {
        Swal.fire({
            title: 'Delete Category',
            text: 'Are you sure you want to delete this category? This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('delete-category.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'id=' + categoryId
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'Deleted!',
                                text: 'Category has been deleted.',
                                icon: 'success',
                                confirmButtonColor: '#3085d6'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', data.message || 'Failed to delete category', 'error');
                        }
                    });
            }
        });
    }
</script>

<?php include 'includes/footer.php'; ?>