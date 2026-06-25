<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/db.php';

$activePage = 'outsource-order-assignment';
$pageTitle = 'Outsource Order Assignment - Sogasu';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

/* =========================
   ASSIGN EMPLOYEE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_employee') {

    $orderId = (int) ($_POST['order_id'] ?? 0);
    $employeeId = (int) ($_POST['employee_id'] ?? 0);
    $supervisorId = (int) ($_POST['supervisor_id'] ?? 0);

    if ($orderId > 0 && $employeeId > 0) {

        $check = $pdo->prepare("
            SELECT id
            FROM outsource_order_responses
            WHERE order_id = ?
            AND employee_id = ?
            AND response = 'accepted'
        ");
        $check->execute([$orderId, $employeeId]);

        if ($check->fetch()) {

            $update = $pdo->prepare("
                UPDATE outsource_orders
                SET
                    supervisor_id = ?,
                    assigned_employee_id = ?,
                    employee_taken_at = NOW(),
                    order_status = 'approved'
                WHERE id = ?
                AND assigned_employee_id IS NULL
                AND order_status = 'accepted'
                AND is_deleted = 0
            ");

            $update->execute([$supervisorId, $employeeId, $orderId]);

            if ($update->rowCount() > 0) {
                $notify = $pdo->prepare("
                    INSERT INTO notifications
                    (employee_id, title, message, is_read, created_at)
                    VALUES (?, ?, ?, 0, NOW())
                ");

                $notify->execute([
                    $employeeId,
                    'Order Assigned',
                    'A new outsource order has been assigned to you.'
                ]);
            }
        }
    }

    $_SESSION['success'] = 'employee_assigned';
    header("Location: outsource-order-assignment.php");
    exit;
}

/* =========================
   FETCH ORDERS
========================= */
$stmt = $pdo->query("
    SELECT 
        o.id,
        o.order_code,
        o.total_amount,
        o.due_date,
        c.first_name,
        c.last_name,
        sc.name AS garment
    FROM outsource_orders o
    LEFT JOIN customers c ON c.id = o.customer_id
    LEFT JOIN sub_categories sc ON sc.id = o.sub_category_id
    WHERE o.order_status = 'accepted'
    AND o.assigned_employee_id IS NULL
    AND o.is_deleted = 0
    ORDER BY o.id DESC
");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   FETCH ACCEPTED RESPONSES
========================= */
$acceptedMap = [];

$stmt = $pdo->query("
    SELECT 
        r.order_id,
        r.employee_id,
        r.created_at,
        e.first_name,
        e.last_name
    FROM outsource_order_responses r
    INNER JOIN employees e ON e.id = r.employee_id
    WHERE r.response = 'accepted'
    AND e.employee_type = 'outsource'
    ORDER BY r.created_at ASC
");

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $acceptedMap[$row['order_id']][] = $row;
}
$historyStmt = $pdo->query("
    SELECT 
        o.order_code,
        o.employee_taken_at,

        e.first_name AS emp_first,
        e.last_name AS emp_last,

        s.first_name AS sup_first,
        s.last_name AS sup_last

    FROM outsource_orders o

    INNER JOIN employees e 
        ON e.id = o.assigned_employee_id

    LEFT JOIN employees s
        ON s.id = o.supervisor_id

    WHERE o.assigned_employee_id IS NOT NULL
    AND o.is_deleted = 0

    ORDER BY o.employee_taken_at DESC
");

$assignmentHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

$supervisors = $pdo->query("
    SELECT 
        id,
        CONCAT(first_name, ' ', last_name) AS name
    FROM employees
    WHERE job_role = 'supervisor'
    ORDER BY first_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<main class="main-content">
    <div class="content">

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0; font-size:28px;">Outsource Order Assignment</h2>
                <p style="margin-top:6px; color:#64748b;">
                    Assign accepted outsource orders to employees
                </p>
            </div>
        </div>

        <div class="table-container">
            <table class="table" id="assignmentTable">
                <thead>
                    <tr>
                        <th>Order No</th>
                        <th>Customer</th>
                        <th>Garment</th>
                        <th>Amount</th>
                        <th>Due Date</th>
                        <th>Accepted Employees</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>
                                <strong style="color:#4f46e5;">
                                    #<?= htmlspecialchars($order['order_code']) ?>
                                </strong>
                            </td>

                            <td>
                                <?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($order['garment'] ?? 'General') ?>
                            </td>

                            <td>
                                ₹<?= number_format($order['total_amount'], 2) ?>
                            </td>

                            <td>
                                <?= date('d M Y', strtotime($order['due_date'])) ?>
                            </td>

                            <td>
                                <?php
                                $employees = $acceptedMap[$order['id']] ?? [];
                                ?>

                                <?php if (empty($employees)): ?>
                                    <span style="color:#94a3b8;">No accepts</span>
                                <?php else: ?>
                                    <table style="width:100%; border-collapse:collapse;">
                                        <tr>
                                            <th>Name</th>
                                            <th>Accepted At</th>
                                            <th>Action</th>
                                        </tr>

                                        <?php foreach ($employees as $emp): ?>
                                            <tr>
                                                <td>
                                                    <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                                                </td>

                                                <td>
                                                    <?= date('d M h:i A', strtotime($emp['created_at'])) ?>
                                                </td>

                                                <td>
                                                    <button type="button" onclick="openSupervisorModal(
                                                                <?= $order['id'] ?>,
                                                                <?= $emp['employee_id'] ?>,
                                                                '<?= $order['order_code'] ?>'
                                                            )" style="
                                                            background:#D2A07E;
                                                            color:white;
                                                            border:none;
                                                            padding:6px 12px;
                                                            border-radius:6px;
                                                            cursor:pointer;
                                                        ">
                                                        Assign
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </table>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="table-container" style="margin-top:30px;">
            <h3>Assignment History</h3>

            <table class="table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Employee</th>
                        <th>Supervisor</th>
                        <th>Assigned At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assignmentHistory as $history): ?>
                        <tr>
                            <td>#<?= htmlspecialchars($history['order_code']) ?></td>
                            <td>
                                <?= htmlspecialchars($history['emp_first'] . ' ' . $history['emp_last']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(($history['sup_first'] ?? '') . ' ' . ($history['sup_last'] ?? '')) ?>
                            </td>

                            <td>
                                <?= date('d M Y h:i A', strtotime($history['employee_taken_at'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
</main>
<div id="supervisorModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:9999;">

    <div style="
        background:white;
        width:420px;
        margin:120px auto;
        padding:25px;
        border-radius:12px;
    ">
        <h3>Assign Supervisor</h3>

        <form method="POST">
            <input type="hidden" name="action" value="assign_employee">
            <input type="hidden" name="order_id" id="modal_order_id">
            <input type="hidden" name="employee_id" id="modal_employee_id">

            <p style="margin-bottom:12px;">
                Select supervisor for order
                <strong id="modal_order_code"></strong>
            </p>

            <select name="supervisor_id" required style="width:100%; padding:10px;">
                <option value="">-- Select Supervisor --</option>

                <?php foreach ($supervisors as $sup): ?>
                    <option value="<?= $sup['id'] ?>">
                        <?= htmlspecialchars($sup['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                <button type="button" onclick="closeSupervisorModal()" style="
                    background:#f1f5f9;
                    color:#334155;
                    border:1px solid #cbd5e1;
                    padding:8px 16px;
                    border-radius:6px;
                    cursor:pointer;
                    font-weight:600;
                ">
                    Cancel
                </button>

                <button type="submit" style="
                        background:#D2A07E;
                        color:white;
                        border:none;
                        padding:8px 16px;
                        border-radius:6px;
                        cursor:pointer;
                        font-weight:600;
                    ">
                    Assign
                </button>
            </div>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function openSupervisorModal(orderId, employeeId, orderCode) {
        document.getElementById('modal_order_id').value = orderId;
        document.getElementById('modal_employee_id').value = employeeId;
        document.getElementById('modal_order_code').innerText = '#' + orderCode;
        document.getElementById('supervisorModal').style.display = 'block';
    }

    function closeSupervisorModal() {
        document.getElementById('supervisorModal').style.display = 'none';
    }
</script>
<?php if (!empty($_SESSION['success']) && $_SESSION['success'] === 'employee_assigned'): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: 'Employee assigned successfully!',
            confirmButtonText: 'OK'
        });
    </script>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>