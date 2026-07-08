<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Map tabs to statuses
$statusQuery = $pdo->query("SHOW COLUMNS FROM orders LIKE 'order_status'");
$statusRow = $statusQuery->fetch(PDO::FETCH_ASSOC);

preg_match("/^enum\((.*)\)$/", $statusRow['Type'], $matches);

$statuses = [];

if (!empty($matches[1])) {
    $statuses = array_map(function ($v) {
        return trim($v, "'");
    }, explode(",", $matches[1]));
}

$activeTab = $_GET['tab'] ?? 'pending';

$pageTitle = "Task Management - Sogasu";
$activePage = "tasks";

// Fetch Employees for Assignment
$inhouseEmployees = $pdo->query("
SELECT id, first_name, last_name, job_role
FROM employees
WHERE is_deleted = 0
AND status = 1
AND employee_type='inhouse'
AND job_role NOT IN ('Supervisor','Manager')
ORDER BY first_name
")->fetchAll();

$outsourceEmployees = $pdo->query("
SELECT id, first_name, last_name, job_role
FROM employees
WHERE is_deleted = 0
AND status = 1
AND employee_type='outsource'
ORDER BY first_name
")->fetchAll();

// Fetch Real Tasks (Orders in this status)
$stmt = $pdo->prepare("
SELECT
o.id,
'orders' source,
o.order_code,
o.order_status,
o.assigned_employee_id,
o.due_date,
c.first_name cust_first,
c.last_name cust_last,
sc.name garment

FROM orders o

LEFT JOIN customers c
ON c.id=o.customer_id

LEFT JOIN sub_categories sc
ON sc.id=o.sub_category_id

WHERE o.order_status=?
AND o.is_deleted=0

UNION ALL

SELECT
co.id,
'customer_orders',
co.order_code,
co.status,
co.assigned_employee_id,
co.appointment_date,
c.first_name,
c.last_name,
sc.name

FROM customer_orders co

LEFT JOIN customers c
ON c.user_id=co.user_id

LEFT JOIN sub_categories sc
ON sc.id=co.sub_category_id

WHERE co.status=?
AND co.slot_status!='rejected'
AND co.is_deleted=0

UNION ALL

SELECT
oo.id,
'outsource_orders',
oo.order_code,
oo.order_status,
oo.assigned_employee_id,
oo.due_date,
'Outsource Order',
'',
sc.name

FROM outsource_orders oo

LEFT JOIN sub_categories sc
ON sc.id=oo.sub_category_id

WHERE oo.order_status=?
AND oo.is_deleted=0

ORDER BY due_date ASC
");

$stmt->execute([
    $activeTab,
    $activeTab,
    $activeTab
]);

$tasksList = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div>

        <!-- Standard Header -->
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Task Management</h2>
                <p style="color: #64748b; margin-top: 0.25rem;">Monitor workshop operations and track employee
                    productivity</p>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <button class="btn btn-light" onclick="location.reload()"
                    style="background: white; border: 1px solid #e2e8f0; color: #475569; padding: 10px 20px; border-radius: 8px; font-weight: 600;">
                    <i class="ri-refresh-line"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Task Categories (Functional Tabs) -->
        <style>
            .status-tab {
                padding: 6px 16px;
                border-radius: 20px;
                font-size: 0.85rem;
                font-weight: 600;
                text-decoration: none;
                transition: all 0.2s ease;
                white-space: nowrap;
            }

            .status-tab:hover {
                transform: translateY(-1px);
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            }
        </style>
        <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem; overflow-x: auto; padding-bottom: 0.5rem; ">
            <?php foreach ($statuses as $status):

                $label = ucwords(str_replace('_', ' ', $status));

                $count = $pdo->query("
                SELECT
                (
                SELECT COUNT(*)
                FROM orders
                WHERE order_status='$status'
                AND is_deleted=0
                )

                +

                (
                SELECT COUNT(*)
                FROM customer_orders
                WHERE status='$status'
                AND slot_status!='rejected'
                AND is_deleted=0
                )

                +

                (
                SELECT COUNT(*)
                FROM outsource_orders
                WHERE order_status='$status'
                AND is_deleted=0
                )
                ")->fetchColumn();

                ?>

                <a href="?tab=<?= urlencode($status) ?>" class="status-tab" style="background:<?= ($activeTab == $status) ? '#4f46e5' : 'white' ?>;
                color:<?= ($activeTab == $status) ? 'white' : '#64748b' ?>;
                border:1px solid <?= ($activeTab == $status) ? '#4f46e5' : '#e2e8f0' ?>;">

                    <?= $label ?>

                    (<?= $count ?>)

                </a>

            <?php endforeach; ?>
        </div>

        <div style="display: grid; grid-template-columns: 2.5fr 1fr; gap: 1.5rem;">

            <!-- Active Tasks Card -->
            <div class="table-container">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 1rem;">
                    <h3 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0;">Active
                        <?= ucwords(str_replace('_', ' ', $activeTab)) ?> Tasks
                    </h3>
                </div>

                <table id="tasksTable" class="table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer / Garment</th>
                            <th>Assigned To</th>
                            <th>Due Date</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($tasksList)): ?>
                            <?php foreach ($tasksList as $t): ?>
                                <tr>
                                    <td style="padding: 1rem; font-weight: 700; color: #4f46e5;">
                                        #<?= htmlspecialchars($t['order_code']) ?></td>
                                    <td style="padding: 1rem;">
                                        <div style="font-weight: 600; color: #1e293b;">
                                            <?php
                                            $name = trim(($t['cust_first'] ?? '') . ' ' . ($t['cust_last'] ?? ''));
                                            ?>

                                            <?= htmlspecialchars($name ?: 'N/A') ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: #64748b;">
                                            <?= htmlspecialchars($t['garment'] ?? 'General') ?>
                                        </div>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <select class="form-select assignment-select" data-id="<?= $t['id'] ?>"
                                            data-source="<?= $t['source'] ?>" style="font-size:0.8rem;
                                            padding:6px 10px;
                                            height:38px;
                                            border-radius:8px;
                                            width:300px;
                                            min-width:300px;
                                            max-width:100%;">
                                            <option value="">Unassigned</option>
                                            <?php
                                            $list = ($t['source'] == 'outsource_orders')
                                                ? $outsourceEmployees
                                                : $inhouseEmployees;

                                            foreach ($list as $emp):
                                                ?>
                                                <option value="<?= $emp['id'] ?>" <?= ($t['assigned_employee_id'] == $emp['id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($emp['first_name'].' '.$emp['last_name'].' - '.$emp['job_role']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td style="padding: 1rem; font-weight: 600; color: #64748b;">
                                        <i class="ri-calendar-line"></i><?= !empty($t['due_date'])
                                            ? date('d M Y', strtotime($t['due_date']))
                                            : '-' ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: right;">
                                        <button class="btn btn-sm btn-done" data-id="<?= $t['id'] ?>"
                                            data-source="<?= $t['source'] ?>"
                                            style="background: #10b981; color: white; border: none; padding: 6px 12px;
                                            border-radius: 6px; font-size: 0.75rem; font-weight: 700; cursor: pointer;">
                                            MARK DONE
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="5" style="text-align:center;padding:25px;">
                                    No Tasks Found
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>
                </table>
            </div>

            <!-- Productivity Stats Card (Dynamic-ish) -->
            <div class="table-container" style="align-self: start;">
                <h3
                    style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="ri-line-chart-line" style="color: #6366f1;"></i> Productivity
                </h3>

                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <?php

                    $totalTasks = $pdo->query("
                    SELECT
                    (
                    SELECT COUNT(*)
                    FROM orders
                    WHERE is_deleted=0
                    )

                    +

                    (
                    SELECT COUNT(*)
                    FROM customer_orders
                    WHERE slot_status!='rejected'
                    AND is_deleted=0
                    )

                    +

                    (
                    SELECT COUNT(*)
                    FROM outsource_orders
                    WHERE is_deleted=0
                    )
                    ")->fetchColumn();


                    $completedTasks = $pdo->query("
                    SELECT
                    (
                    SELECT COUNT(*)
                    FROM orders
                    WHERE order_status IN ('ready','completed','delivered')
                    AND is_deleted=0
                    )

                    +

                    (
                    SELECT COUNT(*)
                    FROM customer_orders
                    WHERE status IN ('ready','completed','delivered')
                    AND slot_status!='rejected'
                    AND is_deleted=0
                    )

                    +

                    (
                    SELECT COUNT(*)
                    FROM outsource_orders
                    WHERE order_status='completed'
                    AND is_deleted=0
                    )
                    ")->fetchColumn();


                    $progress = 0;

                    if ($totalTasks > 0) {
                        $progress = round(($completedTasks / $totalTasks) * 100);
                    }


                    $totalActive = $pdo->query("
                    SELECT
                    (
                    SELECT COUNT(*)
                    FROM orders
                    WHERE order_status IN ('processing','pattern_making','cutting','embroidery','stitching','finishing')
                    AND is_deleted=0
                    )

                    +

                    (
                    SELECT COUNT(*)
                    FROM customer_orders
                    WHERE status IN ('processing','pattern_making','cutting','embroidery','stitching','finishing')
                    AND slot_status!='rejected'
                    AND is_deleted=0
                    )

                    +

                    (
                    SELECT COUNT(*)
                    FROM outsource_orders
                    WHERE order_status IN ('accepted','approved','in progress')
                    AND is_deleted=0
                    )
                    ")->fetchColumn();


                    $totalToday = $pdo->query("
                    SELECT
                    (
                    SELECT COUNT(*)
                    FROM orders
                    WHERE order_status='ready'
                    AND DATE(updated_at)=CURDATE()
                    AND is_deleted=0
                    )

                    +

                    (
                    SELECT COUNT(*)
                    FROM customer_orders
                    WHERE status='ready'
                    AND DATE(updated_at)=CURDATE()
                    AND slot_status!='rejected'
                    AND is_deleted=0
                    )

                    +

                    (
                    SELECT COUNT(*)
                    FROM outsource_orders
                    WHERE order_status='completed'
                    AND DATE(updated_at)=CURDATE()
                    AND is_deleted=0
                    )
                    ")->fetchColumn();

                    ?>
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span style="font-size: 0.85rem; color: #64748b; font-weight: 600;">Overall Progress</span>
                            <span style="font-size: 0.85rem; color: #1e293b; font-weight: 800;"><?= $progress ?>%</span>
                        </div>
                        <div style="height: 6px; background: #f1f5f9; border-radius: 10px; overflow: hidden;">
                            <div style="width:<?= $progress ?>%; height:100%; background:#6366f1;"></div>
                        </div>
                    </div>

                    <div style="background: #f8fafc; padding: 1rem; border-radius: 10px; border: 1px solid #e2e8f0;">
                        <div
                            style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 0.5rem;">
                            Workshop Stats</div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-size: 1.25rem; font-weight: 800; color: #1e293b;"><?= $totalActive ?>
                                </div>
                                <div style="font-size: 0.7rem; color: #64748b;">In Production</div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 1.25rem; font-weight: 800; color: #10b981;"><?= $totalToday ?>
                                </div>
                                <div style="font-size: 0.7rem; color: #64748b;">Ready Today</div>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #f1f5f9;">
                        <h4 style="font-size: 0.85rem; font-weight: 700; color: #1e293b; margin-bottom: 1rem;">Task
                            Distribution</h4>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <?php foreach ($statuses as $status):

                                $label = ucwords(str_replace('_', ' ', $status));

                                $count = $pdo->query("
                                SELECT
                                (
                                SELECT COUNT(*)
                                FROM orders
                                WHERE order_status='$status'
                                AND is_deleted=0
                                )

                                +

                                (
                                SELECT COUNT(*)
                                FROM customer_orders
                                WHERE status='$status'
                                AND slot_status!='rejected'
                                AND is_deleted=0
                                )

                                +

                                (
                                SELECT COUNT(*)
                                FROM outsource_orders
                                WHERE order_status='$status'
                                AND is_deleted=0
                                )
                                ")->fetchColumn();
                                ?>
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <span style="font-size: 0.8rem; color: #475569; font-weight: 600;"><?= $label ?></span>
                                    <span style="font-size: 0.75rem; font-weight: 700; color: #1e293b;"><?= $count ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php include __DIR__ . '/includes/datatable.php'; ?>

<script>
    $(document).ready(function () {
        initializeDataTable('tasksTable', '<?= $activeTab ?> Tasks Report');

        // Handle Employee Assignment
        $('.assignment-select').on('change', function () {
            const orderId = $(this).data('id');
            const source = $(this).data('source');
            const empId = $(this).val();

            $.ajax({
                url: 'update-task.php',
                method: 'POST',
                data: {
                    action: 'assign',
                    order_id: orderId,
                    source: source,
                    employee_id: empId
                },
                success: function (response) {
                    const res = JSON.parse(response);
                    if (res.success) {
                        Swal.fire({ icon: 'success', title: 'Assigned', text: 'Employee assigned successfully', timer: 1000, showConfirmButton: false });
                    }
                }
            });
        });

        // Handle Mark Done
        $('.btn-done').on('click', function () {
            const orderId = $(this).data('id');
            const source = $(this).data('source');
            const row = $(this).closest('tr');

            Swal.fire({
                title: 'Move to next stage?',
                text: "This will move the task to the next logical production stage.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                confirmButtonText: 'Yes, Mark Done!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'update-task.php',
                        method: 'POST',
                        data: {
                            action: 'done',
                            order_id: orderId,
                            source: source
                        },
                        success: function (response) {
                            const res = JSON.parse(response);
                            if (res.success) {
                                row.fadeOut(300, function () { location.reload(); });
                            }
                        }
                    });
                }
            });
        });
    });
</script>

<?php include 'includes/footer.php'; ?>