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

$stmt = $pdo->prepare("
    SELECT 
        id,
        order_code,
        outsource_credit,
        due_date,
        updated_at,
        order_status
    FROM outsource_orders
    WHERE assigned_employee_id = ?
    AND order_status = 'completed'
    AND is_deleted = 0
    ORDER BY updated_at DESC
");
$stmt->execute([$employee_id]);
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
        <div class="card" style="padding:0;overflow:hidden;border-radius:20px;">

            <?php foreach ($completedOrders as $index => $order): ?>
                <div style="
                    padding:1.25rem;
                    border-bottom: <?= ($index === count($completedOrders)-1) ? 'none' : '1px solid #f1f5f9' ?>;
                    display:flex;
                    justify-content:space-between;
                    align-items:center;
                ">
                    <div style="display:flex;gap:1rem;align-items:center;">
                        <div style="
                            width:48px;
                            height:48px;
                            border-radius:14px;
                            display:flex;
                            align-items:center;
                            justify-content:center;
                            background:#f0fdf4;
                            color:#15803d;
                        ">
                            <i class="ri-money-rupee-circle-fill" style="font-size:1.4rem;"></i>
                        </div>

                        <div>
                            <div style="
                                font-weight:700;
                                font-size:1rem;
                                color:#1e293b;
                            ">
                                <?= htmlspecialchars($order['order_code']) ?>
                            </div>

                            <div style="
                                font-size:.75rem;
                                color:#64748b;
                                font-weight:500;
                            ">
                                Completed:
                                <?= date('d M Y', strtotime($order['updated_at'])) ?>
                            </div>
                        </div>
                    </div>

                    <div style="text-align:right;">
                        <div style="
                            font-weight:800;
                            font-size:1.1rem;
                            color:#22c55e;
                        ">
                            + ₹<?= number_format($order['outsource_credit'], 0) ?>
                        </div>

                        <div style="
                            font-size:.65rem;
                            font-weight:700;
                            color:#10b981;
                            text-transform:uppercase;
                        ">
                            Earned
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>
    <?php endif; ?>
</div>

<?php include 'includes/outsource-bottom-nav.php'; ?>