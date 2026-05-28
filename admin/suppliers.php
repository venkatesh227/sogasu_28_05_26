<?php
session_start();
include '../includes/db.php';

$stmt = $pdo->query("SELECT * FROM suppliers WHERE is_deleted = 0 ORDER BY id DESC");
$suppliers = $stmt->fetchAll();

$pageTitle = "Suppliers - Sogasu";
$activePage = "suppliers";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['status'])) {
    $stmt = $pdo->prepare("UPDATE suppliers SET status = ? WHERE id = ?");
    echo json_encode([
        'success' => $stmt->execute([$_POST['status'], $_POST['id']])
    ]);
    exit;
}
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Suppliers</h2>
            <p style="color: #64748b; margin-top: 0.25rem;">Manage inventory material suppliers and contacts</p>
        </div>

        <button class="btn btn-primary" onclick="window.location.href='add-supplier.php'" style="background: #4f46e5; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; color: white;">
            <i class="ri-truck-line"></i> Add New Supplier
        </button>
    </div>

    <div class="table-container" style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem;">
        <table id="suppliersTable" class="table compact-table" style="width:100%; text-align: left; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 2px solid #f1f5f9; color: #64748b; font-size: 0.875rem; text-transform: uppercase;">
                    <th style="padding: 1rem; width: 10%;">S.No</th>
                    <th style="padding: 1rem; width: 40%;">Supplier Name</th>
                    <th style="padding: 1rem; width: 25%;">Contact Info</th>
                    <th style="padding: 1rem; width: 13%;">Status</th>
                    <th style="padding: 1rem; text-align: right; width: 12%;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($suppliers)): ?>
                    <?php $i = 1; ?>
                    <?php foreach ($suppliers as $supp): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 1rem; font-weight: 600; color: #94a3b8;"><?= $i++; ?></td>
                            <td style="padding: 1rem; font-weight: 700; color: #0f172a;">
                                <?= htmlspecialchars($supp['supplier_name']); ?>
                            </td>
                            <td style="padding: 1rem; color: #64748b;">
                                <?= htmlspecialchars($supp['contact'] ?: 'No contact'); ?>
                            </td>
                            <td style="padding: 1rem;">
                                <label class="toggle-switch">
                                    <input type="checkbox" <?= $supp['status'] == 'active' ? 'checked' : '' ?>
                                        onchange="toggleStatus(this, <?= $supp['id']; ?>)">
                                    <span class="toggle-slider"></span>
                                </label>
                            </td>
                            <td style="padding: 1rem; text-align: right;">
                                <button onclick="window.location.href='add-supplier.php?id=<?= $supp['id']; ?>'"
                                    style="border: none; background: transparent; color: #3b82f6; cursor: pointer; margin-right: 1rem;"
                                    title="Edit"><i class="ri-pencil-line"></i></button>
                                <button onclick="confirmDelete(<?= $supp['id']; ?>)"
                                    style="border: none; background: transparent; color: #ef4444; cursor: pointer;"
                                    title="Delete"><i class="ri-delete-bin-line"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 2rem; color: #94a3b8;">No suppliers found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php if (!empty($_SESSION['success'])): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: '<?= $_SESSION['success'] === "updated"
                ? "Supplier updated successfully"
                : ($_SESSION['success'] === "deleted"
                    ? "Supplier deleted successfully"
                    : "Supplier added successfully") ?>'
        });
    </script>
    <?php unset($_SESSION['success']); endif; ?>

<script>
    function confirmDelete(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "This supplier will be removed!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "delete-supplier.php?id=" + id;
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
                            ? 'Supplier activated successfully'
                            : 'Supplier deactivated successfully',
                        timer: 1200,
                        showConfirmButton: false
                    });
                } else {
                    el.checked = !el.checked;
                }
            });
    }

    $(document).ready(function () {
        if ($.fn.DataTable.isDataTable('#suppliersTable')) {
            $('#suppliersTable').DataTable().destroy();
        }

        $('#suppliersTable').DataTable({
            pageLength: 10,
            responsive: true,
            order: [],
            dom: "<'dt-top'<'dt-left'lB><'dt-right'f>>rtip",
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: 'Export Excel',
                    title: 'Suppliers',
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
                    }
                },
                {
                    extend: 'pdfHtml5',
                    text: 'Export PDF',
                    title: 'Suppliers',
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
                    }
                }
            ]
        });
    });
</script>

<?php include 'includes/footer.php'; ?>
