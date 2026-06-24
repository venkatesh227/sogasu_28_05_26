<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = $_GET['success'] ?? '';
$stmt = $pdo->prepare("
    SELECT id, employee_type
    FROM employees
    WHERE user_id = ?
    AND is_deleted = 0
");
$stmt->execute([$user_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee || $employee['employee_type'] !== 'outsource') {
    header("Location: dashboard.php");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['new_status'])) {

    $order_id = (int) $_POST['order_id'];
    $new_status = $_POST['new_status'];

    $stmt = $pdo->prepare("
    SELECT order_status
    FROM outsource_orders
    WHERE id = ?
    AND assigned_employee_id = ?
    AND is_deleted = 0
");
    $stmt->execute([$order_id, $employee['id']]);
    $currentOrder = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($currentOrder) {

        $current_status = $currentOrder['order_status'];

        $validTransition =
            ($current_status === 'approved' && $new_status === 'in progress') ||
            ($current_status === 'in progress' && $new_status === 'completed');

        if ($validTransition) {
            $stmt = $pdo->prepare("
            UPDATE outsource_orders
            SET order_status = ?
            WHERE id = ?
            AND assigned_employee_id = ?
            AND is_deleted = 0
        ");

            $stmt->execute([
                $new_status,
                $order_id,
                $employee['id']
            ]);
        }
    }

    header("Location: outsource-orders.php?success=status_updated");
    exit;
}

$validTabs = [
    'all',
    'pending',
    'accepted',
    'rejected',
    'approved',
    'in progress',
    'completed'
];

$selectedTab = $_GET['tab'] ?? 'all';

if (!in_array($selectedTab, $validTabs)) {
    $selectedTab = 'all';
}
$stmt = $pdo->prepare("
    SELECT order_status, COUNT(*) as total
    FROM outsource_orders
    WHERE assigned_employee_id = ?
    AND is_deleted = 0
    GROUP BY order_status
");
$stmt->execute([$employee['id']]);

$statusCounts = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $statusCounts[$row['order_status']] = $row['total'];
}
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM outsource_orders
    WHERE assigned_employee_id = ?
    AND is_deleted = 0
");
$stmt->execute([$employee['id']]);
$allCount = $stmt->fetchColumn();

if ($selectedTab === 'all') {

    $stmt = $pdo->prepare("
        SELECT 
            o.*,
            sc.name AS garment_name
        FROM outsource_orders o
        LEFT JOIN sub_categories sc ON o.sub_category_id = sc.id
        WHERE o.assigned_employee_id = ?
        AND o.is_deleted = 0
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$employee['id']]);

} elseif (in_array($selectedTab, ['approved', 'in progress', 'completed'])) {

    $stmt = $pdo->prepare("
        SELECT 
            o.*,
            sc.name AS garment_name
        FROM outsource_orders o
        LEFT JOIN sub_categories sc ON o.sub_category_id = sc.id
        WHERE o.order_status = ?
        AND o.assigned_employee_id = ?
        AND o.is_deleted = 0
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$selectedTab, $employee['id']]);

} else {

    $stmt = $pdo->prepare("
        SELECT 
            o.*,
            sc.name AS garment_name
        FROM outsource_orders o
        LEFT JOIN sub_categories sc ON o.sub_category_id = sc.id
        WHERE o.order_status = ?
        AND o.assigned_employee_id = ?
        AND o.is_deleted = 0
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$selectedTab, $employee['id']]);
}

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Outsource Orders - Sogasu";
$headerTitle = "Outsource Orders";
$activePage = "orders";

include 'includes/outsource-header.php';
?>

