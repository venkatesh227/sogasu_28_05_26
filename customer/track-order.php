<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: index.php");
    exit();
}

$orderCode = $_GET['order_code'] ?? '';

if (empty($orderCode)) {
    die("Invalid Order");
}

/*
|--------------------------------------------------------------------------
| FETCH ORDER FROM BOTH TABLES
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

    SELECT
        co.order_code,
        co.status AS final_status,
        co.status_history,
        co.appointment_date,
        co.created_at,
        sc.name AS sub_category_name
    FROM customer_orders co
    LEFT JOIN sub_categories sc
        ON co.sub_category_id = sc.id
    WHERE co.order_code = ?
    AND co.user_id = ?

    UNION ALL

    SELECT
        o.order_code,
        o.order_status AS final_status,
        o.status_history,
        NULL AS appointment_date,
        o.created_at,
        sc.name AS sub_category_name
    FROM orders o
    LEFT JOIN sub_categories sc
        ON o.sub_category_id = sc.id
    WHERE o.order_code = ?

    LIMIT 1
");

$stmt->execute([
    $orderCode,
    $_SESSION['user_id'],
    $orderCode
]);

$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Order not found");
}

$currentStatus = strtolower(trim($order['final_status']));

$steps = ['pending'];

if (!empty($order['status_history'])) {

    $historySteps = array_filter(
        array_map(
            'trim',
            explode(',', strtolower($order['status_history']))
        )
    );

    foreach ($historySteps as $historyStep) {
        if (!in_array($historyStep, $steps, true)) {
            $steps[] = $historyStep;
        }
    }
}

/* ADD CURRENT STATUS IF MISSING */

if (!in_array($currentStatus, $steps, true)) {
    $steps[] = $currentStatus;
}

$pageTitle = "Track Order";
$headerTitle = "Track Order";
$activePage = "orders";

include 'includes/header.php';
?>

<div class="container">

    <div class="card">

        <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:1rem;">

            <div style="display:flex;gap:1rem;">

                <div style="
                    width:60px;
                    height:60px;
                    border-radius:12px;
                    background:var(--background);
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    border:1px solid var(--border);
                    color:var(--primary);
                ">
                    <i class="ri-t-shirt-line" style="font-size:1.5rem;"></i>
                </div>

                <div>

                    <div style="font-size:1.1rem;font-weight:700;">
                        <?= htmlspecialchars($order['sub_category_name']) ?>
                    </div>

                    <div style="font-size:0.85rem;color:var(--text-muted);">
                        Order #<?= htmlspecialchars($order['order_code']) ?>
                    </div>

                </div>

            </div>

            <span class="badge progress">
                <?= ucwords(str_replace('_', ' ', $currentStatus)) ?>
            </span>

        </div>

        <!-- STATUS TRACK -->

        <div style="margin-top:2rem;">

            <div style="
                display:flex;
                justify-content:space-between;
                position:relative;
                margin-bottom:2rem;
                flex-wrap:wrap;
                gap:1rem;
            ">

                <?php foreach ($steps as $index => $step): ?>

                    <?php
                    $lastIndex = count($steps) - 1;

                    $completed = $index < $lastIndex;
                    $current = $index == $lastIndex;
                    ?>

                    <div style="
                        flex:1;
                        min-width:70px;
                        text-align:center;
                        position:relative;
                        opacity:
                        <?= $completed || $current
                            ? '1'
                            : '0.5' ?>; 
                    ">
                        <!-- LINE -->

                        <?php if ($index < count($steps) - 1): ?>

                            <div style="
                            position:absolute;
                            top:18px;
                            left:50%;
                            width:100%;
                            height:4px;
                            background:
                            <?= $index < $lastIndex
                                ? 'var(--primary)'
                                : 'var(--border)' ?>;
                            z-index:1;
                        "></div>

                        <?php endif; ?>

                        <!-- CIRCLE -->

                        <div style="
                            width:36px;
                            height:36px;
                            border-radius:50%;
                            margin:auto;
                            background:
                            <?= $completed || $current
                                ? 'var(--primary)'
                                : '#e5e7eb' ?>;
                            border:2px solid var(--primary);
                            color:
                            <?= $completed || $current
                                ? 'white'
                                : '#9ca3af' ?>;
                            display:flex;
                            align-items:center;
                            justify-content:center;
                            position:relative;
                            z-index:2;
                            font-size:0.8rem;
                            font-weight:700;
                            box-shadow:
                                <?= $current
                                    ? '0 0 0 4px rgba(99,102,241,0.2)'
                                    : 'none' ?>;
                        ">
                            <?= $index + 1 ?>
                        </div>

                        <!-- LABEL -->

                        <div style="
                            margin-top:0.6rem;
                            font-size:0.75rem;
                            font-weight:600;
                            color:
                                <?= $completed || $current
                                    ? 'var(--primary)'
                                    : '#9ca3af' ?>;
                        ">
                            <?= ucwords(str_replace('_', ' ', trim($step))) ?>
                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        </div>

        <!-- DETAILS -->

        <div style="
            background:var(--background);
            padding:1rem;
            border-radius:12px;
            margin-top:1rem;
            font-size:0.9rem;
        ">

            <div style="margin-bottom:0.5rem;">
                <strong>Created:</strong>
                <?= date('d M Y', strtotime($order['created_at'])) ?>
            </div>

            <div>
                <strong>Appointment:</strong>

                <?php if (!empty($order['appointment_date'])): ?>
                    <?= date('d M Y', strtotime($order['appointment_date'])) ?>
                <?php else: ?>
                    Not Available
                <?php endif; ?>
            </div>

        </div>

    </div>

</div>

<?php include 'includes/bottom-nav.php'; ?>