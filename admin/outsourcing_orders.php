<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Logic
$tab = $_GET['tab'] ?? 'all';
// Fetch order status ENUM values dynamically
$statusQuery = $pdo->query("SHOW COLUMNS FROM outsource_orders LIKE 'order_status'");
$statusRow = $statusQuery->fetch(PDO::FETCH_ASSOC);

preg_match("/^enum\((.*)\)$/", $statusRow['Type'], $matches);

$statuses = [];

if (!empty($matches[1])) {
    $statuses = array_map(function ($value) {
        return trim($value, "'");
    }, explode(',', $matches[1]));
}

// Mark orders as viewed to clear the notification badge
$pdo->query("UPDATE outsource_orders SET is_viewed = 1 WHERE is_viewed = 0 AND is_deleted = 0");

$pageTitle = "Outsource Orders - Sogasu";
$activePage = "outsourcing-orders";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div>
        <?php if (!empty($_SESSION['success'])): ?>
            <div
                style="background: #ecfdf5; color: #047857; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #a7f3d0; display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem;">
                <i class="ri-checkbox-circle-line" style="font-size: 1.2rem;"></i>
                <span>Success!</span>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div
                style="background: #fef2f2; color: #b91c1c; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #fca5a5; display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem;">
                <i class="ri-error-warning-line" style="font-size: 1.2rem;"></i>
                <span><?= htmlspecialchars($_SESSION['error']) ?></span>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Standard Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; ">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Order Management</h2>
                <p style="color: #64748b; margin-top: 0.25rem;">View and manage customer orders</p>
            </div>
            <a href="add-order.php" class="btn btn-primary"
                style="background: #4f46e5; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; color: white;">
                <i class="ri-add-line"></i> New Order
            </a>
        </div>

        <!-- Tabs -->
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
        <div style="display: flex; gap: 0.75rem; overflow-x: auto; flex-wrap: wrap;">

            <!-- All Orders Tab -->
            <a href="outsourcing_orders.php?tab=all" class="status-tab" style="
            background: <?= $tab == 'all' ? '#4f46e5' : '#ffffff' ?>;
            color: <?= $tab == 'all' ? 'white' : '#64748b' ?>;
            border: 1px solid <?= $tab == 'all' ? '#4f46e5' : '#e2e8f0' ?>;
        ">
                All Orders
            </a>

            <?php foreach ($statuses as $status): ?>

                <?php
                $label = ucwords(str_replace('_', ' ', $status));
                ?>

                <a href="outsourcing_orders.php?tab=<?= urlencode($status) ?>" class="status-tab" style="
                background: <?= $tab == $status ? '#4f46e5' : '#ffffff' ?>;
                color: <?= $tab == $status ? 'white' : '#64748b' ?>;
                border: 1px solid <?= $tab == $status ? '#4f46e5' : '#e2e8f0' ?>;
            ">
                    <?= $label ?>
                </a>

            <?php endforeach; ?>

        </div>

        <!-- Orders Table (Standard Table Box) -->
        <div class="table-container">
            <table id="ordersTable" class="table">
                <thead>
                    <tr>
                        <th>Order No</th>
                        <th>Customer</th>
                        <th>Garment</th>
                        <th>Preview</th>
                        <th>Status</th>
                        <th>Due Date</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $whereClause = "o.is_deleted = 0";
                    if ($tab != 'all' && in_array($tab, $statuses)) {
                        $whereClause .= " AND o.order_status = " . $pdo->quote($tab);
                    }

                    $query = "
                        SELECT 
                            o.id,
                            'admin' as order_type,
                            o.order_code,
                            o.total_amount,
                            o.advance_amount,
                            o.paid_amount,
                            o.due_date,
                            o.order_status,
                            o.supervisor_id,
                            o.payment_link,
                            o.payment_status,
                            b.id as bill_id,
                            c.first_name,
                            c.last_name,
                            sc.name as garment,
                            o.material_image as fabric_img
                        FROM outsource_orders o
                        LEFT JOIN customers c ON o.customer_id = c.id
                        LEFT JOIN bills b ON b.order_id = o.id
                        LEFT JOIN sub_categories sc ON o.sub_category_id = sc.id
                        WHERE $whereClause

                        UNION ALL

                        SELECT 
                            co.id,
                            'customer' as order_type,
                            co.order_code,
                            co.total_amount,
                            0 as advance_amount,
                            0 as paid_amount,
                            co.appointment_date as due_date,
                            co.status as order_status,
                            co.supervisor_id,
                            NULL as payment_link,
                            NULL as payment_status,
                            NULL as bill_id,
                            cc.first_name,
                            cc.last_name,
                            sc.name as garment,
                            co.material_image as fabric_img
                        FROM customer_orders co
                        LEFT JOIN customers cc ON co.user_id = cc.user_id
                        LEFT JOIN sub_categories sc ON co.sub_category_id = sc.id
                        WHERE co.is_deleted = 0
                        AND co.slot_status != 'rejected'
                    ";
                    if ($tab != 'all' && in_array($tab, $statuses)) {
                        $query .= " AND co.status = " . $pdo->quote($tab);
                    }

                    $query .= " ORDER BY id DESC";
                    $orders_list = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($orders_list as $o):
                        switch ($o['order_status']) {
                            case 'pending':
                                $statusColor = '#f59e0b';
                                break;
                            case 'stitching':
                                $statusColor = '#6366f1';
                                break;
                            case 'ready':
                                $statusColor = '#0891b2';
                                break;
                            case 'delivered':
                                $statusColor = '#10b981';
                                break;
                            case 'cancelled':
                                $statusColor = '#ef4444';
                                break;
                            default:
                                $statusColor = '#64748b';
                                break;
                        }
                        ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 1rem; font-weight: 700; color: #4f46e5;">
                                #<?= htmlspecialchars($o['order_code']) ?></td>
                            <td style="padding: 1rem; font-weight: 600; color: #1e293b;">
                                <?= htmlspecialchars($o['first_name'] . ' ' . $o['last_name']) ?>
                            </td>
                            <td style="padding: 1rem; color: #64748b;"><?= htmlspecialchars($o['garment'] ?? 'General') ?>
                            </td>
                            <td>

                                <?php if (!empty($o['fabric_img'])): ?>

                                    <?php

                                    $imageName = $o['fabric_img'];

                                    $customerPath = "../customer/uploads/" . $imageName;

                                    $adminPath = "../" . $imageName;

                                    $imageSrc = '';

                                    if (file_exists($customerPath)) {

                                        $imageSrc = $customerPath;

                                    } elseif (file_exists($adminPath)) {

                                        $imageSrc = $adminPath;

                                    }

                                    ?>

                                    <?php if (!empty($imageSrc)): ?>

                                        <img src="<?= htmlspecialchars($imageSrc) ?>" alt="Fabric Image"
                                            style="width:50px;height:50px;object-fit:cover;border-radius:6px;">

                                    <?php else: ?>

                                        <span>No Image</span>

                                    <?php endif; ?>

                                <?php else: ?>

                                    <span>No Image</span>

                                <?php endif; ?>

                            </td>
                            <td style="padding: 1rem;">
                                <span
                                    style="background: <?= $statusColor ?>15; color: <?= $statusColor ?>; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">
                                    <?= ucfirst($o['order_status']) ?>
                                </span>
                            </td>
                            <td style="padding: 1rem; font-weight: 600;">
                                <?php
                                $diff_days = round((strtotime($o['due_date']) - strtotime(date('Y-m-d'))) / (60 * 60 * 24));
                                if (!in_array($o['order_status'], ['delivered', 'cancelled'])) {
                                    if ($diff_days < 0) {
                                        $dueColor = '#ef4444'; // Red (Overdue)
                                        $dueBg = '#fee2e2';
                                    } elseif ($diff_days <= 2) {
                                        $dueColor = '#f59e0b'; // Amber (Due soon)
                                        $dueBg = '#fef3c7';
                                    } else {
                                        $dueColor = '#334155'; // Normal
                                        $dueBg = 'transparent';
                                    }
                                } else {
                                    $dueColor = '#94a3b8'; // Delivered/Cancelled
                                    $dueBg = 'transparent';
                                }
                                ?>
                                <span
                                    style="color: <?= $dueColor ?>; background: <?= $dueBg ?>; padding: <?= $dueBg !== 'transparent' ? '4px 8px' : '0' ?>; border-radius: 6px; display: inline-block;">
                                    <?= date('d M Y', strtotime($o['due_date'])) ?>
                                </span>
                            </td>
                            <td style="padding: 1rem; text-align: right;">
                                <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                    
                                    <a href="view-outsource-order.php?id=<?= $o['id'] ?>" class="btn btn-sm"
                                        style="background: #f8fafc; color: #6366f1; border: 1px solid #e2e8f0; padding: 5px 10px; border-radius: 6px; text-decoration: none;"><i
                                            class="ri-eye-line"></i> View</a>
                                    <?php if (
                                        !empty($o['bill_id']) &&
                                        $o['payment_status'] !== 'paid'
                                    ): ?>

                                        <a href="generate-payment-link.php?order_id=<?= $o['id'] ?>" class="btn btn-sm"
                                            style="background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; padding: 5px 10px; border-radius: 6px; text-decoration: none;">
                                            <i class="ri-secure-payment-line"></i> Pay Now
                                        </a>

                                    <?php endif; ?>
                                    <a href="edit-outsource-order.php?id=<?= $o['id'] ?>&type=<?= $o['order_type'] ?>"
                                        class="btn btn-sm"
                                        style="background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; padding: 5px 10px; border-radius: 6px; text-decoration: none;"><i
                                            class="ri-pencil-line"></i> Edit</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include __DIR__ . '/includes/datatable.php'; ?>
<script>

    $(document).ready(function () {
        initializeDataTable('ordersTable', 'Orders Report');
    });

    
        let html = `
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; margin-top: 0.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <span style="font-size: 0.8rem; font-weight: 600; color: #475569;">Active Workload: ${orderCount} Order(s)</span>
                <span style="font-size: 0.75rem; font-weight: 700; color: ${progressColor}; text-transform: uppercase;">${loadText} (${loadPercent}%)</span>
            </div>
            <div style="background: #e2e8f0; height: 8px; border-radius: 4px; overflow: hidden;">
                <div style="background: ${progressColor}; height: 100%; width: ${loadPercent}%; transition: width 0.3s ease;"></div>
            </div>
        </div>
    `;

        container.innerHTML = html;
        container.style.display = 'block';
    }
</script>
<?php include 'includes/footer.php'; ?>