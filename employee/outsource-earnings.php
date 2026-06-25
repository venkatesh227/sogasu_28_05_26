<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT id, first_name, last_name
    FROM employees
    WHERE user_id = ?
    AND employee_type = 'outsource'
    AND is_deleted = 0
");
$stmt->execute([$user_id]);
$employee = $stmt->fetch();

if (!$employee) {
    die("Outsource employee not found");
}

$employee_id = $employee['id'];
$fromDate = $_GET['from_date'] ?? date('Y-m-01'); // current month 1st
$toDate = $_GET['to_date'] ?? date('Y-m-d');    // today

$stmt = $pdo->prepare("
    SELECT 
        id,
        order_code,
        outsource_credit,
        due_date,
        created_at,
        updated_at,
        order_status
    FROM outsource_orders
    WHERE assigned_employee_id = ?
    AND order_status = 'completed'
    AND is_deleted = 0
    AND DATE(created_at) BETWEEN ? AND ?
    ORDER BY created_at DESC
");
$stmt->execute([$employee_id, $fromDate, $toDate]);
$completedOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalEarned = 0;
foreach ($completedOrders as $order) {
    $totalEarned += $order['outsource_credit'];
}

$pageTitle = "My Earnings - Outsource";
$headerTitle = "Earnings";
$activePage = "earnings";

include 'includes/outsource-header.php';
?>

<div class="container" style="padding-bottom:100px;">

    <!-- Summary Card -->
    <div class="card" style="
        background: linear-gradient(135deg, #0f172a, #1e293b);
        color:white;
        border:none;
        padding:2rem 1.5rem;
        border-radius:24px;
        position:relative;
        overflow:hidden;
        box-shadow:0 10px 25px rgba(15,23,42,.4);
    ">
        <div style="
            position:absolute;
            right:-30px;
            top:-30px;
            font-size:8rem;
            opacity:.05;
            transform:rotate(-15deg);
        ">
            <i class="ri-wallet-3-line"></i>
        </div>

        <div style="position:relative;z-index:1;">
            <div style="
                font-size:.85rem;
                text-transform:uppercase;
                letter-spacing:1.5px;
                opacity:.7;
                margin-bottom:.5rem;
            ">
                Total Earnings
            </div>

            <div style="
                font-size:2.75rem;
                font-weight:800;
                line-height:1;
                margin-bottom:1.5rem;
            ">
                ₹<?= number_format($totalEarned, 0) ?>
            </div>

            <div style="display:flex;gap:.75rem;">
                <div style="
                    background:rgba(34,197,94,.2);
                    border:1px solid rgba(34,197,94,.3);
                    padding:.5rem 1rem;
                    border-radius:12px;
                    display:flex;
                    align-items:center;
                    gap:.5rem;
                ">
                    <div style="
                        width:8px;
                        height:8px;
                        background:#22c55e;
                        border-radius:50%;
                    "></div>

                    <span style="font-size:.75rem;font-weight:600;">
                        <?= count($completedOrders) ?> Completed Orders
                    </span>
                </div>
            </div>
        </div>
    </div>
    <form method="GET" class="card" style="padding:1rem;margin-bottom:1rem;">
        <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:end;">

            <div>
                <label>From Date</label><br>
                <input type="date" name="from_date" value="<?= $fromDate ?>"
                    style="padding:.5rem;border:1px solid #ccc;border-radius:8px;">
            </div>

            <div>
                <label>To Date</label><br>
                <input type="date" name="to_date" value="<?= $toDate ?>"
                    style="padding:.5rem;border:1px solid #ccc;border-radius:8px;">
            </div>

            <div>
                <div style="display:flex;gap:.5rem;">

                    <button type="submit"
                        style="padding:.6rem 1rem;border:none;background:#7c3aed;color:white;border-radius:8px;cursor:pointer;">
                        Filter
                    </button>

                    <a href="outsource-earnings.php" style="
                        padding:.6rem 1rem;
                        background:#ef4444;
                        color:white;
                        text-decoration:none;
                        border-radius:8px;
                        display:inline-flex;
                        align-items:center;
                        justify-content:center;
                    ">
                        Reset
                    </a>

                </div>
            </div>

        </div>
    </form>

    <!-- History -->
    <!-- History -->
    <div class="section-title" style="
    margin-top:2rem;
    display:flex;
    justify-content:space-between;
    align-items:center;
">
        <span>Earnings History</span>
        <i class="ri-history-line" style="color:#64748b;"></i>
    </div>

    <?php if (empty($completedOrders)): ?>

        <div class="card" style="
        text-align:center;
        padding:3rem 1.5rem;
        border-style:dashed;
    ">
            <div style="
            width:60px;
            height:60px;
            background:#f1f5f9;
            color:#94a3b8;
            border-radius:50%;
            display:flex;
            align-items:center;
            justify-content:center;
            margin:0 auto 1rem;
        ">
                <i class="ri-money-rupee-circle-line" style="font-size:2rem;"></i>
            </div>

            <div style="color:#64748b;font-size:.9rem;">
                No earnings yet
            </div>
        </div>

    <?php else: ?>

        <div class="card" style="padding:1rem;overflow:auto;">
            <table id="earningsTable" class="display">
                <thead>
                    <tr>
                        <th>S.No</th>
                        <th>Order Code</th>
                        <th>Date</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($completedOrders as $index => $order): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($order['order_code']) ?></td>
                            <td><?= date('d-m-Y', strtotime($order['created_at'])) ?></td>
                            <td>₹<?= number_format($order['outsource_credit'], 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function () {
            $('#earningsTable').DataTable({
                paging: true,
                searching: true,
                ordering: true,
                pageLength: 10
            });
        });
    </script>

    <?php include 'includes/outsource-bottom-nav.php'; ?>