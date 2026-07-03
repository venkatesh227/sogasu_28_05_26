<?php
session_start();
include '../includes/db.php';

$id = $_GET['id'] ?? null;
$type = $_GET['type'] ?? 'admin';
if (!$id) {
    die("Invalid Order ID");
}
// ===== FETCH ORDER DATA =====
if ($type === 'customer') {

    $stmt = $pdo->prepare("
        SELECT 
            co.id,
            co.order_code,
            co.user_id as customer_id,
            co.category_id,
            co.sub_category_id,

            co.status as order_status,
            co.status_history,
            co.additional_notes as notes,

            co.assigned_employee_id,
            co.supervisor_id,
            co.rack_id,

            co.base_price,
            co.extra_charges,
            co.total_amount,
            co.appointment_date as due_date,

            'customer' as order_type,

            c.first_name,
            c.last_name,

            cat.category_name,
            sc.name as sub_category_name

        FROM customer_orders co
        JOIN customers c ON co.user_id = c.user_id
        LEFT JOIN categories cat ON co.category_id = cat.id
        LEFT JOIN sub_categories sc ON co.sub_category_id = sc.id
        WHERE co.id = ?
    ");

} else {

    $stmt = $pdo->prepare("
        SELECT 
            o.id,
            o.order_code,
            o.customer_id,
            o.category_id,
            o.sub_category_id,

            o.order_status as order_status,
            o.status_history,
            o.notes as notes,

            o.assigned_employee_id,
            o.supervisor_id,
            o.rack_id,

            o.base_price,
            o.extra_charges,
            o.total_amount,
            o.due_date,

            'admin' as order_type,

            c.first_name,
            c.last_name,

            cat.category_name,
            sc.name as sub_category_name

        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        LEFT JOIN categories cat ON o.category_id = cat.id
        LEFT JOIN sub_categories sc ON o.sub_category_id = sc.id
        WHERE o.id = ?
    ");
}

$stmt->execute([$id]);
$order = $stmt->fetch();
$order_type = 'inhouse';

if (($type ?? 'admin') === 'customer') {
    $order_type = 'customer';
} else {
    $order_type = 'inhouse';
}
// ===== HANDLE POST UPDATE =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
$assigned_id = !empty($_POST['assigned_employee_id'])
    ? $_POST['assigned_employee_id']
    : $order['assigned_employee_id'];
        $status = $_POST['order_status'] ?? 'pending';
        $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $notes = $_POST['notes'] ?? '';
        $rack_id = $_POST['rack_id'] ?: null;
        $existingHistory = $order['status_history'] ?? '';

        if (!empty($existingHistory)) {

            $historyArray = explode(',', $existingHistory);

            if (!in_array($status, $historyArray)) {
                $existingHistory .= ',' . $status;
            }

        } else {

            $existingHistory = 'pending';

            if ($status !== 'pending') {
                $existingHistory .= ',' . $status;
            }

        }

        if (($order['order_type'] ?? '') === 'customer') {

            $stmt = $pdo->prepare("
        UPDATE customer_orders
        SET assigned_employee_id = ?, 
        status = ?, 
        additional_notes = ?,
        status_history = ?,
        rack_id = ?,
        appointment_date = ?,
        updated_at = NOW()
        WHERE id = ?
    ");

            $stmt->execute([
                $assigned_id,
                $status,
                $notes,
                $existingHistory,
                $rack_id,
                $due_date,
                $id
            ]);

        } else {

            $stmt = $pdo->prepare("
                UPDATE orders 
                SET assigned_employee_id = ?, 
                    order_status = ?, 
                    notes = ?,
                    status_history = ?,
                    rack_id = ?,
                    due_date = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $assigned_id,
                $status,
                $notes,
                $existingHistory,
                $rack_id,
                $due_date,
                $id
            ]);
        }


$_SESSION['success'] = "Order updated successfully!";
        header("Location: orders.php");
        exit;
    } catch (PDOException $e) {
        $error = "Update failed: " . $e->getMessage();
    }
}


if (!$order) {
    die("Order not found");
}

