<?php
session_start();
require '../includes/db.php';
$userId = $_SESSION['user_id'];
$customerStmt = $pdo->prepare("
    SELECT id 
    FROM customers 
    WHERE user_id = ?
");

$customerStmt->execute([$userId]);

$customerId = $customerStmt->fetchColumn();


$stmt = $pdo->prepare("
    SELECT 
        co.id,
        co.order_code,
        co.category_id,
        co.sub_category_id,
        co.created_at,
        co.appointment_date,
        co.status AS final_status,
        c.category_name,
        sc.name AS sub_category_name,
        'customer_order' AS order_type
    FROM customer_orders co
    LEFT JOIN categories c ON co.category_id = c.id
    LEFT JOIN sub_categories sc ON co.sub_category_id = sc.id
    WHERE co.user_id = ?
    AND co.is_deleted = 0

    UNION ALL

    SELECT 
    NULL AS id,
    o.order_code,
    o.category_id,
    o.sub_category_id,
    o.created_at,
    NULL AS appointment_date,
    o.order_status AS final_status,
    c.category_name,
    sc.name AS sub_category_name,
    'admin_order' AS order_type
FROM orders o
LEFT JOIN categories c ON o.category_id = c.id
LEFT JOIN sub_categories sc ON o.sub_category_id = sc.id
WHERE o.customer_id = ?
");
$stmt->execute([$userId, $customerId]);

$orders = $stmt->fetchAll();
$statusOrders = [];
$statusOrders['all'] = $orders;
$allStatuses = [
    'pending',
    'processing',
    'pattern_making',
    'cutting',
    'embroidery',
    'stitching',
    'finishing',
    'ready',
    'completed',
    'delivered'
];

$tempOrders = $orders;

$statusOrders = [
    'all' => $tempOrders
];

foreach ($allStatuses as $status) {
    $statusOrders[$status] = [];
}

foreach ($orders as $order) {

    $status = !empty($order['final_status'])
        ? strtolower(trim($order['final_status']))
        : 'pending';

    if (isset($statusOrders[$status])) {
        $statusOrders[$status][] = $order;
    }
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$pageTitle = "My Orders - Sogasu";
$headerTitle = "My Orders";
$activePage = "orders";
include 'includes/header.php';
?>
<?php if (!empty($_SESSION['order_success'])): ?>

    <script>

        document.addEventListener('DOMContentLoaded', function () {

            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: <?= json_encode($_SESSION['order_success']) ?>,
                confirmButtonColor: '#d63384'
            });

        });

    </script>

    <?php unset($_SESSION['order_success']); ?>

<?php endif; ?>

<div
    style="background: var(--surface); padding: 0.75rem 1.25rem; border-bottom: 1px solid var(--border); position: sticky; top: 58px; z-index: 100; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
    <div
        style="display: flex; gap: 0.6rem; overflow-x: auto; padding-bottom: 2px; -ms-overflow-style: none; scrollbar-width: none;">
        <?php
        $firstTab = true;

        foreach ($statusOrders as $status => $statusData):
            ?>

            <button onclick="filterOrders('<?= $status ?>')" id="tab-<?= $status ?>"
                class="order-tab <?= $firstTab ? 'active' : '' ?>">

                <?= ucwords(str_replace('_', ' ', $status)) ?>
                (<?= count($statusData) ?>)

            </button>

            <?php
            $firstTab = false;
        endforeach;
        ?>
    </div>
</div>

<style>
    .order-tab {
        border: 1px solid var(--border);
        background: transparent;
        color: var(--text-muted);
        padding: 0.6rem 1.25rem;
        border-radius: 99px;
        font-size: 0.85rem;
        font-weight: 600;
        white-space: nowrap;
        cursor: pointer;
        transition: all 0.2s;
    }

    .order-tab.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        box-shadow: 0 4px 6px -1px rgba(219, 39, 119, 0.3);
    }

    .order-list {
        display: none;
    }

    .order-list.active {
        display: block;
    }
</style>

<div class="container">
    <?php
    $firstList = true;

    foreach ($statusOrders as $status => $statusData):
        ?>

        <div id="list-<?= $status ?>" class="order-list <?= $firstList ? 'active' : '' ?>">

            <?php if (empty($statusData)): ?>

                <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                    <i class="ri-shopping-bag-line" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p style="margin-top: 1rem;">
                        No <?= ucwords(str_replace('_', ' ', $status)) ?> orders found.
                    </p>
                </div>

            <?php else: ?>

                <?php foreach ($statusData as $order): ?>

                    <div class="card">

                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">

                            <div style="display: flex; gap: 1rem;">

                                <div
                                    style="width: 60px; height: 60px; background: var(--background); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--primary); border: 1px solid var(--border);">
                                    <i class="ri-t-shirt-line" style="font-size: 1.5rem;"></i>
                                </div>

                                <div>
                                    <div style="font-weight: 700; font-size: 1.05rem; color: var(--text-main);">
                                        <?= htmlspecialchars($order['sub_category_name']) ?>
                                    </div>

                                    <div style="color: var(--text-muted); font-size: 0.8rem; margin-top: 2px;">
                                        Order #<?= $order['order_code'] ?>
                                    </div>
                                </div>

                            </div>

                            <span class="badge progress">
                                <?= ucwords(str_replace('_', ' ', $order['final_status'])) ?>
                            </span>

                        </div>

                        <div
                            style="background: var(--background); padding: 0.85rem; border-radius: 12px; margin-bottom: 1rem; font-size: 0.85rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.5rem;">

                            <i class="ri-time-line" style="color: var(--primary);"></i>

                            Appointment:
                            <span style="font-weight: 700; color: var(--text-main);">
                                <?php if (!empty($order['appointment_date'])): ?>
                                    <?= date('d M, Y', strtotime($order['appointment_date'])) ?>
                                <?php else: ?>
                                    Not Available
                                <?php endif; ?>
                            </span>

                        </div>

                        <button onclick="window.location.href='track-order.php?id=<?= $order['id'] ?>'"
                            style="width: 100%; border: 1px solid var(--primary); background: transparent; color: var(--primary); padding: 0.85rem; border-radius: 12px; font-weight: 700; font-size: 0.9rem;">
                            Track Order
                        </button>

                    </div>

                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
        $firstList = false;
    endforeach;
    ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        window.filterOrders = function (tab) {
            // Toggle Buttons
            const tabs = document.getElementsByClassName('order-tab');
            for (let t of tabs) {
                t.classList.remove('active');
            }
            document.getElementById('tab-' + tab).classList.add('active');

            // Toggle Lists
            const lists = document.getElementsByClassName('order-list');
            for (let l of lists) {
                l.classList.remove('active');
            }
            document.getElementById('list-' + tab).classList.add('active');
        }
    });
</script>

<?php include 'includes/bottom-nav.php'; ?>