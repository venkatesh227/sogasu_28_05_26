<?php
session_start();
include '../includes/db.php';

$stmt = $pdo->query("SELECT * FROM job_roles WHERE is_deleted = 0 ORDER BY id DESC");
$jobRoles = $stmt->fetchAll();

$pageTitle = "Job Roles - Sogasu";
$activePage = "job-roles";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['status'])) {
    $stmt = $pdo->prepare("UPDATE job_roles SET status = ? WHERE id = ?");
    echo json_encode([
        'success' => $stmt->execute([$_POST['status'], $_POST['id']])
    ]);
    exit;
}
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="display: flex; justify-content: space-between; align-items: center; ">
        <div>
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Job Roles</h2>
            <p style="color: #64748b; margin-top: 0.25rem;">Manage employee job roles and designations</p>
        </div>

        <button class="btn btn-primary" onclick="window.location.href='add-job-role.php'" style="background: #4f46e5; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; color: white;">
            <i class="ri-briefcase-4-line"></i> Add New Job Role
        </button>
    </div>

    <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem;">
        <table id="jobRolesTable" class="jobRolesTable" style="text-align: left; border-collapse: collapse;">
            <thead>
                <tr
                    style="border-bottom: 2px solid #f1f5f9; color: #64748b; font-size: 0.875rem; text-transform: uppercase;">
                    <th style="padding: 1rem;">S.No</th>
                    <th style="padding: 1rem;">Role Name</th>
                    <th style="padding: 1rem;">Description</th>
                    <th style="padding: 1rem;">Status</th>
                    <th style="padding: 1rem; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($jobRoles)): ?>
                    <?php $i = 1; ?>
                    <?php foreach ($jobRoles as $role): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 1rem; font-weight: 600; color: #94a3b8;"><?= $i++; ?></td>
                            <td style="padding: 1rem; font-weight: 600; color: #0f172a;">
                                <?= htmlspecialchars($role['role_name']); ?>
                            </td>
                            <td style="padding: 1rem; color: #64748b;">
                                <?= htmlspecialchars($role['description'] ?: 'No description'); ?>
                            </td>
                            <td style="padding: 1rem;">
                                <label class="toggle-switch">
                                    <input type="checkbox" <?= $role['status'] == 'active' ? 'checked' : '' ?>
                                        onchange="toggleStatus(this, <?= $role['id']; ?>)">
                                    <span class="toggle-slider"></span>
                                </label>
                            </td>
                            <td style="padding: 1rem; text-align: right;">
                                <button onclick="window.location.href='add-job-role.php?id=<?= $role['id']; ?>'"
                                    style="border: none; background: transparent; color: #3b82f6; cursor: pointer; margin-right: 1rem;"
                                    title="Edit"><i class="ri-pencil-line"></i></button>
                                <button onclick="confirmDelete(<?= $role['id']; ?>)"
                                    style="border: none; background: transparent; color: #ef4444; cursor: pointer;"
                                    title="Delete"><i class="ri-delete-bin-line"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 2rem;">No job roles found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</main>
</script>
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


<?php if (!empty($_SESSION['success'])): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: '<?= $_SESSION['success'] === "updated"
                ? "Job role updated successfully"
                : ($_SESSION['success'] === "deleted"
                    ? "Job role deleted successfully"
                    : "Job role added successfully") ?>'
        });
    </script>
    <?php unset($_SESSION['success']); endif; ?>
<script>
    function confirmDelete(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "This job role will be removed!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "delete-job-role.php?id=" + id;
            }
        });
    }
    function toggleStatus(el, id) {
        const status = el.checked ? 'active' : 'inactive';

        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
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
    $(document).ready(function () {

        if ($.fn.DataTable.isDataTable('#jobRolesTable')) {
            $('#jobRolesTable').DataTable().destroy();
        }

        window.table = $('#jobRolesTable').DataTable({
            pageLength: 10,
            responsive: true,
            order: [],

            dom: "<'dt-top'<'dt-left'lB><'dt-right'f>>rtip",

            buttons: [
                {
                    extend: 'excelHtml5',
                    text: 'Export Excel',
                    title: 'Job Roles',
                    exportOptions: {
                        columns: ':not(:last-child)',
                        format: {
                            body: function (data, row, column, node) {

                                // STATUS COLUMN
                                if (column === 3) {
                                    return $(node).find('input').is(':checked') ? 'Active' : 'Inactive';
                                }

                                return $(node).text().trim();
                            }
                        }
                    }
                },
                {
                    extend: 'pdfHtml5',
                    text: 'Export PDF',
                    title: 'Job Roles',
                    orientation: 'landscape',

                    exportOptions: {
                        columns: ':not(:last-child)',
                        format: {
                            body: function (data, row, column, node) {

                                if (column === 3) {
                                    return $(node).find('input').is(':checked') ? 'Active' : 'Inactive';
                                }

                                return $(node).text().trim();
                            }
                        }
                    },


                    customize: function (doc) {

                        // CENTER TABLE PROPERLY
                        doc.content[1].table.widths = ['*', '*', '*', '*'];
                        doc.content[1].margin = [0, 0, 0, 0];
                        doc.content[1].alignment = 'center';

                        // REMOVE HEADER COLOR
                        doc.styles.tableHeader.fillColor = 'white';
                        doc.styles.tableHeader.color = 'black';

                        // REMOVE ROW COLORS
                        doc.content[1].table.body.forEach(function (row) {
                            row.forEach(function (cell) {
                                cell.fillColor = 'white';
                                cell.color = 'black';
                            });
                        });

                        // ADD BLACK BORDERS
                        doc.content[1].layout = {
                            hLineWidth: () => 0.5,
                            vLineWidth: () => 0.5,
                            hLineColor: () => '#000',
                            vLineColor: () => '#000'
                        };
                    }
                }
            ]
        });

    });
</script>


<?php include 'includes/footer.php'; ?>