// ===== FETCH EMPLOYEES =====
$employees = $pdo->query("
    SELECT id, first_name, last_name, job_role
    FROM employees
    WHERE status = 1
    AND is_deleted = 0
    AND job_role = 'Supervisor'
    ORDER BY first_name ASC
")->fetchAll();

// ===== FETCH RACKS =====
$racks = $pdo->query("
    SELECT id, rack_name, status
    FROM racks
    ORDER BY rack_name ASC
")->fetchAll();
// ===== ADDITIONAL SERVICES =====
if ($order_type === 'customer') {
    $order_services = [];
} else {
    $stmt = $pdo->prepare("
        SELECT os.service_price, s.service_name 
        FROM order_services os
        JOIN services s ON os.service_id = s.id
        WHERE os.order_id = ?
        AND os.order_type = ?
    ");
    $stmt->execute([$id, $order_type]);
    $order_services = $stmt->fetchAll();
}

$pageTitle = "Edit Order #" . $order['order_code'] . " - Sogasu";
$activePage = "orders";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div>
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Edit Order
                    #<?= htmlspecialchars($order['order_code']) ?></h2>
                <p class="text-muted">Reassign employee or update status</p>
            </div>
            <a href="orders.php" class="btn"
                style="background: white; border: 1px solid #e2e8f0; color: #64748b; text-decoration: none; padding: 0.5rem 1rem; border-radius: 6px; display: flex; align-items: center; gap: 0.5rem;">
                <i class="ri-arrow-left-line"></i> Back to List
            </a>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div
            style="background: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid #fecaca;">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <form method="POST" style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">

        <!-- Left Column -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">

            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3
                    style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="ri-user-line" style="color: var(--primary);"></i> Customer & Garment
                </h3>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Customer Name</label>
                        <input type="text" class="form-control"
                            value="<?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?>" readonly
                            style="background: #f8fafc;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Garment Type</label>
                        <input type="text" class="form-control"
                            value="<?= htmlspecialchars($order['category_name'] . ' - ' . $order['sub_category_name']) ?>"
                            readonly style="background: #f8fafc;">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1.5rem;">
                    <label class="form-label">Design Notes / Instructions</label>
                    <textarea name="notes" class="form-control" rows="5"
                        placeholder="Enter stitching instructions..."><?= htmlspecialchars($order['notes']) ?></textarea>
                </div>
            </div>

            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3
                    style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="ri-ruler-2-line" style="color: var(--primary);"></i> Pricing Info
                </h3>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Base Price</label>
                        <div style="font-weight: 700; font-size: 1.1rem;">₹
                            <?= number_format($order['base_price'], 2) ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Extra Charges</label>
                        <div style="font-weight: 700; font-size: 1.1rem;">₹
                            <?= number_format($order['extra_charges'], 2) ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Total Amount</label>
                        <div style="font-weight: 800; font-size: 1.2rem; color: var(--primary);">₹
                            <?= number_format($order['total_amount'], 2) ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($order_services)): ?>
                    <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px dashed #e2e8f0;">
                        <label class="form-label"
                            style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8;">Included Services</label>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 0.5rem;">
                            <?php foreach ($order_services as $srv): ?>
                                <div
                                    style="background: #f1f5f9; padding: 0.5rem 0.75rem; border-radius: 6px; font-size: 0.85rem; color: #475569; border: 1px solid #e2e8f0;">
                                    <i class="ri-checkbox-circle-line" style="color: #22c55e;"></i>
                                    <?= htmlspecialchars($srv['service_name']) ?> (₹<?= number_format($srv['service_price']) ?>)
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column: Reassign & Status -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">

            <div
                style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px; border-top: 4px solid var(--primary);">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1.5rem;">Workflow
                    Management</h3>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="form-label">Assign / Reassign To</label>
                    <p style="font-size: 0.75rem; color: #64748b; margin-top: -0.25rem; margin-bottom: 0.5rem;">Select
                        an employee to handle this order.</p>
                    <select name="assigned_employee_id" class="form-select"
                        style="border-color: #4f46e5; border-width: 2px;">
                        <option value="">-- Select Employee --</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>" <?= ($order['assigned_employee_id'] == $emp['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                                (<?= htmlspecialchars($emp['job_role']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="form-label">Order Status</label>
                    <select name="order_status" class="form-select">
                        <?php

                        $statuses = [
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

                        foreach ($statuses as $status):
                            ?>

                            <option value="<?= $status ?>" <?= ($order['order_status'] == $status) ? 'selected' : '' ?>>

                                <?= ucwords(str_replace('_', ' ', $status)) ?>

                            </option>

                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                <label class="form-label">Rack Assignment</label>

                <select name="rack_id" class="form-select">
                    <option value="">-- Select Rack --</option>

                    <?php foreach ($racks as $rack): ?>
                        <option
                            value="<?= $rack['id'] ?>"
                            <?= ($order['rack_id'] == $rack['id']) ? 'selected' : '' ?>
                            <?= ($rack['status'] == 'Occupied' && $order['rack_id'] != $rack['id']) ? 'disabled' : '' ?>
                        >
                            <?= htmlspecialchars($rack['rack_name']) ?>
                            (<?= $rack['status'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                </div>
                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="form-label">Due Date</label>
                    <input 
                        type="date"
                        name="due_date"
                        class="form-control"
                        value="<?= !empty($order['due_date']) ? date('Y-m-d', strtotime($order['due_date'])) : '' ?>">
                </div>

                <div style="border-top: 1px solid #f1f5f9; margin: 1.5rem 0;"></div>

                <button type="submit" class="btn btn-primary"
                    style="width: 100%; justify-content: center; padding: 1rem; font-weight: 700; font-size: 1rem;">
                    <i class="ri-save-line"></i> Save Changes
                </button>

                <a href="orders.php"
                    style="display: block; text-align: center; margin-top: 1rem; font-size: 0.85rem; color: #64748b; text-decoration: none;">
                    Discard and Go Back
                </a>
            </div>

        </div>

    </form>
</main>

<style>
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .form-label {
        font-size: 0.875rem;
        font-weight: 600;
        color: #475569;
    }

    .form-control,
    .form-select {
        padding: 0.75rem;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 0.95rem;
        width: 100%;
        outline: none;
        transition: all 0.2s;
        font-family: inherit;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: #4f46e5;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }

    .btn-primary {
        background: #4f46e5;
        color: white;
        border: none;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-primary:hover {
        background: #4338ca;
        transform: translateY(-1px);
    }
</style>

<?php include 'includes/footer.php'; ?>