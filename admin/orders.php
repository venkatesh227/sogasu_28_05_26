<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_supervisor') {
    $order_id = $_POST['order_id'] ?? null;
    $supervisor_id = $_POST['supervisor_id'] ?: null;
    $tab = $_GET['tab'] ?? 'all';

    if ($order_id) {
        try {
            $stmt = $pdo->prepare("UPDATE orders SET supervisor_id = ? WHERE id = ?");
            $stmt->execute([$supervisor_id, $order_id]);
            $_SESSION['success'] = "supervisor_assigned";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
    }
    header("Location: orders.php" . ($tab !== 'all' ? "?tab=$tab" : ""));
    exit();
}

// Fetch Supervisor Workload / Active Orders
$activeOrders = $pdo->query("
    SELECT o.id, o.order_code, o.total_amount, o.advance_amount, o.paid_amount, o.due_date, o.order_status, o.supervisor_id, o.payment_link, o.payment_status, sc.name as garment
    FROM orders o
    LEFT JOIN sub_categories sc ON o.sub_category_id = sc.id
    WHERE o.is_deleted = 0 
      AND o.supervisor_id IS NOT NULL 
      AND o.order_status NOT IN ('completed', 'delivered', 'cancelled')
    ORDER BY o.due_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

$supervisorWorkloads = [];
foreach ($activeOrders as $orderItem) {
    $supervisorWorkloads[$orderItem['supervisor_id']][] = $orderItem;
}

// Fetch Supervisors
$supervisors = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM employees WHERE is_deleted=0 AND job_role = 'Supervisor'")->fetchAll(PDO::FETCH_ASSOC);

// Logic
$tab = $_GET['tab'] ?? 'all';
$statuses = ['pending', 'processing', 'stitching', 'ready', 'delivered', 'cancelled'];

// Mark orders as viewed to clear the notification badge
$pdo->query("UPDATE orders SET is_viewed = 1 WHERE is_viewed = 0 AND is_deleted = 0");

$pageTitle = "Orders - Sogasu";
$activePage = "orders";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div>
        <?php if (!empty($_SESSION['success'])): ?>
            <div
                style="background: #ecfdf5; color: #047857; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #a7f3d0; display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem;">
                <i class="ri-checkbox-circle-line" style="font-size: 1.2rem;"></i>
                <span>
                    <?= $_SESSION['success'] === 'supervisor_assigned' ? 'Supervisor assigned successfully!' : 'Success!' ?>
                </span>
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
        <div style="display: flex; gap: 0.75rem;  overflow-x: auto; ">
            <a href="orders.php?tab=all" class="status-tab"
                style="background: <?= $tab == 'all' ? '#4f46e5' : '#ffffff' ?>; color: <?= $tab == 'all' ? 'white' : '#64748b' ?>; border: 1px solid <?= $tab == 'all' ? '#4f46e5' : '#e2e8f0' ?>;">All
                Orders</a>
            <a href="orders.php?tab=pending" class="status-tab"
                style="background: <?= $tab == 'pending' ? '#4f46e5' : '#ffffff' ?>; color: <?= $tab == 'pending' ? 'white' : '#64748b' ?>; border: 1px solid <?= $tab == 'pending' ? '#4f46e5' : '#e2e8f0' ?>;">Pending</a>
            <a href="orders.php?tab=processing" class="status-tab"
                style="background: <?= $tab == 'processing' ? '#4f46e5' : '#ffffff' ?>; color: <?= $tab == 'processing' ? 'white' : '#64748b' ?>; border: 1px solid <?= $tab == 'processing' ? '#4f46e5' : '#e2e8f0' ?>;">Processing</a>
            <a href="orders.php?tab=stitching" class="status-tab"
                style="background: <?= $tab == 'stitching' ? '#4f46e5' : '#ffffff' ?>; color: <?= $tab == 'stitching' ? 'white' : '#64748b' ?>; border: 1px solid <?= $tab == 'stitching' ? '#4f46e5' : '#e2e8f0' ?>;">Stitching</a>
            <a href="orders.php?tab=ready" class="status-tab"
                style="background: <?= $tab == 'ready' ? '#4f46e5' : '#ffffff' ?>; color: <?= $tab == 'ready' ? 'white' : '#64748b' ?>; border: 1px solid <?= $tab == 'ready' ? '#4f46e5' : '#e2e8f0' ?>;">Ready
                for Delivery</a>
            <a href="orders.php?tab=delivered" class="status-tab"
                style="background: <?= $tab == 'delivered' ? '#4f46e5' : '#ffffff' ?>; color: <?= $tab == 'delivered' ? 'white' : '#64748b' ?>; border: 1px solid <?= $tab == 'delivered' ? '#4f46e5' : '#e2e8f0' ?>;">Delivered</a>
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
                            o.order_code,
                            o.due_date,
                            o.order_status,
                            o.supervisor_id,
                            o.payment_link,
                            o.payment_status,
                            c.first_name,
                            c.last_name,
                            sc.name as garment,
                            (SELECT image_path FROM order_images WHERE order_id = o.id AND image_type = 'fabric' LIMIT 1) as fabric_img
                        FROM orders o
                        LEFT JOIN customers c ON o.customer_id = c.id
                        LEFT JOIN sub_categories sc ON o.sub_category_id = sc.id
                        WHERE $whereClause
                        ORDER BY o.id DESC
                    ";
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
                            <td style="padding: 1rem;">
                                <?php $img = !empty($o['fabric_img']) ? '../' . $o['fabric_img'] : 'https://via.placeholder.com/40'; ?>
                                <img src="<?= $img ?>"
                                    style="width: 40px; height: 40px; border-radius: 8px; object-fit: cover; border: 1px solid #e2e8f0;">
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
                                    <button
                                        onclick="openSupervisorModal(<?= $o['id'] ?>, '<?= htmlspecialchars($o['order_code']) ?>', '<?= htmlspecialchars($o['supervisor_id'] ?? '') ?>')"
                                        class="btn btn-sm"
                                        style="background: #f8fafc; color: #f59e0b; border: 1px solid #e2e8f0; padding: 5px 10px; border-radius: 6px; cursor: pointer;"
                                        title="Assign Supervisor"><i class="ri-user-star-line"></i> Supervisor</button>
                                    <a href="view-order.php?id=<?= $o['id'] ?>" class="btn btn-sm"
                                        style="background: #f8fafc; color: #6366f1; border: 1px solid #e2e8f0; padding: 5px 10px; border-radius: 6px; text-decoration: none;"><i
                                            class="ri-eye-line"></i> View</a>
                                    <?php if (!empty($o['payment_link']) && $o['payment_status'] !== 'paid'): ?>

                                        <a href="generate-payment-link.php?order_id=<?= $o['id'] ?>" class="btn btn-sm"
                                            style="background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; padding: 5px 10px; border-radius: 6px; text-decoration: none;">
                                            <i class="ri-secure-payment-line"></i> Pay Now
                                        </a>

                                    <?php endif; ?>
                                    <a href="edit-order.php?id=<?= $o['id'] ?>" class="btn btn-sm"
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

<!-- Supervisor Assignment Modal -->
<div id="supervisorModal"
    style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center; transition: all 0.3s;">
    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; max-width: 400px; width: 90%; padding: 1.5rem; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); transform: scale(0.95); transition: all 0.2s;"
        id="modalContent">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h3
                style="font-size: 1.1rem; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 0.5rem; margin: 0;">
                <i class="ri-user-star-line" style="color: #4f46e5;"></i> Assign Supervisor
            </h3>
            <button onclick="closeSupervisorModal()"
                style="border: none; background: transparent; color: #64748b; font-size: 1.2rem; cursor: pointer;"><i
                    class="ri-close-line"></i></button>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="assign_supervisor">
            <input type="hidden" name="order_id" id="modalOrderId">

            <p style="font-size: 0.85rem; color: #64748b; margin-top: -0.25rem; margin-bottom: 1.25rem;">
                Select a supervisor for Order <strong id="modalOrderCode" style="color: #4f46e5;"></strong>.
            </p>

            <div style="margin-bottom: 1rem;">
                <label
                    style="font-size: 0.8rem; font-weight: 600; color: #475569; display: block; margin-bottom: 0.5rem;">Supervisor</label>
                <select name="supervisor_id" id="modalSupervisorSelect"
                    style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; outline: none; transition: border-color 0.2s;"
                    onfocus="this.style.borderColor='#4f46e5'" onblur="this.style.borderColor='#cbd5e1'">
                    <option value="">-- Select Supervisor --</option>
                    <?php foreach ($supervisors as $sup): ?>
                        <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Workload & Timeline Container -->
            <div id="modalWorkloadContainer" style="display: none; margin-bottom: 1.25rem;"></div>

            <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button type="button" onclick="closeSupervisorModal()"
                    style="padding: 0.625rem 1rem; border: 1px solid #e2e8f0; background: white; color: #475569; font-weight: 600; border-radius: 8px; cursor: pointer; transition: background 0.2s;"
                    onmouseover="this.style.background='#f8fafc'"
                    onmouseout="this.style.background='white'">Cancel</button>
                <button type="submit"
                    style="padding: 0.625rem 1.25rem; border: none; background: #4f46e5; color: white; font-weight: 600; border-radius: 8px; cursor: pointer; transition: opacity 0.2s;"
                    onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">Assign</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/datatable.php'; ?>
