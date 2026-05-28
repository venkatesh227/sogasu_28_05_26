<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// --- DATA LOGIC ---
$today = date('Y-m-d');
$today_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = '$today' AND is_deleted = 0")->fetchColumn();
$today_income = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE DATE(created_at) = '$today' AND is_deleted = 0")->fetchColumn() ?: 0;
$pending_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'pending' AND is_deleted = 0")->fetchColumn();
$total_employees = $pdo->query("SELECT COUNT(*) FROM employees WHERE is_deleted = 0")->fetchColumn();
$low_stock_count = $pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity < 10 AND is_deleted = 0")->fetchColumn();
$hr_alerts = ($pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'Pending'")->fetchColumn() ?: 0) + ($pdo->query("SELECT COUNT(*) FROM employee_overtime WHERE status = 'Pending'")->fetchColumn() ?: 0);
$total_outstanding = $pdo->query("SELECT SUM(total_amount - advance_paid) FROM orders WHERE is_deleted = 0")->fetchColumn() ?: 0;

$chart_labels = []; $chart_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('D', strtotime($date));
    $chart_data[] = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE DATE(created_at) = '$date' AND is_deleted = 0")->fetchColumn() ?: 0;
}

$status_counts = $pdo->query("SELECT order_status, COUNT(*) as count FROM orders WHERE is_deleted = 0 GROUP BY order_status")->fetchAll(PDO::FETCH_KEY_PAIR);
$inventory_categories = $pdo->query("SELECT category, COUNT(*) as count FROM inventory WHERE is_deleted = 0 GROUP BY category LIMIT 5")->fetchAll(PDO::FETCH_KEY_PAIR);

$recent_orders = $pdo->query("SELECT o.*, c.first_name, c.last_name FROM orders o JOIN customers c ON o.customer_id = c.id WHERE o.is_deleted = 0 ORDER BY o.created_at DESC LIMIT 6")->fetchAll();

$logs = [];
$trans_stmt = $pdo->query("SELECT 'Financial' as type, type as action, description, amount, created_at FROM financial_transactions ORDER BY created_at DESC LIMIT 4");
while($row = $trans_stmt->fetch()) $logs[] = $row;
$order_log_stmt = $pdo->query("SELECT 'Order' as type, 'Created' as action, CONCAT('Order #', order_code) as description, total_amount as amount, created_at FROM orders ORDER BY created_at DESC LIMIT 4");
while($row = $order_log_stmt->fetch()) $logs[] = $row;
usort($logs, function($a, $b) { return strtotime($b['created_at']) - strtotime($a['created_at']); });
$logs = array_slice($logs, 0, 5);

