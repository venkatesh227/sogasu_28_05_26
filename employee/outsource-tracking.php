<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

/* supervisor employee id */
$stmt = $pdo->prepare("
    SELECT id, first_name, last_name 
    FROM employees 
    WHERE user_id=? 
    LIMIT 1
");
$stmt->execute([$user_id]);
$supervisor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$supervisor) {
    die("Supervisor not found");
}

$supervisor_id = $supervisor['id'];

/* Orders fetch */
$stmt = $pdo->prepare("
    SELECT 
    oo.*, 
    CONCAT(e.first_name,' ',e.last_name) as employee_name
    FROM outsource_orders oo
    LEFT JOIN employees e ON oo.assigned_employee_id = e.id
    WHERE oo.supervisor_id = ?
    AND oo.is_deleted = 0
    ORDER BY oo.created_at DESC
");
$stmt->execute([$supervisor_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT 
        e.id,
        e.first_name,
        e.last_name,
        e.phone,
        COUNT(oo.id) as total_orders,
        SUM(CASE WHEN oo.order_status='completed' THEN 1 ELSE 0 END) as completed_orders
    FROM employees e
    LEFT JOIN outsource_orders oo 
        ON oo.assigned_employee_id = e.id
        AND oo.is_deleted = 0
    WHERE e.supervisor_id = ?
    AND e.employee_type = 'outsource'
    AND e.is_deleted = 0
    GROUP BY e.id
");
$stmt->execute([$supervisor_id]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Stats */
$total_orders = count($orders);
$pending = 0;
$approved = 0;
$in_progress = 0;
$completed = 0;

foreach ($orders as $order) {
    switch ($order['order_status']) {
        case 'pending':
            $pending++;
            break;
        case 'approved':
            $approved++;
            break;
        case 'in progress':
            $in_progress++;
            break;
        case 'completed':
            $completed++;
            break;
    }
}

/* Employee summary */
$employeeSummary = [];

foreach ($orders as $order) {
    $emp = $order['employee_name'] ?: 'Unassigned';

    if (!isset($employeeSummary[$emp])) {
        $employeeSummary[$emp] = [
            'total' => 0,
            'completed' => 0
        ];
    }

    $employeeSummary[$emp]['total']++;

    if ($order['order_status'] == 'completed') {
        $employeeSummary[$emp]['completed']++;
    }
}
$pageTitle = "Outsource Tracking";
$headerTitle = "Outsource Tracking";
$activePage = "tasks"; // or outsource if later nav add chesthav
include 'includes/header.php';
?>
<style>
    .tab-btn {
        border: none;
        padding: 10px 16px;
        border-radius: 12px;
        background: #fce7f3;
        color: #be185d;
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
    }

    .badge {
        padding: 6px 12px;
        border-radius: 20px;
        color: white;
        font-size: 12px;
        font-weight: 600;
    }

    .pending {
        background: #f59e0b;
    }

    .accepted {
        background: #3b82f6;
    }

    .approved {
        background: #8b5cf6;
    }

    .completed {
        background: #10b981;
    }

    .rejected {
        background: #ef4444;
    }

    .progress {
        width: 120px;
        height: 10px;
        background: #e5e7eb;
        border-radius: 20px;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #ec4899, #8b5cf6);
        border-radius: 20px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th {
        background: #f8fafc;
        color: #475569;
        font-size: 14px;
        padding: 14px;
        text-align: left;
    }

    td {
        padding: 14px;
        border-top: 1px solid #e2e8f0;
    }

    .outsource-card {
        background: white;
        border-radius: 16px;
        padding: 1rem;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
    }

    .active-tab {
        background: linear-gradient(135deg, #ec4899, #be185d) !important;
        color: white !important;
        box-shadow: 0 4px 12px rgba(236, 72, 153, .3);
    }
</style>
<div class="container" style="padding-bottom:90px;">

    <div class="section-title">Outsource Tracking Dashboard</div>

    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:1rem;">
        <div class="outsource-card">
            <h4>Total Orders</h4>
            <h2><?= $total_orders ?></h2>
        </div>
        <div class="outsource-card">
            <h4>Pending</h4>
            <h2><?= $pending ?></h2>
        </div>
        <div class="outsource-card">
            <h4>In Progress</h4>
            <h2><?= $in_progress ?></h2>
        </div>
        <div class="outsource-card">
            <h4>Completed</h4>
            <h2><?= $completed ?></h2>
        </div>
    </div>

    <div style="display:flex;gap:10px;margin:1rem 0;">
        <button class="tab-btn active-tab" onclick="showTab('orders-tab', this)">Orders</button>

        <button class="tab-btn" onclick="showTab('employees-tab', this)">Employees</button>
    </div>
    <div id="orders-tab">

        <div class="section-title">Orders Live Status</div>

        <div class="outsource-card" style="overflow:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Employee</th>
                        <th>Status</th>
                        <th>Due Date</th>
                        <th>Progress</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>

                    <?php foreach ($orders as $row): ?>
                        <?php
                        $progress = 0;
                        switch ($row['order_status']) {
                            case 'pending':
                                $progress = 20;
                                break;
                            case 'accepted':
                                $progress = 40;
                                break;
                            case 'approved':
                                $progress = 75;
                                break;
                            case 'completed':
                                $progress = 100;
                                break;
                            case 'rejected':
                                $progress = 0;
                                break;
                        }
                        ?>
                        <tr>
                            <td><?= $row['order_code'] ?></td>
                            <td><?= $row['employee_name'] ?? 'N/A' ?></td>
                            <td>
                                <span class="badge <?= $row['order_status'] ?>">
                                    <?= ucfirst($row['order_status']) ?>
                                </span>
                            </td>
                            <td><?= $row['due_date'] ?></td>
                            <td>
                                <div class="progress">
                                    <div class="progress-fill" style="width:<?= $progress ?>%"></div>
                                </div>
                                <?= $progress ?>%
                            </td>
                            <td>₹<?= $row['total_amount'] ?></td>
                        </tr>
                    <?php endforeach; ?>

                </tbody>
            </table>
        </div>
    </div>
    <div id="employees-tab" style="display:none;">
        <div class="section-title">Employees Under Supervisor</div>

        <div class="outsource-card" style="overflow:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Phone</th>
                        <th>Total Orders</th>
                        <th>Completed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $emp): ?>
                        <tr>
                            <td><?= $emp['first_name'] . ' ' . $emp['last_name'] ?></td>
                            <td><?= $emp['phone'] ?: 'N/A' ?></td>
                            <td><?= $emp['total_orders'] ?></td>
                            <td><?= $emp['completed_orders'] ?? 0 ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function showTab(tabId, clickedBtn) {
        document.getElementById('orders-tab').style.display = 'none';
        document.getElementById('employees-tab').style.display = 'none';

        document.getElementById(tabId).style.display = 'block';

        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active-tab');
        });

        clickedBtn.classList.add('active-tab');
    }
</script>
<?php include 'includes/bottom-nav.php'; ?>