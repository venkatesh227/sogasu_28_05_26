<?php
session_start();
require '../includes/db.php';
<<<<<<< Updated upstream
=======
$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT 
        co.*,
        c.category_name,
        sc.name AS sub_category_name
    FROM customer_orders co
    LEFT JOIN categories c ON co.category_id = c.id
    LEFT JOIN sub_categories sc ON co.sub_category_id = sc.id
    WHERE co.user_id = ?
    AND co.is_deleted = 0
    ORDER BY co.created_at DESC
");

$stmt->execute([$userId]);

$orders = $stmt->fetchAll();
$activeOrders = [];
$completedOrders = [];
$upcomingOrders = [];

$today = date('Y-m-d');

foreach ($orders as $order) {

    // ACTIVE
    if (
        $order['status'] == 'pending' ||
        $order['status'] == 'in_progress'
    ) {
        $activeOrders[] = $order;
    }

    // COMPLETED
    if ($order['status'] == 'completed') {
        $completedOrders[] = $order;
    }

    // UPCOMING
    if (
        $order['appointment_date'] >= $today &&
        $order['status'] != 'completed'
    ) {
        $upcomingOrders[] = $order;
    }
}
>>>>>>> Stashed changes
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

<div style="background: var(--surface); padding: 0.75rem 1.25rem; border-bottom: 1px solid var(--border); position: sticky; top: 58px; z-index: 100; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
    <div style="display: flex; gap: 0.6rem; overflow-x: auto; padding-bottom: 2px; -ms-overflow-style: none; scrollbar-width: none;">
        <button onclick="filterOrders('active')" id="tab-active" class="order-tab active">Active (<?= count($activeOrders) ?>)</button>
        <button onclick="filterOrders('completed')" id="tab-completed" class="order-tab">Completed (<?= count($completedOrders) ?>)</button>
        <button onclick="filterOrders('upcoming')" id="tab-upcoming" class="order-tab">Upcoming (<?= count($upcomingOrders)?>)</button>
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
    .order-list { display: none; }
    .order-list.active { display: block; }
</style>

<div class="container">
    
    <!-- Active Orders -->
    <div id="list-active" class="order-list active">
        <?php if (empty($activeOrders)): ?>
            <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                <i class="ri-shopping-bag-line" style="font-size: 3rem; opacity: 0.3;"></i>
                <p style="margin-top: 1rem;">No active orders found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($activeOrders as $order): ?>
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                        <div style="display: flex; gap: 1rem;">
                            <div style="width: 60px; height: 60px; background: var(--background); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--primary); border: 1px solid var(--border);">
                                <i class="ri-t-shirt-line" style="font-size: 1.5rem;"></i>
                            </div>
                            <div>
                                <div style="font-weight: 700; font-size: 1.05rem; color: var(--text-main);"><?= htmlspecialchars($order['sub_category_name']) ?></div>
                                <div style="color: var(--text-muted); font-size: 0.8rem; margin-top: 2px;">Order #<?= $order['order_code'] ?></div>
                            </div>
                        </div>
                        <span class="badge progress"><?= ucfirst(str_replace('_', ' ', $order['status'])) ?></span>
                    </div>
                    <div style="background: var(--background); padding: 0.85rem; border-radius: 12px; margin-bottom: 1rem; font-size: 0.85rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.5rem;">
                        <i class="ri-time-line" style="color: var(--primary);"></i> Estimated: <span style="font-weight: 700; color: var(--text-main);"><?= date('d M, Y', strtotime($order['appointment_date'])) ?></span>
                    </div>
                    <button onclick="window.location.href='track-order.php?id=<?= $order['id'] ?>'" style="width: 100%; border: 1px solid var(--primary); background: transparent; color: var(--primary); padding: 0.85rem; border-radius: 12px; font-weight: 700; font-size: 0.9rem; transition: all 0.2s;">
                        Track Order
                    </button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Completed Orders -->
    <div id="list-completed" class="order-list">
        <?php if (empty($completedOrders)): ?>
            <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                <i class="ri-checkbox-circle-line" style="font-size: 3rem; opacity: 0.3;"></i>
                <p style="margin-top: 1rem;">No completed orders yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($completedOrders as $order): ?>
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                        <div style="display: flex; gap: 1rem;">
                            <div style="width: 60px; height: 60px; background: #f0fdf4; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #15803d; border: 1px solid #bbf7d0;">
                                <i class="ri-check-double-line" style="font-size: 1.5rem;"></i>
                            </div>
                            <div>
                                <div style="font-weight: 700; font-size: 1.05rem; color: var(--text-main);"><?= htmlspecialchars($order['sub_category_name']) ?></div>
                                <div style="color: var(--text-muted); font-size: 0.8rem; margin-top: 2px;">Order #<?= $order['order_code'] ?></div>
                            </div>
                        </div>
                        <span class="badge success">Delivered</span>
                    </div>
                    <div style="background: #f0fdf4; padding: 0.85rem; border-radius: 12px; margin-bottom: 1rem; font-size: 0.85rem; color: #166534; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="ri-checkbox-circle-fill"></i> Delivered on <span style="font-weight: 700;"><?= date('d M, Y', strtotime($order['updated_at'])) ?></span>
                    </div>
                    <button style="width: 100%; border: 1px solid #15803d; background: transparent; color: #15803d; padding: 0.85rem; border-radius: 12px; font-weight: 700; font-size: 0.9rem;">
                        Download Invoice
                    </button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Upcoming Orders -->
    <div id="list-upcoming" class="order-list">
        <?php if (empty($upcomingOrders)): ?>
            <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                <i class="ri-calendar-event-line" style="font-size: 3rem; opacity: 0.3;"></i>
                <p style="margin-top: 1rem;">No upcoming appointments.</p>
            </div>
        <?php else: ?>
            <?php foreach ($upcomingOrders as $order): ?>
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                        <div style="display: flex; gap: 1rem;">
                            <div style="width: 60px; height: 60px; background: #fff7ed; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #c2410c; border: 1px solid #ffedd5;">
                                <i class="ri-calendar-line" style="font-size: 1.5rem;"></i>
                            </div>
                            <div>
                                <div style="font-weight: 700; font-size: 1.05rem; color: var(--text-main);"><?= htmlspecialchars($order['sub_category_name']) ?></div>
                                <div style="color: var(--text-muted); font-size: 0.8rem; margin-top: 2px;">Order #<?= $order['order_code'] ?></div>
                            </div>
                        </div>
                        <span class="badge pending">Awaiting</span>
                    </div>
                    <div style="background: #fff7ed; padding: 0.85rem; border-radius: 12px; margin-bottom: 1rem; font-size: 0.85rem; color: #9a3412; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="ri-information-line"></i> Awaiting supervisor assignment
                    </div>
                    <button onclick="window.location.href='track-order.php?id=<?= $order['id'] ?>'" style="width: 100%; border: 1px solid #c2410c; background: transparent; color: #c2410c; padding: 0.85rem; border-radius: 12px; font-weight: 700; font-size: 0.9rem;">
                        View Details
                    </button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    window.filterOrders = function(tab) {
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
