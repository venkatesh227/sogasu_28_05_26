<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$readyDelivery = [];

/* Normal Orders */
$stmt1 = $pdo->query("
    SELECT 
        o.order_code,
        c.first_name,
        c.last_name,
        c.phone,
        sc.name AS sub_category
    FROM orders o
    LEFT JOIN customers c ON c.id = o.customer_id
    LEFT JOIN sub_categories sc ON sc.id = o.sub_category_id
    WHERE o.order_status IN ('ready', 'completed')
    LIMIT 5
");

$readyDelivery = array_merge($readyDelivery, $stmt1->fetchAll(PDO::FETCH_ASSOC));
$stmt2 = $pdo->query("
    SELECT 
        co.order_code,
        c.first_name,
        c.last_name,
        c.phone,
        sc.name AS sub_category
    FROM customer_orders co
    LEFT JOIN customers c ON c.user_id = co.user_id
    LEFT JOIN sub_categories sc ON sc.id = co.sub_category_id
    WHERE co.status IN ('ready', 'completed')
    LIMIT 5
");

$readyDelivery = array_merge($readyDelivery, $stmt2->fetchAll(PDO::FETCH_ASSOC));

$stmt3 = $pdo->query("
    SELECT 
        oo.order_code,
        c.first_name,
        c.last_name,
        c.phone,
        sc.name AS sub_category
    FROM outsource_orders oo
    LEFT JOIN customers c ON c.id = oo.customer_id
    LEFT JOIN sub_categories sc ON sc.id = oo.sub_category_id
    WHERE oo.order_status = 'completed'
    LIMIT 5
");

$readyDelivery = array_merge($readyDelivery, $stmt3->fetchAll(PDO::FETCH_ASSOC));
$readyDelivery = array_slice($readyDelivery, 0, 5);

// --- DATA LOGIC ---
$today = date('Y-m-d');
$today_orders = $pdo->query("
    SELECT COUNT(*) FROM (
        SELECT created_at FROM orders WHERE is_deleted = 0
        UNION ALL
        SELECT created_at FROM customer_orders WHERE is_deleted = 0
        UNION ALL
        SELECT created_at FROM outsource_orders WHERE is_deleted = 0
    ) all_orders
    WHERE DATE(created_at) = '$today'
")->fetchColumn();
$today_income = $pdo->query("
    SELECT COALESCE(SUM(total_amount),0)
    FROM bills
    WHERE DATE(created_at) = '$today'
    AND is_deleted = 0
")->fetchColumn() ?: 0;
$pending_orders = $pdo->query("
    SELECT COUNT(*) FROM (
        SELECT order_status AS status
        FROM orders
        WHERE is_deleted = 0

        UNION ALL

        SELECT status
        FROM customer_orders
        WHERE is_deleted = 0

        UNION ALL

        SELECT order_status AS status
        FROM outsource_orders
        WHERE is_deleted = 0
    ) x
    WHERE status IN (
        'pending',
        'processing',
        'pattern_making',
        'cutting',
        'embroidery',
        'stitching',
        'finishing',
        'accepted',
        'approved',
        'in progress'
    )
")->fetchColumn();
$total_employees = $pdo->query("
    SELECT COUNT(*)
    FROM employees
    WHERE is_deleted = 0
    AND status = 1
")->fetchColumn();
$low_stock_count = $pdo->query("
    SELECT COUNT(*)
    FROM inventory
    WHERE quantity <= low_stock_alert
    AND is_deleted = 0
    AND status = 1
")->fetchColumn();
$hr_alerts = ($pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'Pending'")->fetchColumn() ?: 0) + ($pdo->query("SELECT COUNT(*) FROM employee_overtime WHERE status = 'Pending'")->fetchColumn() ?: 0);
$total_outstanding = $pdo->query("
    SELECT COALESCE(SUM(pending_amount),0)
    FROM bills
    WHERE is_deleted = 0
")->fetchColumn() ?: 0;

$chart_labels = [];
$chart_data = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('D', strtotime($date));

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount), 0)
        FROM bills
        WHERE DATE(created_at) = ?
        AND is_deleted = 0
    ");
    $stmt->execute([$date]);

    $chart_data[] = $stmt->fetchColumn();
}

$status_counts = [
    'Pending' => 0,
    'In Progress' => 0,
    'Ready' => 0,
    'Completed' => 0,
    'Cancelled' => 0
];

$all_statuses = $pdo->query("
    SELECT order_status AS status FROM orders WHERE is_deleted = 0
    UNION ALL
    SELECT status FROM customer_orders WHERE is_deleted = 0
    UNION ALL
    SELECT order_status AS status FROM outsource_orders WHERE is_deleted = 0
")->fetchAll(PDO::FETCH_COLUMN);

foreach ($all_statuses as $status) {
    if ($status === 'pending') {
        $status_counts['Pending']++;
    } elseif (
        in_array($status, [
            'processing',
            'pattern_making',
            'cutting',
            'embroidery',
            'stitching',
            'finishing',
            'accepted',
            'approved',
            'in progress'
        ])
    ) {
        $status_counts['In Progress']++;
    } elseif ($status === 'ready') {
        $status_counts['Ready']++;
    } elseif (in_array($status, ['completed', 'delivered'])) {
        $status_counts['Completed']++;
    } elseif (in_array($status, ['cancelled', 'rejected'])) {
        $status_counts['Cancelled']++;
    }
}
$inventory_categories = $pdo->query("SELECT category, COUNT(*) as count FROM inventory WHERE is_deleted = 0 GROUP BY category LIMIT 5")->fetchAll(PDO::FETCH_KEY_PAIR);

$recent_orders = $pdo->query("
    SELECT *
    FROM (
        SELECT 
            o.order_code,
            o.total_amount,
            o.order_status AS status,
            o.created_at,
            c.first_name,
            c.last_name
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        WHERE o.is_deleted = 0

        UNION ALL

        SELECT 
            oo.order_code,
            oo.total_amount,
            oo.order_status AS status,
            oo.created_at,
            c.first_name,
            c.last_name
        FROM outsource_orders oo
        LEFT JOIN customers c ON oo.customer_id = c.id
        WHERE oo.is_deleted = 0

        UNION ALL

        SELECT 
            co.order_code,
            co.total_amount,
            co.status,
            co.created_at,
            c.first_name,
            c.last_name
        FROM customer_orders co
        LEFT JOIN customers c ON co.user_id = c.user_id
        WHERE co.is_deleted = 0
    ) recent_orders
    ORDER BY created_at DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

$logs = [];
$trans_stmt = $pdo->query("SELECT 'Financial' as type, type as action, description, amount, created_at FROM financial_transactions ORDER BY created_at DESC LIMIT 4");
while ($row = $trans_stmt->fetch())
    $logs[] = $row;
$order_log_stmt = $pdo->query("
    SELECT *
    FROM (
        SELECT
            'Order' AS type,
            'Created' AS action,
            CONCAT('Order #', order_code) AS description,
            total_amount AS amount,
            created_at
        FROM orders
        WHERE is_deleted = 0

        UNION ALL

        SELECT
            'Order' AS type,
            'Created' AS action,
            CONCAT('Customer Order #', order_code) AS description,
            total_amount AS amount,
            created_at
        FROM customer_orders
        WHERE is_deleted = 0

        UNION ALL

        SELECT
            'Order' AS type,
            'Created' AS action,
            CONCAT('Outsource Order #', order_code) AS description,
            total_amount AS amount,
            created_at
        FROM outsource_orders
        WHERE is_deleted = 0
    ) logs
    ORDER BY created_at DESC
    LIMIT 4
");

while ($row = $order_log_stmt->fetch()) {
    $logs[] = $row;
}
usort($logs, function ($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});
$logs = array_slice($logs, 0, 5);

$pageTitle = "Dashboard - Sogasu";
$activePage = "dashboard";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div>

        <!-- Standard Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; ">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Dashboard</h2>
                <p style="color: #64748b; margin-top: 0.25rem;">Overview of your boutique's performance</p>
            </div>
            <a href="add-order.php" class="btn btn-primary"
                style="background: #4f46e5; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; color: white;">
                <i class="ri-add-line"></i> New Order
            </a>
        </div>

        <!-- Compact Stats Cards -->
        <div style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 1rem; ">
            <?php
            $stats = [
                ['label' => 'Today\'s Orders', 'val' => $today_orders, 'color' => '#3b82f6'], // Blue
                ['label' => 'Today\'s Income', 'val' => '₹' . number_format($today_income / 1000, 1) . 'k', 'color' => '#10b981'], // Emerald
                ['label' => 'Pending Queue', 'val' => $pending_orders, 'color' => '#f59e0b'], // Amber
                ['label' => 'Active Staff', 'val' => $total_employees, 'color' => '#ec4899'], // Pink
                ['label' => 'Low Stock', 'val' => $low_stock_count, 'color' => '#8b5cf6'], // Purple
                ['label' => 'Outstanding', 'val' => '₹' . number_format($total_outstanding / 1000, 1) . 'k', 'color' => '#ef4444'] // Red
            ];
            foreach ($stats as $s): ?>
                <div class="table-container" style="padding: 1.25rem; margin-top: 0; ">
                    <div
                        style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        <?= $s['label'] ?>
                    </div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: <?= $s['color'] ?>; margin-top: 0.5rem;">
                        <?= $s['val'] ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Main Grid -->
        <div style="display: grid; grid-template-columns: 2fr 1.2fr; gap: 1.5rem;">
            <!-- Left: Charts -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                <div class="table-container" style="padding: 1.5rem; margin-top: 0;">
                    <div style="display: flex; justify-content: space-between; align-items: center; ">
                        <h3 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0;">Revenue Intelligence
                        </h3>
                    </div>
                    <div style="height: 250px;"><canvas id="revenueChart"></canvas></div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div class="table-container"
                        style="padding: 1.5rem; margin-top: 0; display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 100px; height: 100px;"><canvas id="statusChart"></canvas></div>
                        <div>
                            <div
                                style="font-size: 0.8rem; color: #64748b; font-weight: 700; text-transform: uppercase;">
                                Operations</div>
                            <div style="font-size: 1.1rem; font-weight: 800; color: #1e293b; margin-top: 0.25rem;">
                                Status Flow</div>
                        </div>
                    </div>
                    <div class="table-container"
                        style="padding: 1.5rem; margin-top: 0; display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 100px; height: 100px;"><canvas id="inventoryChart"></canvas></div>
                        <div>
                            <div
                                style="font-size: 0.8rem; color: #64748b; font-weight: 700; text-transform: uppercase;">
                                Warehouse</div>
                            <div style="font-size: 1.1rem; font-weight: 800; color: #1e293b; margin-top: 0.25rem;">
                                Inventory</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Logs -->
            <div class="table-container" style="padding: 1.5rem; margin-top: 0;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0 0 1.5rem 0;">System Activity
                </h3>
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <?php foreach ($logs as $log):
                        $color = ($log['action'] === 'Income' || $log['action'] === 'Created') ? '#10b981' : '#ec4899';
                        ?>
                        <div style="display: flex; gap: 1rem; padding: 0.75rem 0; border-bottom: 1px solid #f1f5f9;">
                            <div
                                style="width: 36px; height: 36px; background: <?= $color ?>15; color: <?= $color ?>; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;">
                                <i
                                    class="<?= $log['type'] === 'Financial' ? 'ri-money-dollar-circle-line' : 'ri-shopping-cart-line' ?>"></i>
                            </div>
                            <div style="flex: 1;">
                                <div style="display: flex; justify-content: space-between;">
                                    <div style="font-weight: 700; color: #1e293b; font-size: 0.9rem;">
                                        <?= htmlspecialchars($log['description']) ?>
                                    </div>
                                    <div style="font-size: 0.9rem; font-weight: 800; color: <?= $color ?>;">
                                        ₹<?= number_format($log['amount']) ?></div>
                                </div>
                                <div
                                    style="display: flex; justify-content: space-between; font-size: 0.75rem; color: #64748b; font-weight: 600; margin-top: 0.25rem;">
                                    <span><?= $log['type'] ?></span>
                                    <span><?= date('h:i A', strtotime($log['created_at'])) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Recent Orders Table -->
        <div class="table-container" style="margin-top: 0;">
            <div
                style="display: flex; justify-content: space-between; align-items: center; padding: 1.5rem 1.5rem 0 1.5rem; margin-bottom: 1rem;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0;">Recent Queue</h3>
                <a href="orders.php"
                    style="font-size: 0.85rem; font-weight: 700; color: #6366f1; text-decoration: none;">View All
                    &rarr;</a>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Order No</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($recent_orders, 0, 5) as $order): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 1rem;">
                                <div style="font-weight: 700; color: #4f46e5;">#<?= $order['order_code'] ?></div>
                            </td>
                            <td style="padding: 1rem;">
                                <div style="font-weight: 600; color: #1e293b;">
                                    <?= htmlspecialchars(
                                        trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''))
                                        ?: 'Unknown Customer'
                                    ) ?>
                                </div>
                            </td>
                            <td style="padding: 1rem;">
                                <div style="font-weight: 700; color: #1e293b;">₹<?= number_format($order['total_amount']) ?>
                                </div>
                            </td>
                            <td style="padding: 1rem;">
                                <?php
                                $status = $order['status'];

                                $statusColor = match ($status) {
                                    'pending' => '#f59e0b',

                                    'processing',
                                    'pattern_making',
                                    'cutting',
                                    'embroidery',
                                    'stitching',
                                    'finishing',
                                    'accepted',
                                    'approved',
                                    'in progress' => '#6366f1',

                                    'ready' => '#0891b2',

                                    'completed',
                                    'delivered' => '#10b981',

                                    'cancelled',
                                    'rejected' => '#ef4444',

                                    default => '#64748b'
                                };
                                ?>
                                <span
                                    style="background: <?= $statusColor ?>15; color: <?= $statusColor ?>; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">
                                    <?= ucwords(str_replace('_', ' ', $order['status'])) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
</main>

<?php include 'includes/right-sidebar.php'; ?>

<script>
    const ctxRevenue = document.getElementById('revenueChart').getContext('2d');
    const grad = ctxRevenue.createLinearGradient(0, 0, 0, 180);
    grad.addColorStop(0, 'rgba(99, 102, 241, 0.25)');
    grad.addColorStop(1, 'rgba(99, 102, 241, 0)');

    new Chart(ctxRevenue, {
        type: 'line',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [{
                data: <?= json_encode($chart_data) ?>,
                borderColor: '#6366f1',
                backgroundColor: grad,
                fill: true,
                tension: 0.4,
                borderWidth: 3,
                pointRadius: 3,
                pointBackgroundColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 8, weight: 'bold' } } },
                x: { grid: { display: false }, ticks: { font: { size: 8, weight: 'bold' } } }
            }
        }
    });

    const commonPie = {
        responsive: true, maintainAspectRatio: false, cutout: '75%',
        plugins: { legend: { display: false } }
    };

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            datasets: [{
                data: <?= json_encode(array_values($status_counts)) ?>,
                backgroundColor: ['#6366f1', '#ec4899', '#f59e0b', '#10b981', '#8b5cf6'],
                borderWidth: 0, borderRadius: 2
            }]
        },
        options: commonPie
    });

    new Chart(document.getElementById('inventoryChart'), {
        type: 'doughnut',
        data: {
            datasets: [{
                data: <?= json_encode(array_values($inventory_categories)) ?>,
                backgroundColor: ['#f59e0b', '#6366f1', '#10b981', '#ec4899', '#8b5cf6'],
                borderWidth: 0, borderRadius: 2
            }]
        },
        options: commonPie
    });
</script>

<?php include 'includes/footer.php'; ?>