<div class="container" style="padding-bottom:100px;">

    <div style="
        display:flex;
        gap:10px;
        overflow-x:auto;
        margin-bottom:20px;
        padding-bottom:6px;
    ">
        <?php foreach ($validTabs as $tab): ?>
            <a href="outsource-orders.php?tab=<?= urlencode($tab) ?>" style="
                text-decoration:none;
                white-space:nowrap;
                padding:10px 16px;
                border-radius:999px;
                font-size:14px;
                font-weight:600;
                <?= $selectedTab === $tab
                    ? 'background:#db2777;color:white;'
                    : 'background:white;color:#64748b;border:1px solid #fbcfe8;' ?>
            ">
                <?php
                $count = $tab === 'all'
                    ? $allCount
                    : ($statusCounts[$tab] ?? 0);
                ?>

                <?= ucwords($tab) ?> (<?= $count ?>)
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($orders)): ?>
        <div class="card" style="padding:40px;text-align:center;">
            <i class="ri-inbox-line" style="font-size:50px;color:#cbd5e1;"></i>
            <div style="margin-top:12px;font-weight:700;">
                No orders found
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($orders as $order): ?>
            <?php
            $statusBg = '#fce7f3';
            $statusColor = '#be185d';

            switch ($order['order_status']) {
                case 'pending':
                    $statusBg = '#fef3c7';
                    $statusColor = '#b45309';
                    break;
                case 'accepted':
                    $statusBg = '#dcfce7';
                    $statusColor = '#15803d';
                    break;
                case 'rejected':
                    $statusBg = '#fee2e2';
                    $statusColor = '#b91c1c';
                    break;
                case 'approved':
                    $statusBg = '#dbeafe';
                    $statusColor = '#1d4ed8';
                    break;
                case 'in progress':
                    $statusBg = '#ffedd5';
                    $statusColor = '#c2410c';
                    break;
                case 'completed':
                    $statusBg = '#d1fae5';
                    $statusColor = '#047857';
                    break;
            }
            ?>
            <div class="card" style="
                padding:20px;
                margin-bottom:16px;
                border-radius:20px;
                background:white;
                box-shadow:0 10px 25px rgba(0,0,0,.06);
                border:1px solid #fce7f3;
            ">

                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div style="font-size:18px;font-weight:700;">
                        <?= htmlspecialchars($order['order_code']) ?>
                    </div>

                    <span style="
                        background:<?= $statusBg ?>;
                        color:<?= $statusColor ?>;
                        padding:6px 12px;
                        border-radius:999px;
                        font-size:12px;
                        font-weight:700;
                    ">
                        <?= ucwords($order['order_status']) ?>
                    </span>
                </div>

                <div style="margin-top:14px;color:#64748b;font-size:14px;">
                    <div style="margin-top:5px;">
                        <b>Garment:</b>
                        <?= htmlspecialchars($order['garment_name'] ?? 'N/A') ?>
                    </div>

                    <div style="margin-top:5px;">
                        <b>Notes:</b>
                        <?= htmlspecialchars($order['notes'] ?? 'N/A') ?>
                    </div>
                    <div>
                        <b>Total:</b> ₹<?= number_format($order['total_amount'], 2) ?>
                    </div>

                    <div style="margin-top:5px;">
                        <b>Due:</b>
                        <?= !empty($order['due_date']) ? date('d M Y', strtotime($order['due_date'])) : 'N/A' ?>
                    </div>

                    <div style="margin-top:5px;">
                        <b>Created:</b>
                        <?= date('d M Y', strtotime($order['created_at'])) ?>
                    </div>
                    <div style="margin-top:15px;">
                        <a href="view-outsource-order.php?id=<?= $order['id'] ?>" style="
                            display:inline-flex;
                            align-items:center;
                            gap:5px;
                            background:#2563eb;
                            color:white;
                            text-decoration:none;
                            padding:10px 18px;
                            border-radius:12px;
                            font-size:14px;
                            font-weight:600;
                        ">
                            View
                        </a>
                    </div>
                    <?php if ($order['order_status'] === 'approved'): ?>
                        <form method="POST" style="margin-top:16px;">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <input type="hidden" name="new_status" value="in progress">

                            <button type="submit" style="
                                    background:#f59e0b;
                                    color:white;
                                    border:none;
                                    padding:10px 18px;
                                    border-radius:12px;
                                    font-size:14px;
                                    font-weight:600;
                                    cursor:pointer;
                                ">
                                Start Work
                            </button>
                        </form>

                    <?php elseif ($order['order_status'] === 'in progress'): ?>
                        <form method="POST" style="margin-top:16px;">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <input type="hidden" name="new_status" value="completed">

                            <div style="display:flex; gap:10px; margin-top:15px; align-items:center;">
                                <button type="submit" style="
                                    background:#16a34a;
                                    color:white;
                                    border:none;
                                    padding:10px 18px;
                                    border-radius:12px;
                                    font-size:14px;
                                    font-weight:600;
                                    cursor:pointer;
                                ">
                                    Mark Completed
                                </button>

                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php if ($success === 'status_updated'): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Order Completed Successfully',
            text: 'Order status updated successfully.',
            confirmButtonColor: '#16a34a'
        }).then(() => {
            window.history.replaceState({}, document.title, window.location.pathname);
        });
    </script>
<?php endif; ?>
<?php include 'includes/outsource-bottom-nav.php'; ?>