$pageTitle = "Dashboard - Sogasu";
$activePage = "dashboard";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div >
        
        <!-- Standard Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; ">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Dashboard</h2>
                <p style="color: #64748b; margin-top: 0.25rem;">Overview of your boutique's performance</p>
            </div>
            <a href="add-order.php" class="btn btn-primary" style="background: #4f46e5; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; color: white;">
                <i class="ri-add-line"></i> New Order
            </a>
        </div>

        <!-- Compact Stats Cards -->
        <div style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 1rem; ">
            <?php
            $stats = [
                ['label' => 'Today\'s Orders', 'val' => $today_orders, 'color' => '#3b82f6'], // Blue
                ['label' => 'Today\'s Income', 'val' => '₹'.number_format($today_income/1000, 1).'k', 'color' => '#10b981'], // Emerald
                ['label' => 'Pending Queue', 'val' => $pending_orders, 'color' => '#f59e0b'], // Amber
                ['label' => 'Active Staff', 'val' => $total_employees, 'color' => '#ec4899'], // Pink
                ['label' => 'Low Stock', 'val' => $low_stock_count, 'color' => '#8b5cf6'], // Purple
                ['label' => 'Outstanding', 'val' => '₹'.number_format($total_outstanding/1000, 1).'k', 'color' => '#ef4444'] // Red
            ];
            foreach($stats as $s): ?>
            <div class="table-container" style="padding: 1.25rem; margin-top: 0; ">
                <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= $s['label'] ?></div>
                <div style="font-size: 1.75rem; font-weight: 800; color: <?= $s['color'] ?>; margin-top: 0.5rem;"><?= $s['val'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Main Grid -->
        <div style="display: grid; grid-template-columns: 2fr 1.2fr; gap: 1.5rem;">
            <!-- Left: Charts -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                <div class="table-container" style="padding: 1.5rem; margin-top: 0;">
                    <div style="display: flex; justify-content: space-between; align-items: center; ">
                        <h3 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0;">Revenue Intelligence</h3>
                    </div>
                    <div style="height: 250px;"><canvas id="revenueChart"></canvas></div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div class="table-container" style="padding: 1.5rem; margin-top: 0; display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 100px; height: 100px;"><canvas id="statusChart"></canvas></div>
                        <div>
                            <div style="font-size: 0.8rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Operations</div>
                            <div style="font-size: 1.1rem; font-weight: 800; color: #1e293b; margin-top: 0.25rem;">Status Flow</div>
                        </div>
                    </div>
                    <div class="table-container" style="padding: 1.5rem; margin-top: 0; display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 100px; height: 100px;"><canvas id="inventoryChart"></canvas></div>
                        <div>
                            <div style="font-size: 0.8rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Warehouse</div>
                            <div style="font-size: 1.1rem; font-weight: 800; color: #1e293b; margin-top: 0.25rem;">Inventory</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Logs -->
            <div class="table-container" style="padding: 1.5rem; margin-top: 0;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0 0 1.5rem 0;">System Activity</h3>
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <?php foreach ($logs as $log): 
                        $color = ($log['action'] === 'Income' || $log['action'] === 'Created') ? '#10b981' : '#ec4899';
                    ?>
                    <div style="display: flex; gap: 1rem; padding: 0.75rem 0; border-bottom: 1px solid #f1f5f9;">
                        <div style="width: 36px; height: 36px; background: <?= $color ?>15; color: <?= $color ?>; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;"><i class="<?= $log['type'] === 'Financial' ? 'ri-money-dollar-circle-line' : 'ri-shopping-cart-line' ?>"></i></div>
                        <div style="flex: 1;">
                            <div style="display: flex; justify-content: space-between;">
                                <div style="font-weight: 700; color: #1e293b; font-size: 0.9rem;"><?= htmlspecialchars($log['description']) ?></div>
                                <div style="font-size: 0.9rem; font-weight: 800; color: <?= $color ?>;">₹<?= number_format($log['amount']) ?></div>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: #64748b; font-weight: 600; margin-top: 0.25rem;">
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
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 1.5rem 1.5rem 0 1.5rem; margin-bottom: 1rem;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0;">Recent Queue</h3>
                <a href="orders.php" style="font-size: 0.85rem; font-weight: 700; color: #6366f1; text-decoration: none;">View All &rarr;</a>
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
                        <td style="padding: 1rem;"><div style="font-weight: 700; color: #4f46e5;">#<?= $order['order_code'] ?></div></td>
                        <td style="padding: 1rem;"><div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></div></td>
                        <td style="padding: 1rem;"><div style="font-weight: 700; color: #1e293b;">₹<?= number_format($order['total_amount']) ?></div></td>
                        <td style="padding: 1rem;">
                            <?php 
                            $statusColor = match($order['order_status']) {
                                'pending' => '#f59e0b',
                                'stitching' => '#6366f1',
                                'ready' => '#0891b2',
                                'delivered' => '#10b981',
                                'cancelled' => '#ef4444',
                                default => '#64748b'
                            };
                            ?>
                            <span style="background: <?= $statusColor ?>15; color: <?= $statusColor ?>; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">
                                <?= ucfirst($order['order_status']) ?>
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