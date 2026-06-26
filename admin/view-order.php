<?php
session_start();
$activePage = "all-orders";
include '../includes/db.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    die("Invalid Order ID");
}

// ===== MAIN ORDER DATA =====
$stmt = $pdo->prepare("
    SELECT 
        o.*,
        c.first_name,
        c.address,
        COALESCE(u.mobile, c.phone) AS mobile,
        cat.category_name AS category_name,
        sc.name AS sub_category_name,
        e.first_name AS emp_first,
        e.last_name AS emp_last,
        e.job_role,
        s.first_name AS sup_first,
        s.last_name AS sup_last,
        r.rack_name
    FROM orders o
    JOIN customers c ON o.customer_id = c.id
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN categories cat ON o.category_id = cat.id
    LEFT JOIN sub_categories sc ON o.sub_category_id = sc.id
    LEFT JOIN employees e ON o.assigned_employee_id = e.id
    LEFT JOIN employees s ON o.supervisor_id = s.id
    LEFT JOIN racks r ON o.rack_id = r.id
    WHERE o.id = ?
");

$stmt->execute([$id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

$is_customer_order = false;

if (!$order) {

    $stmt = $pdo->prepare("
        SELECT 
            co.id,
            co.order_code,
            co.user_id as customer_id,
            co.category_id,
            co.sub_category_id,
            NULL as fabric_details,
            co.additional_notes as notes,
            co.status as order_status,
            co.supervisor_id,
            co.assigned_employee_id,
            co.rack_id,
            co.base_price,
            co.extra_charges,
            co.total_amount,

            0 as advance_amount,
            0 as paid_amount,

            co.appointment_date as due_date,
            co.created_at,

            c.first_name,
            c.address,
            COALESCE(u.mobile, c.phone) AS mobile,

            cat.category_name AS category_name,
            sc.name AS sub_category_name,

            e.first_name AS emp_first,
            e.last_name AS emp_last,
            e.job_role,

            s.first_name AS sup_first,
            s.last_name AS sup_last,

            r.rack_name

        FROM customer_orders co

        JOIN customers c 
            ON co.user_id = c.user_id

        LEFT JOIN users u 
            ON c.user_id = u.id

        LEFT JOIN categories cat 
            ON co.category_id = cat.id

        LEFT JOIN sub_categories sc 
            ON co.sub_category_id = sc.id

        LEFT JOIN employees e 
            ON co.assigned_employee_id = e.id

        LEFT JOIN employees s 
            ON co.supervisor_id = s.id

        LEFT JOIN racks r 
            ON co.rack_id = r.id

        WHERE co.id = ?
    ");

    $stmt->execute([$id]);

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    $is_customer_order = true;
}

if (!$order) {
    die("Order not found");
}
$order_type = $order['order_type'] ?? 'inhouse';
// ======================================================
// CHECK CUSTOMER ORDER DATA
// ======================================================

$customerOrderStmt = $pdo->prepare("
    SELECT 
        co.*,
        cm.measurements AS customer_measurements_json
    FROM customer_orders co
    LEFT JOIN customer_measurements cm 
        ON co.customer_measurement_id = cm.id
    WHERE co.order_code = ?
    LIMIT 1
");

$customerOrderStmt->execute([$order['order_code']]);

$customerOrder = $customerOrderStmt->fetch(PDO::FETCH_ASSOC);

// If customer-created order exists,
// override required fields dynamically
if ($customerOrder) {

    // Override order values
    $order['appointment_date'] = $customerOrder['appointment_date'];
    $order['appointment_time'] = $customerOrder['appointment_time'];
    $order['additional_notes'] = $customerOrder['additional_notes'];
    $order['assigned_employee_id'] = $customerOrder['assigned_employee_id'];
    $order['supervisor_id'] = $customerOrder['supervisor_id'];
    $order['rack_id'] = $customerOrder['rack_id'];
    $order['base_price'] = $customerOrder['base_price'];
    $order['extra_charges'] = $customerOrder['extra_charges'];
    $order['total_amount'] = $customerOrder['total_amount'];
    $order['order_status'] = $customerOrder['status'];

    // Store customer measurements
    $customerMeasurements = json_decode(
        $customerOrder['customer_measurements_json'],
        true
    );
}
$pageTitle = "View Order #" . ($order['order_code'] ?? '') . " - Sogasu";

// ===== MEASUREMENTS =====
// ===== MEASUREMENTS =====

$measurements = [];

// If customer measurements exist
if (!empty($customerMeasurements) && is_array($customerMeasurements)) {

    foreach ($customerMeasurements as $key => $value) {

        $measurements[] = [
            'key_name' => $key,
            'measurement_value' => $value
        ];
    }

} else {

    // Fallback to admin measurements
    $stmt = $pdo->prepare("
        SELECT key_name, measurement_value
        FROM order_measurements
        WHERE order_id = ?
        AND order_type = ?
    ");

    $stmt->execute([$id, $order_type]);

    $measurements = $stmt->fetchAll();
}

// ===== ADDITIONAL SERVICES =====
if ($is_customer_order) {

    $order_services = [];

} else {

    $stmt = $pdo->prepare("
        SELECT DISTINCT os.service_id, os.service_price, s.service_name
        FROM order_services os
        JOIN services s ON os.service_id = s.id
        WHERE os.order_id = ?
        AND os.order_type = ?
    ");

    $stmt->execute([$id, $order_type]);

    $order_services = $stmt->fetchAll();
}

// ===== IMAGES =====
if ($is_customer_order) {

    $images = [];

    if (!empty($customerOrder['material_image'])) {

        $images[] = [
            'image_path' => $customerOrder['material_image'],
            'image_type' => 'fabric'
        ];
    }

    if (!empty($customerOrder['referral_image'])) {

        $images[] = [
            'image_path' => $customerOrder['referral_image'],
            'image_type' => 'sample'
        ];
    }

} else {

    $stmt = $pdo->prepare("
        SELECT image_path, image_type 
        FROM order_images 
        WHERE order_id = ? 
        AND is_deleted = 0
    ");

    $stmt->execute([$id]);

    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$fabric_images = array_filter($images, function ($img) {
    return $img['image_type'] === 'fabric';
});
$sample_images = array_filter($images, function ($img) {
    return $img['image_type'] === 'sample';
});

// ===== CALCULATIONS =====
$total = (float) $order['total_amount'];

$advance = (float) $order['advance_amount'];

$paid = (float) $order['paid_amount'];

$balance = $total - $paid;

// ===== STATUS BADGE CLASS =====
$status = strtolower($order['order_status']);

switch ($status) {
    case 'pending':
        $statusClass = 'badge-warning';
        break;
    case 'processing':
        $statusClass = 'badge-info';
        break;
    case 'completed':
        $statusClass = 'badge-success';
        break;
    default:
        $statusClass = 'badge-warning';
        break;
}

include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div>
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Order #<?= $order['order_code'] ?>
                    </h2>
                    <span class="badge <?= $statusClass ?>"
                        style="font-size: 0.85rem;"><?= ucfirst(strtolower($order['order_status'])) ?></span>
                </div>
                <p class="text-muted">Created on <?= date('d M, Y', strtotime($order['created_at'])) ?></p>
            </div>
            <div style="display: flex; gap: 1rem;">
                <button class="btn" onclick="history.back()"
                    style="background: white; border: 1px solid #e2e8f0; color: #64748b;">
                    <i class="ri-arrow-left-line"></i> Back
                </button>
                <button class="btn btn-primary" onclick="window.location.href='add-order.php?id=<?= $order['id'] ?>'">
                    <i class="ri-pencil-line"></i> Edit / Payment
                </button>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">

        <!-- Left Column: Details -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">

            <!-- Customer & Garment Header -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                <div
                    style="padding: 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between;">
                    <div>
                        <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 0.25rem;">
                            <?= $order['first_name'] ?>
                        </h3>
                        <div style="color: #64748b; font-size: 0.9rem;"><i class="ri-phone-line"></i> +91
                            <?= $order['mobile'] ?>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 0.25rem;">
                            <?= $order['category_name'] ?>
                        </h3>
                        <div style="color: #64748b; font-size: 0.9rem;"><?= $order['category_name'] ?> >
                            <?= $order['sub_category_name'] ?>
                        </div>
                    </div>
                </div>
                <div style="padding: 1.5rem; display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        <div
                            style="font-size: 0.85rem; color: #94a3b8; text-transform: uppercase; font-weight: 600; margin-bottom: 0.5rem;">
                            Fabric Details</div>
                        <div style="color: #334155;"><?= $order['fabric_details'] ?></div>
                    </div>
                    <div
                        style="background: #fff1f2; padding: 0.5rem 1rem; border-radius: 6px; border: 1px solid #fda4af;">
                        <div
                            style="font-size: 0.8rem; color: #be123c; text-transform: uppercase; font-weight: 700; margin-bottom: 0.25rem;">
                            Due Date</div>
                        <div style="color: #be123c; font-weight: 700; font-size: 1.1rem;">
                            <?= date('d M, Y', strtotime($order['due_date'])) ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Measurements Review -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem;">
                <h3
                    style="font-size: 1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.5px;">
                    Measurements</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 1rem;">

                    <?php if (empty($measurements)): ?>
                        <div
                            style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: #94a3b8; background: #f8fafc; border-radius: 8px; border: 1px dashed #e2e8f0;">
                            No measurements recorded for this order.
                        </div>
                    <?php else: ?>
                        <?php foreach ($measurements as $m): ?>
                            <div style="background: #f8fafc; padding: 0.75rem; border-radius: 6px;">
                                <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem;">
                                    <?= htmlspecialchars($m['key_name']) ?>
                                </div>
                                <div style="font-weight: 600; color: #0f172a;">
                                    <?= htmlspecialchars($m['measurement_value']) ?>
                                    <?= strtoupper($order['measurement_unit'] ?? 'CMS') === 'CMS' ? ' cm' : '"' ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            </div>

            <!-- Design Notes -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem;">
                <h3
                    style="font-size: 1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.5px;">
                    Design Notes</h3>
                <p style="color: #475569; line-height: 1.6;">
                    <?= nl2br(htmlspecialchars($order['notes'] ?? 'No special instructions.')) ?>
                </p>
            </div>

            <!-- Reference Images -->
            <?php if (!empty($fabric_images) || !empty($sample_images)): ?>
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem;">
                    <h3
                        style="font-size: 1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.5px;">
                        Attached Images</h3>

                    <?php if (!empty($fabric_images)): ?>
                        <div style="margin-bottom: 1rem;">
                            <div style="font-size: 0.85rem; font-weight: 600; color: #64748b; margin-bottom: 0.5rem;">Fabric
                                Photos</div>
                            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                                <?php foreach ($fabric_images as $img): ?>
                                    <a href="../<?= htmlspecialchars($img['image_path']) ?>" target="_blank"
                                        style="display: block; width: 80px; height: 80px; border-radius: 6px; overflow: hidden; border: 1px solid #e2e8f0; transition: transform 0.2s;"
                                        onmouseover="this.style.transform='scale(1.05)'"
                                        onmouseout="this.style.transform='scale(1)'">
                                        <img src="../<?= htmlspecialchars($img['image_path']) ?>"
                                            style="width: 100%; height: 100%; object-fit: cover;">
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($sample_images)): ?>
                        <div>
                            <div style="font-size: 0.85rem; font-weight: 600; color: #64748b; margin-bottom: 0.5rem;">Sample
                                References</div>
                            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                                <?php foreach ($sample_images as $img): ?>
                                    <a href="../<?= htmlspecialchars($img['image_path']) ?>" target="_blank"
                                        style="display: block; width: 80px; height: 80px; border-radius: 6px; overflow: hidden; border: 1px solid #e2e8f0; transition: transform 0.2s;"
                                        onmouseover="this.style.transform='scale(1.05)'"
                                        onmouseout="this.style.transform='scale(1)'">
                                        <img src="../<?= htmlspecialchars($img['image_path']) ?>"
                                            style="width: 100%; height: 100%; object-fit: cover;">
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>

        <!-- Right Column: Status & Payment -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">

            <!-- Assignment Info -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem;">
                <h3 style="font-size: 1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Workflow Status</h3>

                <div style="margin-bottom: 1.5rem;">
                    <div
                        style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; font-weight: 700; margin-bottom: 0.5rem;">
                        Supervisor</div>
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($order['sup_first'] ?? 'S') ?>&background=fdf2f8&color=db2777"
                            style="width: 36px; height: 36px; border-radius: 50%;">
                        <div>
                            <div style="font-weight: 600; color: #1e293b;">
                                <?= $order['sup_first'] ? ($order['sup_first'] . ' ' . $order['sup_last']) : '<span style="color:#94a3b8; font-style:italic;">Not Assigned</span>' ?>
                            </div>
                            <div style="font-size: 0.8rem; color: #64748b;">Managing Order</div>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <div
                        style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; font-weight: 700; margin-bottom: 0.5rem;">
                        Assigned Employee</div>
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($order['emp_first'] ?? 'E') ?>&background=eef2ff&color=4338ca"
                            style="width: 36px; height: 36px; border-radius: 50%;">
                        <div>
                            <div style="font-weight: 600; color: #1e293b;">
                                <?= $order['emp_first'] ? ($order['emp_first'] . ' ' . $order['emp_last']) : '<span style="color:#94a3b8; font-style:italic;">Waiting for Supervisor</span>' ?>
                            </div>
                            <div style="font-size: 0.8rem; color: #64748b;">
                                <?= $order['job_role'] ?: 'Tailor / Master' ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <div
                        style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; font-weight: 700; margin-bottom: 0.5rem;">
                        Rack Location</div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; color: #1e293b; font-weight: 600;">
                        <i class="ri-archive-line" style="color: #f59e0b;"></i>
                        <?= $order['rack_name'] ?: '<span style="color:#94a3b8; font-style:italic; font-weight:400;">Not Allocated</span>' ?>
                    </div>
                </div>
            </div>

            <!-- Payment Summary -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem;">
                <h3 style="font-size: 1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Payment Details</h3>

                <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; color: #64748b;">
                    <span>Base Charges</span>
                    <span>₹ <?= number_format($order['base_price'], 2) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; color: #64748b;">
                    <span>Extra / Work</span>
                    <span>₹ <?= number_format($order['extra_charges'], 2) ?></span>
                </div>

                <?php if (!empty($order_services)): ?>
                    <div
                        style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; font-weight: 700; margin: 0.5rem 0;">
                        Additional Services</div>
                    <?php foreach ($order_services as $srv): ?>
                        <div
                            style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; color: #64748b; font-size: 0.9rem;">
                            <span><i class="ri-add-circle-line" style="font-size: 0.8rem;"></i>
                                <?= htmlspecialchars($srv['service_name']) ?></span>
                            <span>₹ <?= number_format($srv['service_price'], 2) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div style="border-top: 1px dashed #cbd5e1; margin: 0.75rem 0;"></div>
                <div
                    style="display: flex; justify-content: space-between; margin-bottom: 1rem; font-weight: 600; color: #1e293b; font-size: 1.1rem;">
                    <span>Total Amount</span>
                    <span>₹ <?= number_format($total, 2) ?></span>
                </div>

                <div style="background: #f1f5f9; padding: 1rem; border-radius: 6px; margin-bottom: 1rem;">
                    <div
                        style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.9rem;">
                        <span style="color: #64748b;">Advance Paid</span>
                        <span style="font-weight: 600; color: #15803d;">
                            ₹ <?= number_format($advance, 2) ?>
                            <?php if (!empty($order['advance_payment_mode'])): ?>
                                <span
                                    style="font-size: 0.75rem; color: #64748b; font-weight: normal; margin-left: 0.25rem;">(<?= htmlspecialchars($order['advance_payment_mode']) ?>)</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.9rem;">
                        <span style="color: #64748b;">Balance Due</span>
                        <span style="font-weight: 600; color: #b91c1c;">₹ <?= number_format($balance, 2) ?></span>
                    </div>
                </div>

                <div style="font-size: 0.85rem; color: #94a3b8; text-align: center;">
                    Payment Status: <strong>
                        <?php
                        echo $balance <= 0 ? 'Paid' : ($advance > 0 ? 'Partially Paid' : 'Unpaid');
                        ?>
                    </strong>
                </div>
            </div>

        </div>

    </div>
</main>
<style>
    .badge {
        display: inline-block;
        padding: 4px 10px;
        font-size: 0.75rem;
        font-weight: 600;
        border-radius: 6px;
        text-transform: capitalize;
    }

    /* Pending */
    .badge-warning {
        background: #fff7ed;
        color: #c2410c;
    }

    /* Processing */
    .badge-info {
        background: #eff6ff;
        color: #1d4ed8;
    }

    /* Completed */
    .badge-success {
        background: #ecfdf5;
        color: #047857;
    }
</style>

<?php include 'includes/footer.php'; ?>