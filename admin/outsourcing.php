<?php
$pageTitle = "Outsourcing - Sogasu";
$activePage = "outsourcing";
include '../includes/db.php';
$employees = $pdo->query("
    SELECT 
        id,
        CONCAT(first_name, ' ', last_name) AS employee_name
    FROM employees
    WHERE job_role NOT IN ('HR', 'Supervisor', 'Manager', 'Admin')
    AND status = 1
    AND is_deleted = 0
    ORDER BY first_name ASC
")->fetchAll(PDO::FETCH_ASSOC);
if (isset($_POST['update_status'])) {

    $id = $_POST['id'] ?? 0;
    $status = $_POST['status'] ?? '';

    if ($id && $status) {

        $employee_id = $_POST['employee_id'] ?? null;
        $given_date = $_POST['given_date'] ?? null;
        $expected_date = $_POST['expected_date'] ?? null;

        if (!empty($employee_id)) {

            $stmt = $pdo->prepare("
        UPDATE orders 
        SET 
            assigned_employee_id = ?,
            order_status = ?,
            employee_taken_at = ?,
            due_date = ?
        WHERE id = ?
    ");

            $result = $stmt->execute([
                $employee_id,
                $status,
                $given_date,
                $expected_date,
                $id
            ]);

        } else {

            $stmt = $pdo->prepare("
        UPDATE orders 
        SET 
            order_status = ?,
            employee_taken_at = ?,
            due_date = ?
        WHERE id = ?
    ");

            $result = $stmt->execute([
                $status,
                $given_date,
                $expected_date,
                $id
            ]);
        }

        if ($result) {
            echo "success";
        } else {
            echo "error";
        }
    } else {
        echo "error";
    }

    exit;
}
$statusStmt = $pdo->query("SHOW COLUMNS FROM orders LIKE 'order_status'");
$statusRow = $statusStmt->fetch(PDO::FETCH_ASSOC);

preg_match("/^enum\\((.*)\\)$/", $statusRow['Type'], $matches);

$statusList = [];
if (!empty($matches[1])) {
    $statusList = str_getcsv($matches[1], ',', "'");
}
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="padding:1rem;">

        <!-- Header -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
            <h2 style="color:#1e293b;">Outsourcing</h2>

            <div style="display:flex; gap:0.5rem;">
                <input type="text" placeholder="Search..." class="form-control" style="width:200px;">
                <button class="btn btn-primary"><i class="ri-search-line"></i></button>
            </div>
        </div>

        <!-- Table -->
        <div class="table-box">
            <table>
                <thead>
                    <tr>
                        <th>Order No</th>
                        <th>Product (Material Design)</th>
                        <th>Employee</th>
                        <th>Given Date</th>
                        <th>Expected Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <?php
                $stmt = $pdo->query("
                    SELECT 
                        o.id,
                        o.order_code,
                        o.fabric_details,
                        o.order_status,
                        o.assigned_employee_id,
                        o.employee_taken_at AS given_date,
                        o.due_date AS expected_date,
                        e.first_name,
                        e.last_name
                    FROM orders o
                    LEFT JOIN employees e ON o.assigned_employee_id = e.id
                    WHERE o.is_deleted = 0
                    ORDER BY o.id DESC
                ");

                $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <tbody>

                    <?php foreach ($orders as $o) { ?>
                        <tr>

                            <td>#<?= htmlspecialchars($o['order_code']) ?></td>

                            <td><?= htmlspecialchars($o['fabric_details']) ?></td>

                            <td>
                                <?= htmlspecialchars(trim(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? ''))) ?: 'Not Assigned' ?>
                            </td>

                            <td><?= $o['given_date'] ? date('d/m/Y', strtotime($o['given_date'])) : '-' ?></td>

                            <td><?= $o['expected_date'] ? date('d/m/Y', strtotime($o['expected_date'])) : '-' ?></td>

                            <td>
                                <span class="badge">
                                    <?= ucfirst(str_replace('_', ' ', $o['order_status'])) ?>
                                </span>
                            </td>

                            <td>
                                <button class="btn btn-sm"
                                    style="background: #eef2ff; color: #4f46e5; border: 1px solid #c7d2fe;" onclick="openEdit(
                                        '<?= $o['id'] ?>',
                                        '<?= $o['assigned_employee_id'] ?? '' ?>',
                                        '<?= $o['given_date'] ? date('Y-m-d', strtotime($o['given_date'])) : '' ?>',
                                        '<?= $o['expected_date'] ? date('Y-m-d', strtotime($o['expected_date'])) : '' ?>',
                                        '<?= $o['order_status'] ?>'
                                    )">
                                    <i class="ri-pencil-line"></i> Edit
                                </button>
                            </td>

                        </tr>
                    <?php } ?>

                </tbody>
            </table>
        </div>

    </div>
</main>

<!-- Edit Card Modal -->
<div id="editModal" class="modal">
    <div class="modal-card"
        style="width: 400px; padding: 0; overflow: hidden; border: none; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">

        <div
            style="background: var(--primary); color: white; padding: 1.25rem; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem;">Edit Outsourcing Details</h3>
            <i class="ri-close-line" style="cursor: pointer; font-size: 1.5rem;" onclick="closeModal()"></i>
        </div>

        <div style="padding: 1.5rem;">
            <div class="form-group">
                <label class="form-label" style="font-weight: 600; color: #475569;">Outsourcing Employee</label>
                <select id="employee" class="form-control">
                    <option value="">Select Employee</option>

                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>">
                            <?= htmlspecialchars($emp['employee_name']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" style="font-weight: 600; color: #475569;">Given Date</label>
                    <input type="date" id="given" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-weight: 600; color: #475569;">Expected Date</label>
                    <input type="date" id="expected" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" style="font-weight: 600; color: #475569;">Status</label>
                <select id="status" class="form-select">
                    <?php foreach ($statusList as $status): ?>
                        <option value="<?= $status ?>">
                            <?= ucwords(str_replace('_', ' ', $status)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" style="font-weight: 600; color: #475569;">Reference Image</label>
                <div
                    style="border: 2px dashed #e2e8f0; padding: 1rem; border-radius: 8px; text-align: center; background: #f8fafc; position: relative;">
                    <i class="ri-image-add-line"
                        style="font-size: 1.5rem; color: #94a3b8; display: block; margin-bottom: 0.25rem;"></i>
                    <span style="font-size: 0.75rem; color: #64748b;">Click to upload reference</span>
                    <input type="file"
                        style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer;">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
                <button class="btn"
                    style="background: white; border: 1px solid #e2e8f0; color: #64748b; padding: 0.5rem 1rem;"
                    onclick="closeModal()">Cancel</button>
                <button class="btn btn-primary" style="padding: 0.5rem 1.5rem;" onclick="saveChanges()">Save
                    Changes</button>
            </div>
        </div>
    </div>
</div>

<style>
    .table-box {
        background: white;
        border: 1px solid #e2e8f0;
        padding: 1rem;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th,
    td {
        padding: 0.8rem;
        font-size: 0.85rem;
    }

    th {
        color: #64748b;
    }

    .badge {
        background: #e0f2fe;
        color: #0284c7;
        padding: 3px 8px;
        border-radius: 6px;
    }

    .edit-icon {
        cursor: pointer;
        color: #4f46e5;
    }

    /* Modal */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.3);
        justify-content: center;
        align-items: center;
    }

    .modal-card {
        background: white;
        padding: 1.5rem;
        width: 350px;
        border-radius: 10px;
    }

    .form-group {
        margin-bottom: 0.8rem;
    }

    .form-group input,
    .form-group select {
        width: 100%;
        padding: 0.5rem;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>

    let currentOrderId = null;

    function openEdit(id, emp, given, expected, status) {

        currentOrderId = id;

        document.getElementById('editModal').style.display = 'flex';

        document.getElementById('employee').value = emp;
        document.getElementById('given').value = given;
        document.getElementById('expected').value = expected;
        document.getElementById('status').value = status;
    }

    function closeModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    function saveChanges() {

        let status = document.getElementById('status').value;
        let employee = document.getElementById('employee').value;
        let given = document.getElementById('given').value;
        let expected = document.getElementById('expected').value;

        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:
                'update_status=1' +
                '&id=' + currentOrderId +
                '&employee_id=' + encodeURIComponent(employee) +
                '&status=' + encodeURIComponent(status) +
                '&given_date=' + encodeURIComponent(given) +
                '&expected_date=' + encodeURIComponent(expected)
        })
            .then(res => res.text())
            .then(data => {

                if (data === 'success') {

                    Swal.fire({
                        icon: 'success',
                        title: 'Updated',
                        text: 'Status updated successfully',
                        timer: 1200,
                        showConfirmButton: false
                    });

                    setTimeout(() => {
                        location.reload();
                    }, 1200);

                } else {

                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Update failed'
                    });

                }

            });
    }

    // convert dd/mm/yyyy → yyyy-mm-dd
    function formatDate(date) {
        if (!date) return '';
        let parts = date.split('/');
        if (parts.length !== 3) return date;
        return parts[2] + '-' + parts[1] + '-' + parts[0];
    }

</script>

<?php include 'includes/footer.php'; ?>