<script>
    const supervisorWorkloads = <?= json_encode($supervisorWorkloads) ?>;

    $(document).ready(function () {
        initializeDataTable('ordersTable', 'Orders Report');

        document.getElementById('modalSupervisorSelect').addEventListener('change', function () {
            handleSupervisorChange(this.value);
        });
    });

    function openSupervisorModal(orderId, orderCode, currentSupId) {
        document.getElementById('modalOrderId').value = orderId;
        document.getElementById('modalOrderCode').innerText = '#' + orderCode;
        document.getElementById('modalSupervisorSelect').value = currentSupId;

        // Trigger workload display load
        handleSupervisorChange(currentSupId);

        const modal = document.getElementById('supervisorModal');
        modal.style.display = 'flex';
        setTimeout(() => {
            document.getElementById('modalContent').style.transform = 'scale(1)';
        }, 10);
    }

    function closeSupervisorModal() {
        document.getElementById('modalContent').style.transform = 'scale(0.95)';
        setTimeout(() => {
            document.getElementById('supervisorModal').style.display = 'none';
        }, 150);
    }

    function handleSupervisorChange(supervisorId) {
        const container = document.getElementById('modalWorkloadContainer');
        if (!supervisorId) {
            container.style.display = 'none';
            container.innerHTML = '';
            return;
        }

        const orders = supervisorWorkloads[supervisorId] || [];
        const orderCount = orders.length;
        const loadPercent = Math.min(orderCount * 20, 100);

        let progressColor = '#22c55e'; // Green
        let loadText = 'Available / Light';
        if (orderCount >= 5) {
            progressColor = '#ef4444'; // Red
            loadText = 'Overloaded / Heavy';
        } else if (orderCount >= 3) {
            progressColor = '#f59e0b'; // Amber
            loadText = 'Moderate';
        }

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