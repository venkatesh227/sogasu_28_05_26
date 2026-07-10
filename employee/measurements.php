<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header('Location: login.php');
    exit();
}

$stmt = $pdo->prepare("SELECT id, first_name, last_name, job_role FROM employees WHERE user_id = ? AND is_deleted = 0");
$stmt->execute([$_SESSION['user_id']]);
$emp = $stmt->fetch();

if (!$emp || $emp['job_role'] === 'Supervisor') {
    header('Location: dashboard.php');
    exit();
}

$employee_id = $emp['id'];
$pageTitle = 'Measurements - Sogasu';
$headerTitle = 'Measurements';
$activePage = 'measurements';

// Find appointments assigned to this employee
$stmt = $pdo->prepare("SELECT
    a.id,
    a.user_id,
    a.order_id AS order_code,
    a.customer_name,
    a.customer_phone,
    a.appointment_date,
    a.appointment_time,
    a.status,
    a.visit_type,
    a.notes,
    a.measurement_id,
    a.category_id,
    a.sub_category_id,
    cu.first_name AS cust_first,
    cu.last_name AS cust_last,
    cu.phone AS cust_phone,
    cu.email AS cust_email,
    sc.name AS garment
FROM appointments a
LEFT JOIN customers cu ON a.user_id = cu.user_id
LEFT JOIN sub_categories sc ON a.sub_category_id = sc.id
WHERE a.assigned_employee_id = ?
AND a.is_deleted = 0
AND a.appointment_source = 'customer'
ORDER BY a.appointment_date DESC, a.appointment_time ASC");
$stmt->execute([$employee_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$measurements_by_order = [];
if (!empty($orders)) {
    $measurement_ids = array_filter(array_column($orders, 'measurement_id'));
    if (!empty($measurement_ids)) {
        $placeholders = implode(',', array_fill(0, count($measurement_ids), '?'));
        $stmt = $pdo->prepare("SELECT id, measurements FROM customer_measurements WHERE id IN ($placeholders)");
        $stmt->execute($measurement_ids);
        $measurement_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($measurement_rows as $row) {
            $measurements_by_order[$row['id']] = json_decode($row['measurements'], true) ?: [];
        }
    }
}

include 'includes/header.php';
?>

<div class="container">
    <div class="card" style="margin-bottom:1rem;">
        <div class="section-title">Measurements Assigned to You</div>
        <p style="color: #64748b; margin-bottom: 1rem;">See assigned customer orders and their measurement status.</p>
        <?php if (empty($orders)): ?>
            <div style="padding: 2rem; text-align: center; color: #64748b; background: #f8fafc; border-radius: 12px;">
                <i class="ri-search-line" style="font-size: 2rem; margin-bottom: 0.75rem;"></i>
                <div style="font-size: 1rem; font-weight: 700; margin-bottom: 0.25rem;">No assigned customer orders</div>
                <div style="font-size: 0.95rem;">You have no customer orders assigned or all assigned customer orders are rejected/deleted.</div>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <div class="card" style="margin-bottom: 1rem; border: 1px solid #e5e7eb;">
                    <div style="display:flex; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
                        <div>
                            <div style="font-weight:700; font-size:1rem; color:#111827;">Order #<?= htmlspecialchars($order['order_code']) ?></div>
                            <div style="color:#475569; margin-top:0.25rem;">Customer: <?= htmlspecialchars($order['cust_first'] . ' ' . ($order['cust_last'] ?? '')) ?></div>
                            <div style="color:#475569; margin-top:0.25rem;">Phone: <?= htmlspecialchars($order['cust_phone'] ?: 'N/A') ?></div>
                            <div style="color:#475569; margin-top:0.25rem;">Garment: <?= htmlspecialchars($order['garment'] ?: 'General') ?></div>
                        </div>
                        <div style="text-align:right; min-width:180px;">
                            <div style="font-size:0.9rem; color:#6b7280;">Appointment</div>
                            <div style="font-weight:700; color:#111827; margin-top:0.25rem;"><?= date('d M Y', strtotime($order['appointment_date'])) ?> <?= substr($order['appointment_time'], 0, 5) ?></div>
                            <div style="margin-top:0.5rem;"><span style="padding: 0.35rem 0.75rem; border-radius:999px; background:#eef2ff; color:#4338ca; font-size:0.8rem; font-weight:700; text-transform:capitalize;"><?= htmlspecialchars($order['status'] ?: 'unknown') ?></span></div>
                        </div>
                    </div>
                    <?php
                    $measurements = [];
                    if (!empty($order['measurement_id'])) {
                        $measurements = $measurements_by_order[$order['measurement_id']] ?? [];
                    }

                    $size_value = '';
                    foreach (['size', 'Size', 'SIZE'] as $size_key) {
                        if (isset($measurements[$size_key]) && trim($measurements[$size_key]) !== '') {
                            $size_value = $measurements[$size_key];
                            break;
                        }
                    }
                    ?>
                    <div style="margin-top:1rem; padding:1rem; background:#f8fafc; border-radius:12px;">
<?php if (empty($measurements)): ?>

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;">

    <div>
        <div style="font-weight:700;">
            Measurement not added
        </div>

        <div style="color:#64748b;">
            Customer measurements are not available.
        </div>
    </div>

    <a href="add-measurement.php?appointment_id=<?= $order['id'] ?>"
       class="btn btn-primary">
        Add Measurement
    </a>

</div>

<?php else: ?>
                            <?php if ($size_value !== ''): ?>
                                <div style="margin-bottom:0.75rem; padding:0.75rem; background:white; border:1px solid #c7d2fe; border-radius:10px;">
                                    <div style="font-size:0.8rem; color:#4338ca; margin-bottom:0.35rem; text-transform:uppercase; font-weight:700;">Size</div>
                                    <div style="font-size:1rem; font-weight:700; color:#111827;"><?= htmlspecialchars($size_value) ?></div>
                                </div>
                            <?php endif; ?>
                            <div style="font-size:0.95rem; font-weight:700; color:#111827; margin-bottom:0.75rem;">Measurements</div>
                            <div style="text-align:right;margin-bottom:15px;">

<a href="add-measurement.php?appointment_id=<?= $order['id'] ?>"
class="btn btn-warning btn-sm">

Update Measurement

</a>

</div>
                            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:0.75rem;">
                                <?php foreach ($measurements as $key => $value): ?>
                                    <?php if (in_array($key, ['size', 'Size', 'SIZE'], true)) continue; ?>
                                    <div style="background:white; border:1px solid #e5e7eb; border-radius:10px; padding:0.75rem;">
                                        <div style="font-size:0.8rem; color:#6b7280; margin-bottom:0.25rem; text-transform:capitalize;"><?= htmlspecialchars($key) ?></div>
                                        <div style="font-size:1rem; font-weight:700; color:#111827;"><?= htmlspecialchars($value) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/bottom-nav.php'; ?>
