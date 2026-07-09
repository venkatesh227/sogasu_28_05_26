<?php
session_start();
require '../includes/db.php';
// Fetch logged in customer details
$customerId = $_SESSION['user_id'] ?? 0;
$pageTitle = "Home - Sogasu";
$headerTitle = "Sogasu";
$activePage = "dashboard";
if (
    isset($_POST['mark_holiday_read']) &&
    $_POST['mark_holiday_read'] == '1'
) {

    $stmt = $pdo->prepare("

        UPDATE appointment_notifications
        SET status = 'read'
        WHERE user_id = ?
        AND message LIKE '%holiday%'
        AND status = 'pending'

    ");

    $stmt->execute([
        $_SESSION['user_id']
    ]);

    exit;
}
include 'includes/header.php';
?>
<?php

$stmt = $pdo->prepare("
    SELECT 
        c.id AS category_id,
        c.category_name,
        c.icon,
        s.id AS service_id,
        s.service_name,
        s.base_price,
        s.price_type
    FROM categories c
    LEFT JOIN services s 
        ON s.category_id = c.id
        AND s.status = 'active'
        AND s.is_deleted = 0
    WHERE c.status = 'active'
    ORDER BY c.category_name ASC, s.service_name ASC
");

$stmt->execute();

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Group services by category */

$categories = [];

foreach ($data as $row) {

    $catId = $row['category_id'];

    if (!isset($categories[$catId])) {

        $categories[$catId] = [
            'category_name' => $row['category_name'],
            'icon' => $row['icon'],
            'services' => []
        ];
    }

    if (!empty($row['service_id'])) {

        $categories[$catId]['services'][] = [
            'service_name' => $row['service_name'],
            'base_price' => $row['base_price'],
            'price_type' => $row['price_type']
        ];
    }
}

?>

<div class="container">

    <!-- Hero Banner -->
    <div
        style="background: linear-gradient(135deg, var(--primary), #f472b6); color: white; border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; position: relative; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(219, 39, 119, 0.3);">
        <div style="position: relative; z-index: 10;">
            <div style="font-size: 0.9rem; margin-bottom: 0.5rem; opacity: 0.9;">New Collection</div>
            <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1rem; line-height: 1.2;">Design Your
                Dream<br>Outfit Today</h2>
            <a href="new-order.php"
                style="background: white; color: var(--primary); padding: 0.5rem 1rem; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; display: inline-block;">Book
                Appointment</a>
        </div>
        <i class="ri-t-shirt-air-line"
            style="position: absolute; right: -20px; bottom: -20px; font-size: 8rem; opacity: 0.2; transform: rotate(-15deg);"></i>
    </div>
    <?php
    $notificationStmt = $pdo->prepare("

    SELECT *
    FROM appointment_notifications
    WHERE user_id = ?
    ORDER BY id DESC

");

    $notificationStmt->execute([
        $_SESSION['user_id']
    ]);

    $notifications = $notificationStmt->fetchAll();
    $showHolidayPopup = false;

    foreach ($notifications as $notification) {

        if (
            $notification['status'] === 'pending' &&
            stripos($notification['message'], 'holiday') !== false
        ) {

            $showHolidayPopup = true;
            break;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FETCH ACTIVE ORDERS
    |--------------------------------------------------------------------------
    */

    $activeOrdersStmt = $pdo->prepare("

    SELECT
        co.order_code,
        co.status AS final_status,
        co.status_history,
        co.created_at,
        sc.name AS sub_category_name

    FROM customer_orders co

    LEFT JOIN sub_categories sc
        ON co.sub_category_id = sc.id

    WHERE co.user_id = :user_id
    AND LOWER(co.status) != 'completed'
    AND co.order_code IS NOT NULL
    AND co.order_code != ''
    AND (
        co.slot_status IS NULL
        OR LOWER(co.slot_status) != 'rejected'
    )

    UNION

    SELECT
        o.order_code,
        o.order_status AS final_status,
        o.status_history,
        o.created_at,
        sc.name AS sub_category_name

    FROM orders o

    LEFT JOIN sub_categories sc
        ON o.sub_category_id = sc.id

    WHERE o.customer_id = :customer_id
    AND LOWER(o.order_status) != 'completed'
    AND o.order_code IS NOT NULL
    AND o.order_code != ''

    ORDER BY created_at DESC

");

    $activeOrdersStmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':customer_id' => $customerId
    ]);

    $activeOrders = $activeOrdersStmt->fetchAll(PDO::FETCH_ASSOC);

    ?>
    <!-- Active Orders -->
    <div class="section-title">
        <span>Active Orders</span>
        <a href="my-orders.php" style="color: var(--primary); font-size: 0.85rem; text-decoration: none;">View All</a>
    </div>

    <?php if (!empty($activeOrders)): ?>

        <?php foreach ($activeOrders as $order): ?>

            <?php

            $currentStatus = strtolower(trim($order['final_status']));

            $steps = [];

            if (!empty($order['status_history'])) {

                $steps = array_filter(array_map('trim', explode(',', $order['status_history'])));

                $steps = array_unique($steps);

                if (!in_array($currentStatus, $steps)) {
                    $steps[] = $currentStatus;
                }

            } else {

                $steps = ['pending'];

            }

            $totalSteps = count($steps);
            $lastIndex = $totalSteps - 1;

            ?>

            <a href="track-order.php?order_code=<?= urlencode($order['order_code']) ?>"
                style="text-decoration:none;color:inherit;display:block;">

                <div class="card" style="margin-bottom:1rem;">

                    <div style="display:flex;gap:1rem;margin-bottom:1rem;">

                        <div style="
                        width:60px;
                        height:60px;
                        background:var(--background);
                        border-radius:8px;
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        color:var(--text-muted);
                    ">
                            <i class="ri-t-shirt-line" style="font-size:1.5rem;"></i>
                        </div>

                        <div style="flex:1;">

                            <div style="
                            display:flex;
                            justify-content:space-between;
                            margin-bottom:0.25rem;
                            gap:1rem;
                        ">

                                <div style="
                                font-weight:600;
                                color:var(--text-main);
                            ">
                                    <?= htmlspecialchars($order['sub_category_name'] ?: 'Custom Order') ?>
                                </div>

                                <span class="badge progress">
                                    <?= ucwords(str_replace('_', ' ', $currentStatus)) ?>
                                </span>

                            </div>

                            <div style="
                            color:var(--text-muted);
                            font-size:0.85rem;
                        ">
                                Order #<?= htmlspecialchars($order['order_code']) ?>

                            </div>

                        </div>

                    </div>

                    <!-- PROGRESS BAR -->

                    <div style="
                    display:flex;
                    align-items:center;
                    gap:0.5rem;
                    margin-top:1rem;
                ">

                        <?php foreach ($steps as $index => $step): ?>

                            <?php

                            $completed = $index < $lastIndex;
                            $current = $index == $lastIndex;

                            ?>

                            <div style="
                            flex:1;
                            height:4px;
                            background:var(--border);
                            border-radius:2px;
                            overflow:hidden;
                        ">

                                <div style="
                                width:
                                <?= $completed
                                    ? '100%'
                                    : ($current ? '50%' : '0%') ?>;

                                height:100%;

                                background:
                                <?= $completed
                                    ? 'var(--success)'
                                    : ($current ? 'var(--primary)' : 'transparent') ?>;

                                border-radius:2px;
                            "></div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                    <!-- STEP LABELS -->

                    <div style="
                    display:flex;
                    justify-content:space-between;
                    margin-top:0.5rem;
                    font-size:0.75rem;
                    color:var(--text-muted);
                    gap:0.5rem;
                    flex-wrap:wrap;
                ">

                        <?php foreach ($steps as $index => $step): ?>

                            <span style="
                            <?= $index == $lastIndex
                                ? 'color:var(--primary);font-weight:600;'
                                : '' ?>
                        ">
                                <?= ucwords(str_replace('_', ' ', $step)) ?>
                            </span>

                        <?php endforeach; ?>

                    </div>

                </div>

            </a>

        <?php endforeach; ?>

    <?php else: ?>

        <div class="card" style="text-align:center;padding:2rem;">

            <i class="ri-shopping-bag-line" style="
                font-size:2rem;
                color:var(--text-muted);
                margin-bottom:0.5rem;
                display:block;
            ">
            </i>

            <div style="font-weight:600;margin-bottom:0.25rem;">
                No Active Orders
            </div>

            <div style="
            font-size:0.9rem;
            color:var(--text-muted);
        ">
                Your active orders will appear here.
            </div>

        </div>

    <?php endif; ?>


    <!-- Services / Categories -->
    <div class="section-title">Explore Services</div>

    <div style="
        display:grid;
        grid-template-columns:repeat(auto-fit, minmax(320px, 340px));
        justify-content:start;
        column-gap:0.75rem;
        row-gap:0.85rem;
    ">

        <?php foreach ($categories as $category): ?>

            <?php if (empty($category['services']))
                continue; ?>

            <div class="card" style="
        padding:0;
        overflow:hidden;
        border-radius:16px;
        background:white;
    ">

                <!-- Category Header -->

                <div style="
            display:flex;
            align-items:center;
            gap:0.75rem;
            padding:0.85rem 1rem;
            border-bottom:1px solid var(--border);
            background:#fff;
        ">

                    <div style="
                width:36px;
                height:36px;
                border-radius:50%;
                background:#fdf2f8;
                color:var(--primary);
                display:flex;
                align-items:center;
                justify-content:center;
                font-size:1rem;
                flex-shrink:0;
            ">

                        <i class="<?= !empty($category['icon'])
                            ? htmlspecialchars($category['icon'])
                            : 'ri-function-line' ?>">
                        </i>

                    </div>

                    <div style="
                font-weight:700;
                font-size:1rem;
                color:var(--text-main);
            ">
                        <?= htmlspecialchars($category['category_name']) ?>
                    </div>

                </div>

                <!-- Services -->

                <?php if (!empty($category['services'])): ?>

                    <?php foreach ($category['services'] as $service): ?>

                        <div style="
                    display:flex;
                    justify-content:space-between;
                    align-items:center;
                    padding:0.75rem 1rem;
                    border-bottom:1px solid #f1f5f9;
                    background:white;
                ">

                            <div style="
                        font-size:0.88rem;
                        color:var(--text-main);
                        font-weight:500;
                    ">
                                <?= htmlspecialchars($service['service_name']) ?>
                            </div>

                            <div style="
                        color:var(--primary);
                        font-weight:700;
                        font-size:0.9rem;
                        white-space:nowrap;
                    ">

                                <?php if ($service['price_type'] === 'starting'): ?>

                                    Starting ₹<?= number_format($service['base_price'], 0) ?>

                                <?php else: ?>

                                    ₹<?= number_format($service['base_price'], 0) ?>

                                <?php endif; ?>

                            </div>

                        </div>

                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php endforeach; ?>

    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>

    function acceptSlot(id) {
        fetch('accept-slot.php', {

            method: 'POST',

            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },

            body: 'id=' + id

        })
            .then(res => res.json())
            .then(data => {

                if (data.success) {

                    location.reload();

                }

            });
    }

    function rejectSlot(id) {
        fetch('reject-slot.php', {

            method: 'POST',

            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },

            body: 'id=' + id

        })
            .then(res => res.json())
            .then(data => {

                if (data.success) {

                    location.reload();

                }

            });
    }

</script>
<script>

    function updateSlot(id, action) {
        fetch('update-slot-status.php', {

            method: 'POST',

            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },

            body:
                'id=' + id +
                '&action=' + action

        })
            .then(res => res.json())
            .then(data => {

                if (data.success) {

                    location.reload();

                }

            });
    }

</script>
<?php if ($showHolidayPopup): ?>

    <script>

        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'mark_holiday_read=1'
        });

    </script>

<?php endif; ?>

<?php if (!empty($_SESSION['success_message'])): ?>

    <script>

        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: '<?= $_SESSION['success_message']; ?>',
            confirmButtonText: 'OK'
        });

    </script>

    <?php unset($_SESSION['success_message']); ?>

<?php endif; ?>
<?php if (!empty($_SESSION['appointment_success'])): ?>

<script>
document.addEventListener('DOMContentLoaded', function () {

    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: <?= json_encode($_SESSION['appointment_success']) ?>,
        confirmButtonColor: '#d63384'
    });

});
</script>

<?php unset($_SESSION['appointment_success']); ?>

<?php endif; ?>
<?php include 'includes/bottom-nav.php'; ?>