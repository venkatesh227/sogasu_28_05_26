<?php
$pageTitle = "Billing - Sogasu";
$activePage = "billing";
require '../includes/db.php';
if (
    isset($_GET['action']) &&
    $_GET['action'] === 'get_bill_details' &&
    isset($_GET['id'])
) {

    header('Content-Type: application/json');

    $id = (int) $_GET['id'];

    $stmt = $pdo->prepare("

    SELECT

        b.*,
        COALESCE((
            SELECT SUM(os.service_price)
            FROM order_services os
            WHERE os.order_id = b.order_id
            AND (
                (b.order_type = 'orders' AND os.order_type = 'inhouse')
                OR
                (b.order_type = 'outsource_orders' AND os.order_type = 'outsource')
            )
        ), 0) AS additional_services,

        COALESCE(o.order_code, co.order_code, oo.order_code) AS order_code,

        CASE
            WHEN b.order_type = 'orders' THEN COALESCE(o.paid_amount, 0)
            WHEN b.order_type = 'outsource_orders' THEN COALESCE(oo.paid_amount, 0)
            ELSE 0
        END AS advance_paid,

        CASE
            WHEN b.order_type = 'customer_orders' THEN 'Customer Order'
            ELSE CONCAT(c.first_name, ' ', c.last_name)
        END AS customer_name,

        c.phone,
        c.email

        FROM bills b

        LEFT JOIN orders o
            ON b.order_type = 'orders'
            AND o.id = b.order_id

        LEFT JOIN customer_orders co
            ON b.order_type = 'customer_orders'
            AND co.id = b.order_id

        LEFT JOIN outsource_orders oo
            ON b.order_type = 'outsource_orders'
            AND oo.id = b.order_id

        LEFT JOIN customers c
            ON c.id = COALESCE(o.customer_id, oo.customer_id)

        WHERE b.id = ?
            AND b.is_deleted = 0

");

    $stmt->execute([$id]);

    $bill = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bill) {

        echo json_encode([
            'success' => false
        ]);

        exit;
    }

    echo json_encode([
        'success' => true,
        'bill' => $bill
    ]);

    exit;
}
$orderError = '';

$dueDateError = '';

$hasError = false;
if (isset($_GET['delete_id'])) {

    $deleteId = (int) $_GET['delete_id'];

    $deleteStmt = $pdo->prepare("

        UPDATE bills

        SET is_deleted = 1

        WHERE id = ?
        AND is_deleted = 0

    ");

    $deleteStmt->execute([$deleteId]);

    header("Location: billing.php?deleted=1");

    exit;
}
if (

    $_SERVER['REQUEST_METHOD'] === 'POST'

    &&

    (

        isset($_POST['create_bill'])

        ||

        isset($_POST['update_bill'])

    )

) {

    $orderId =
        isset($_POST['order_id'])
        ? (int) $_POST['order_id']
        : 0;
    $orderType = $_POST['order_type'] ?? 'orders';

    $basePrice =
        isset($_POST['base_price'])
        ? (float) $_POST['base_price']
        : 0;

    $extraCharges =
        isset($_POST['extra_charges'])
        ? (float) $_POST['extra_charges']
        : 0;

    $advance =
        isset($_POST['advance'])
        ? (float) $_POST['advance']
        : 0;

    $dueDate =
        trim($_POST['due_date'] ?? '');
    $billId =
        (int) ($_POST['bill_id'] ?? 0);
    if ($orderId <= 0) {

        $orderError = 'Please select order';

        $hasError = true;
    }

    if (empty($dueDate)) {

        $dueDateError = 'Due date is required';

        $hasError = true;
    }

    $gstPercent =
        (float) ($_POST['gst_percent'] ?? 18);

    $additionalServices = (float) ($_POST['additional_services'] ?? 0);
    $subTotal = $basePrice + $extraCharges + $additionalServices;

    $gstAmount = ($subTotal * $gstPercent) / 100;

    $totalAmount = $subTotal + $gstAmount;

    $paidAmount = 0;

    $pendingAmount = $totalAmount - $paidAmount;

    $status = 'pending';

    if ($paidAmount >= $totalAmount) {

        $status = 'paid';

    } elseif ($paidAmount > 0) {

        $status = 'partially_paid';

    }
    $invoiceNo =
        'INV-' .
        date('Ymd') .
        '-' .
        rand(1000, 9999);
    if (!$hasError) {

        if (isset($_POST['update_bill'])) {
            $paidStmt = $pdo->prepare("SELECT paid_amount FROM bills WHERE id = ?");
            $paidStmt->execute([$billId]);
            $existingBill = $paidStmt->fetch(PDO::FETCH_ASSOC);

            $paidAmount = (float) ($existingBill['paid_amount'] ?? 0);
            $pendingAmount = $totalAmount - $paidAmount;
            if ($paidAmount >= $totalAmount) {
                $status = 'paid';
            } elseif ($paidAmount > 0) {
                $status = 'partially_paid';
            } else {
                $status = 'pending';
            }

            $updateStmt = $pdo->prepare("

                UPDATE bills SET

                    order_id = ?,
                    order_type = ?,
                    base_price = ?,
                    extra_charges = ?,
                    gst_percent = ?,
                    gst_amount = ?,
                    total_amount = ?,
                    paid_amount = ?,
                    pending_amount = ?,
                    bill_status = ?,
                    due_date = ?

                WHERE id = ?

            ");

            $updateStmt->execute([

                $orderId,
                $orderType,
                $basePrice,
                $extraCharges,
                $gstPercent,
                $gstAmount,
                $totalAmount,
                $paidAmount,
                $pendingAmount,
                $status,
                $dueDate,
                $billId

            ]);
            if ($orderType === 'orders') {

                $orderUpdateStmt = $pdo->prepare("
        UPDATE orders
        SET
            base_price = ?,
            extra_charges = ?,
            total_amount = ?,
            advance_amount = ?,
            payment_status = 'pending',
            payment_link = NULL,
            razorpay_payment_link_id = NULL
        WHERE id = ?
    ");

                $orderUpdateStmt->execute([
                    $basePrice,
                    $extraCharges,
                    $totalAmount,
                    $advance,
                    $orderId
                ]);

            } elseif ($orderType === 'customer_orders') {

                $orderUpdateStmt = $pdo->prepare("
                    UPDATE customer_orders
                    SET
                        base_price = ?,
                        extra_charges = ?,
                        total_amount = ?
                    WHERE id = ?
                ");

                $orderUpdateStmt->execute([
                    $basePrice,
                    $extraCharges,
                    $totalAmount,
                    $orderId
                ]);

            } elseif ($orderType === 'outsource_orders') {

                $orderUpdateStmt = $pdo->prepare("
                    UPDATE outsource_orders
                    SET
                        base_price = ?,
                        extra_charges = ?,
                        total_amount = ?,
                        advance_amount = ?
                    WHERE id = ?
                ");

                $orderUpdateStmt->execute([
                    $basePrice,
                    $extraCharges,
                    $totalAmount,
                    $advance,
                    $orderId
                ]);
            }
            header("Location: billing.php?updated=1");

            exit;

        } else {

            $invoiceNo =
                'INV-' .
                date('Ymd') .
                '-' .
                rand(1000, 9999);

            $insertStmt = $pdo->prepare("

            INSERT INTO bills (

                order_id,
                order_type,
                invoice_no,
                base_price,
                extra_charges,
                gst_percent,
                gst_amount,
                total_amount,
                paid_amount,
                pending_amount,
                bill_status,
                due_date

            ) VALUES (

                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?

            )

        ");

            $insertStmt->execute([

                $orderId,
                $orderType,
                $invoiceNo,
                $basePrice,
                $extraCharges,
                $gstPercent,
                $gstAmount,
                $totalAmount,
                $paidAmount,
                $pendingAmount,
                $status,
                $dueDate

            ]);
            if ($orderType === 'orders') {

                $orderUpdateStmt = $pdo->prepare("
                    UPDATE orders
                SET
                    base_price = ?,
                    extra_charges = ?,
                    total_amount = ?,
                    advance_amount = ?
                WHERE id = ?
                ");

                $orderUpdateStmt->execute([
                    $basePrice,
                    $extraCharges,
                    $totalAmount,
                    $advance,
                    $orderId
                ]);

            } elseif ($orderType === 'customer_orders') {

                $orderUpdateStmt = $pdo->prepare("
        UPDATE customer_orders
        SET
            base_price = ?,
            extra_charges = ?,
            total_amount = ?
        WHERE id = ?
    ");

                $orderUpdateStmt->execute([
                    $basePrice,
                    $extraCharges,
                    $totalAmount,
                    $orderId
                ]);

            } elseif ($orderType === 'outsource_orders') {

                $orderUpdateStmt = $pdo->prepare("
                    UPDATE outsource_orders
                        SET
                            base_price = ?,
                            extra_charges = ?,
                            total_amount = ?,
                            advance_amount = ?
                        WHERE id = ?
                ");

                $orderUpdateStmt->execute([
                    $basePrice,
                    $extraCharges,
                    $totalAmount,
                    $advance,
                    $orderId
                ]);
            }

            header("Location: billing.php?success=1");

            exit;

        }
    }
}
include 'includes/header.php';
$stmt = $pdo->prepare("

    SELECT

        b.id AS bill_id,
        b.order_id,
        b.order_type,
        b.invoice_no,
        b.base_price,
        b.extra_charges,
        b.gst_percent,
        b.gst_amount,
        b.total_amount,
        b.paid_amount,
        b.pending_amount,
        b.bill_status,
        b.due_date,
        b.notes,
        COALESCE((
            SELECT SUM(os.service_price)
                FROM order_services os
                WHERE os.order_id = b.order_id
                AND (
                    (b.order_type = 'orders' AND os.order_type = 'inhouse')
                    OR
                    (b.order_type = 'outsource_orders' AND os.order_type = 'outsource')
                )
        ), 0) AS additional_services,
        COALESCE(o.order_code, co.order_code, oo.order_code) AS order_code,

        CASE
            WHEN b.order_type = 'orders' THEN COALESCE(o.paid_amount, 0)
            WHEN b.order_type = 'outsource_orders' THEN COALESCE(oo.paid_amount, 0)
            ELSE 0
        END AS advance_paid,

        CASE
            WHEN b.order_type = 'customer_orders' THEN 'Customer Order'
            ELSE CONCAT(c.first_name, ' ', c.last_name)
        END AS customer_name

    FROM bills b

    LEFT JOIN orders o
        ON b.order_type = 'orders'
        AND o.id = b.order_id

    LEFT JOIN customer_orders co
        ON b.order_type = 'customer_orders'
        AND co.id = b.order_id

    LEFT JOIN outsource_orders oo
        ON b.order_type = 'outsource_orders'
        AND oo.id = b.order_id

    LEFT JOIN customers c
        ON c.id = COALESCE(o.customer_id, oo.customer_id)
    WHERE b.is_deleted = 0
        ORDER BY b.id DESC

");

$currentEditOrderId =
    isset($_GET['edit_order'])
    ? (int) $_GET['edit_order']
    : 0;

$stmt->execute();

$billings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$orderStmt = $pdo->prepare("
    SELECT * FROM (

        SELECT
            o.id,
            o.order_code,
            o.base_price,
            o.extra_charges,
            o.advance_amount,
            o.total_amount,
            COALESCE((
                SELECT SUM(os.service_price)
                FROM order_services os
                WHERE os.order_id = o.id
                AND os.order_type = 'inhouse'
            ), 0) AS additional_services,
            CONCAT(c.first_name,' ',c.last_name) AS customer_name,
            'orders' AS order_type
        FROM orders o
        LEFT JOIN customers c ON c.id = o.customer_id
        WHERE o.is_deleted = 0

        UNION ALL

        SELECT
            co.id,
            co.order_code,
            co.base_price,
            co.extra_charges,
            0 AS advance_amount,
            co.total_amount,
            0 AS additional_services,
            'Customer Order' AS customer_name,
            'customer_orders' AS order_type
        FROM customer_orders co
        WHERE co.is_deleted = 0

        UNION ALL

        SELECT
            oo.id,
            oo.order_code,
            oo.base_price,
            oo.extra_charges,
            oo.advance_amount,
            oo.total_amount,
            COALESCE((
                SELECT SUM(os.service_price)
                FROM order_services os
                WHERE os.order_id = oo.id
                AND os.order_type = 'outsource'
            ), 0) AS additional_services,
            'Outsource Order' AS customer_name,
            'outsource_orders' AS order_type
        FROM outsource_orders oo
        WHERE oo.is_deleted = 0

    ) x
    ORDER BY id DESC
");
$orderStmt->execute();

$orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="padding: 1rem;">
        <div class="page-header"
            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Billing & Invoices</h2>
                <p class="text-muted">Manage client bills and payments</p>
            </div>
            <div style="display: flex; gap: 1rem;">
                <button class="btn" style="background: white; border: 1px solid #e2e8f0; color: #64748b;">
                    <i class="ri-download-line"></i> Export PDF
                </button>
                <button class="btn btn-primary" onclick="openCreateBillModal()">
                    <i class="ri-add-line"></i>
                    Create New Bill
                </button>
            </div>
        </div>

        <div class="table-box" style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem;">
            <div style="display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center;">
                <div class="search-bar" style="width: 300px; position: relative;">
                    <i class="ri-search-line"
                        style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                    <input type="text" class="form-control" style="padding-left: 2.5rem;"
                        placeholder="Search by Order No or Client Name...">
                </div>
                <select class="form-select" style="width: 170px;">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="partially_paid"> Partially Paid</option>
                    <option value="paid">Received</option>
                    <option value="failed"> Failed</option>
                </select>
            </div>

            <table id="billingTable" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Order No</th>
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Client Name
                        </th>
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Due Date</th>
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Advance Paid
                        </th>
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Pending</th>
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Total Payable
                            (incl. GST)</th>
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Status</th>
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Actions</th>
                    </tr>
                </thead>
                <tbody>

                    <?php if (!empty($billings)): ?>

                        <?php foreach ($billings as $billing): ?>

                            <?php
                            $total = (float) $billing['total_amount'];

                            $paid = (float) ($billing['paid_amount'] ?? 0);

                            $pending = $total - $paid;

                            $status = 'pending';

                            if ($paid <= 0) {

                                $status = 'pending';

                            } elseif ($paid < $total) {

                                $status = 'partially_paid';

                            } else {

                                $status = 'paid';

                            }

                            $badgeClass = 'badge-secondary';

                            if ($status == 'paid') {

                                $badgeClass = 'badge-success';

                            } elseif ($status == 'partially_paid') {

                                $badgeClass = 'badge-warning';

                            } elseif ($status == 'pending') {

                                $badgeClass = 'badge-info';

                            }


                            ?>

                            <tr style="border-bottom: 1px solid #f1f5f9;">

                                <td style="padding: 1rem; font-weight: 600; color: #4f46e5;">
                                    #<?= htmlspecialchars($billing['order_code']) ?>
                                </td>

                                <td style="padding: 1rem; font-size: 0.9rem; color: #1e293b;">
                                    <?= htmlspecialchars($billing['customer_name'] ?? 'N/A') ?>
                                </td>

                                <td style="padding: 1rem; font-size: 0.9rem; color: #64748b;">
                                    <?= !empty($billing['due_date'])
                                        ? date('d M Y', strtotime($billing['due_date']))
                                        : 'N/A'
                                        ?>
                                </td>

                                <td style="padding: 1rem; font-size: 0.9rem; color: #16a34a;">
                                    ₹ <?= number_format((float) ($billing['advance_paid'] ?? 0), 2) ?>
                                </td>

                                <td style="padding: 1rem; font-size: 0.9rem; color: #dc2626; font-weight: 600;">
                                    ₹ <?= number_format($pending, 2) ?>
                                </td>

                                <td style="padding: 1rem; font-size: 1rem; font-weight: 700; color: #1e293b;">
                                    ₹ <?= number_format($total, 2) ?>
                                </td>

                                <td style="padding: 1rem;">
                                    <span class="badge <?= $badgeClass ?>">
                                        <?php

                                        $displayStatus = match ($status) {

                                            'pending' => 'Pending',

                                            'partially_paid' => 'Partially Paid',

                                            'paid' => 'Received',

                                            'failed' => 'Failed',

                                            default => 'Unknown'
                                        };

                                        ?>

                                        <?= $displayStatus ?>
                                    </span>
                                </td>

                                <td style="padding: 1rem;">

                                    <div style="display: flex; gap: 0.5rem;">

                                        <button class="btn-icon" title="View Bill"
                                            onclick="openViewBillModal(<?= $billing['bill_id'] ?>)">
                                            <i class="ri-eye-line"></i>
                                        </button>

                                        <button class="btn-icon" title="Download PDF"
                                            onclick="printBill(<?= $billing['bill_id'] ?>)">
                                            <i class="ri-file-pdf-line"></i>
                                        </button>
                                        <!-- EDIT BILL -->

                                        <button type="button" class="btn-icon editBillBtn" data-id="<?= $billing['bill_id'] ?>"
                                            data-order="<?= $billing['order_id'] ?>" data-type="<?= $billing['order_type'] ?>"
                                            data-orderno="<?= $billing['order_code'] ?>"
                                            data-base="<?= $billing['base_price'] ?>"
                                            data-extra="<?= $billing['extra_charges'] ?>"
                                            data-gst="<?= $billing['gst_percent'] ?>"
                                            data-advance="<?= $billing['advance_paid'] ?>"
                                            data-services="<?= $billing['additional_services'] ?? 0 ?>"
                                            data-due="<?= $billing['due_date'] ?>">

                                            <i class="ri-pencil-line"></i>

                                        </button>

                                        <!-- DELETE BILL -->

                                        <button type="button" class="btn-icon deleteBillBtn" title="Delete Bill"
                                            data-id="<?= $billing['bill_id'] ?>" style="display:none;">

                                            <i class="ri-delete-bin-line"></i>

                                        </button>

                                        <?php if ($pending > 0): ?>

                                            <button class="btn-icon" title="Receive Payment">
                                                <i class="ri-money-rupee-circle-line"></i>
                                            </button>

                                        <?php endif; ?>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="8" style="padding: 2rem; text-align: center; color: #64748b;">
                                No billing records found.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>
            </table>
        </div>
    </div>
</main>

<style>
    .badge {
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .badge-warning {
        background: #fef3c7;
        color: #d97706;
    }

    .badge-success {
        background: #dcfce7;
        color: #16a34a;
    }

    .badge-danger {
        background: #fee2e2;
        color: #dc2626;
    }

    .badge-secondary {
        background: #f1f5f9;
        color: #64748b;
    }

    .btn-icon {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 6px;
        border-radius: 6px;
        cursor: pointer;
        color: #64748b;
        transition: all 0.2s;
    }

    .btn-icon:hover {
        background: #eef2ff;
        color: #4f46e5;
        border-color: #c7d2fe;
        transform: translateY(-1px);
    }
</style>
<?php include __DIR__ . '/includes/datatable.php'; ?>

<script>

    document.addEventListener('DOMContentLoaded', function () {

        const billingRows =
            document.querySelectorAll('#billingTable tbody tr');

        const hasRealData =
            billingRows.length > 0 &&
            !billingRows[0].innerText.includes('No billing records found');

        if (hasRealData) {

            initializeDataTable(
                'billingTable',
                'Billing & Invoices'
            );

        }

    });

</script>
<script>

    function openCreateBillModal() {
        document.getElementById('order_id').style.display =
            'block';

        document.getElementById('order_code_display').style.display =
            'none';
        document
            .querySelector('[name="order_id"]')
            .disabled = false;
        document.getElementById('base_price').readOnly = true;
        document.getElementById('extra_charges').readOnly = true;
        document.getElementById('advance').readOnly = true;

        document
            .getElementById('createBillModal')
            .style.display = 'flex';

        document
            .getElementById('billModalTitle')
            .innerText = 'Create Bill';

        document
            .getElementById('billSubmitBtn')
            .innerText = 'Create Bill';

        document
            .getElementById('billSubmitBtn')
            .name = 'create_bill';

        document
            .getElementById('bill_id')
            .value = '';

        document.getElementById('billForm').reset();
        document.getElementById('additional_services').value = 0;
        document.getElementById('additional_services_display').value = 0;
        document.getElementById('order_type').value = 'orders';
        document.getElementById('total_amount').value = '';

    }

    function closeCreateBillModal() {

        document
            .getElementById('createBillModal')
            .style.display = 'none';
    }
    window.onclick = function (event) {

        const modal = document.getElementById('createBillModal');

        if (event.target === modal) {

            modal.style.display = 'none';
        }
    }

</script>

<div id="createBillModal" style="
        display:none;
        position:fixed;
        inset:0;
        background:rgba(15,23,42,0.55);
        z-index:9999;
        justify-content:center;
        align-items:center;
    ">
    <div style="
            width:100%;
            max-width:420px;
            background:white;
            border-radius:16px;
            overflow:hidden;
            box-shadow:0 20px 50px rgba(0,0,0,0.15);
        ">

        <div style="
                background:#4f46e5;
                color:white;
                padding:1.2rem 1.5rem;
                display:flex;
                justify-content:space-between;
                align-items:center;
            ">
            <h3 id="billModalTitle" style="margin:0; font-size:1.1rem; font-weight:600;">
                Create Bill
            </h3>

            <button type="button" onclick="closeCreateBillModal()" style="
                    background:none;
                    border:none;
                    color:white;
                    font-size:1.5rem;
                    cursor:pointer;
                ">
                ×
            </button>
        </div>

        <form method="POST" id="billForm" novalidate style="padding:1.5rem;">

            <div style="margin-bottom:1rem;">
                <input type="hidden" name="bill_id" id="bill_id">
                <input type="hidden" name="order_type" id="order_type">
                <input type="hidden" name="additional_services" id="additional_services" value="0">

                <label style="
                        display:block;
                        margin-bottom:0.5rem;
                        font-weight:600;
                        color:#1e293b;
                    ">
                    Select Order <span style="color:red;">*</span>
                </label>

                <select name="order_id" id="order_id" class="form-select">

                    <option value="">
                        Select Order
                    </option>

                    <?php if (!empty($orders)): ?>

                        <?php foreach ($orders as $order): ?>

                            <?php

                            $isAlreadyBilled = false;

                            foreach ($billings as $b) {

                                if (
                                    $b['order_id'] == $order['id'] &&
                                    $b['order_type'] == $order['order_type']
                                ) {

                                    $isAlreadyBilled = true;
                                    break;

                                }

                            }

                            ?>

                            <option value="<?= $order['id'] ?>" data-order-id="<?= $order['id'] ?>"
                                data-type="<?= $order['order_type'] ?>" data-base="<?= $order['base_price'] ?>"
                                data-extra="<?= $order['extra_charges'] ?>" data-services="<?= $order['additional_services'] ?>"
                                data-advance="<?= $order['advance_amount'] ?>" <?= $isAlreadyBilled ? 'disabled' : '' ?>>

                                <?= htmlspecialchars($order['order_code']) ?>
                                -
                                <?= htmlspecialchars($order['customer_name']) ?>

                            </option>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <option value="">
                            No Orders Available
                        </option>

                    <?php endif; ?>

                </select>
                <input type="text" id="order_code_display" class="form-select" readonly
                    style="display:none; margin-top:10px;">
                <?php if (!empty($orderError)): ?>

                    <small style="color:red;">

                        <?= $orderError ?>

                    </small>

                <?php endif; ?>

            </div>

            <div style="margin-bottom:1rem;">

                <label style="
                        display:block;
                        margin-bottom:0.5rem;
                        font-weight:600;
                        color:#1e293b;
                    ">
                    Base Price <span style="color:red;">*</span>
                </label>

                <input type="number" name="base_price" id="base_price" class="form-control" readonly>

            </div>

            <div style="margin-bottom:1rem;">

                <label style="
                        display:block;
                        margin-bottom:0.5rem;
                        font-weight:600;
                        color:#1e293b;
                    ">
                    Extra Charges
                </label>

                <input type="number" name="extra_charges" id="extra_charges" class="form-control" readonly>

            </div>
            <div style="margin-bottom:1rem;">

                <label style="
                    display:block;
                    margin-bottom:0.5rem;
                    font-weight:600;
                    color:#1e293b;
                ">
                    Additional Services
                </label>

                <input type="number" id="additional_services_display" class="form-control" readonly value="0">

            </div>
            <div style="margin-bottom:1rem;">

                <label style="
                        display:block;
                        margin-bottom:0.5rem;
                        font-weight:600;
                    ">
                    GST %
                </label>

                <input type="number" step="0.01" min="0" name="gst_percent" id="gst_percent" class="form-control"
                    value="18">

            </div>

            <div style="
                    display:grid;
                    grid-template-columns:1fr 1fr;
                    gap:1rem;
                    margin-bottom:1rem;
                ">

                <div>

                    <label style="
                            display:block;
                            margin-bottom:0.5rem;
                            font-weight:600;
                            color:#1e293b;
                        ">
                        Advance
                    </label>

                    <input type="number" name="advance" id="advance" class="form-control" readonly>

                </div>
                <div style="margin-top:1rem;">

                    <label style="
                        display:block;
                        margin-bottom:0.5rem;
                        font-weight:600;
                    ">

                        Total Amount

                    </label>

                    <input type="number" name="total_amount" id="total_amount" class="form-control" readonly>

                </div>

                <div>

                    <label style="
                            display:block;
                            margin-bottom:0.5rem;
                            font-weight:600;
                            color:#1e293b;
                        ">
                        Due Date <span style="color:red;">*</span>
                    </label>

                    <input type="date" name="due_date" class="form-control"
                        value="<?= htmlspecialchars($_POST['due_date'] ?? '') ?>">
                    <?php if (!empty($dueDateError)): ?>

                        <small style="color:red;">

                            <?= $dueDateError ?>

                        </small>

                    <?php endif; ?>

                </div>

            </div>

            <button type="submit" id="billSubmitBtn" name="create_bill" class="btn btn-primary" style="
                    width:100%;
                    margin-top:0.5rem;
                ">
                Save Bill
            </button>

        </form>

    </div>
</div>
<script>
    async function printBill(id) {
        try {

            const response = await fetch(
                `billing.php?action=get_bill_details&id=${id}`
            );

            const data = await response.json();

            if (!data.success) {

                alert('Failed to load bill.');

                return;
            }

            const bill = data.bill;

            const printWindow = window.open('', '', 'width=900,height=700');

            printWindow.document.write(`

            <html>

            <head>

                <title>
                    Invoice
                </title>

                <style>

                    body {

                        font-family: Arial, sans-serif;

                        padding: 40px;

                        color: #1e293b;
                    }

                    .invoice-header {

                        margin-bottom: 30px;
                    }

                    .invoice-title {

                        font-size: 32px;

                        font-weight: bold;

                        margin-bottom: 10px;
                    }

                    .invoice-section {

                        margin-bottom: 20px;
                    }

                    .label {

                        font-size: 13px;

                        color: #64748b;

                        margin-bottom: 4px;
                    }

                    .value {

                        font-size: 16px;

                        font-weight: 600;
                    }

                    table {

                        width: 100%;

                        border-collapse: collapse;

                        margin-top: 30px;
                    }

                    table th,
                    table td {

                        border: 1px solid #e2e8f0;

                        padding: 12px;

                        text-align: left;
                    }

                    table th {

                        background: #f8fafc;
                    }

                    .total {

                        text-align: right;

                        margin-top: 30px;

                        font-size: 22px;

                        font-weight: bold;
                    }

                </style>

            </head>

            <body>

                <div class="invoice-header">

                    <div class="invoice-title">
                        Invoice
                    </div>

                    <div>
                        SOGASU Boutique
                    </div>

                </div>

                <div
                    style="
                        display:grid;
                        grid-template-columns:1fr 1fr;
                        gap:20px;
                    "
                >

                    <div class="invoice-section">

                        <div class="label">
                            Order No
                        </div>

                        <div class="value">
                            ${bill.order_code}
                        </div>

                    </div>

                    <div class="invoice-section">

                        <div class="label">
                            Due Date
                        </div>

                        <div class="value">
                            ${bill.due_date ?? '-'}
                        </div>

                    </div>

                    <div class="invoice-section">

                        <div class="label">
                            Customer
                        </div>

                        <div class="value">
                            ${bill.customer_name}
                        </div>

                    </div>

                    <div class="invoice-section">

                        <div class="label">
                            Phone
                        </div>

                        <div class="value">
                            ${bill.phone ?? '-'}
                        </div>

                    </div>

                </div>

                <table>

                    <thead>

                        <tr>

                            <th>
                                Description
                            </th>

                            <th>
                                Amount
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <tr>

                            <td>
                                Base Price
                            </td>

                            <td>
                                ₹ ${bill.base_price ?? 0}
                            </td>

                        </tr>

                        <tr>

                            <td>
                                Extra Charges
                            </td>

                            <td>
                                ₹ ${bill.extra_charges ?? 0}
                            </td>

                        </tr>
                        <tr>
                            <td>
                                Additional Services
                            </td>
                            <td>
                                ₹ ${bill.additional_services ?? 0}
                            </td>
                        </tr>

                        <tr>

                            <td>
                                Advance Paid
                            </td>

                            <td>
                                ₹ ${bill.advance_paid ?? 0}
                            </td>

                        </tr>

                    </tbody>

                </table>

                <div class="total">

                    Total:
                    ₹ ${bill.total_amount ?? 0}

                </div>

            </body>

            </html>

        `);

            printWindow.document.close();

            printWindow.focus();

            setTimeout(() => {

                printWindow.print();

            }, 500);
        }

        catch (error) {

            alert('Something went wrong.');
        }
    }

</script>
<script>

    async function openViewBillModal(id) {

        const modal = document.getElementById('viewBillModal');

        const container = document.getElementById('billDetailsContainer');

        modal.style.display = 'flex';

        container.innerHTML = `
        <div style="padding:2rem; text-align:center;">
            Loading...
        </div>
    `;

        try {

            const response = await fetch(
                `billing.php?action=get_bill_details&id=${id}`
            );

            const data = await response.json();

            if (!data.success) {

                container.innerHTML = `
                <div style="padding:2rem; text-align:center; color:red;">
                    Failed to load bill details.
                </div>
            `;

                return;
            }

            const bill = data.bill;

            container.innerHTML = `

            <div
                style="
                    display:grid;
                    grid-template-columns:1fr 1fr;
                    gap:1rem;
                "
            >

                <div>

                    <p><strong>Order No:</strong> ${bill.order_code}</p>

                    <p><strong>Customer:</strong> ${bill.customer_name}</p>

                    <p><strong>Phone:</strong> ${bill.phone ?? '-'}</p>

                </div>

                <div>

                    <p><strong>Total:</strong> ₹ ${bill.total_amount}</p>
                    <p><strong>Additional Services:</strong> ₹ ${bill.additional_services ?? 0}</p>
                    <p><strong>Advance Paid:</strong> ₹ ${bill.advance_paid}</p>
                    <p><strong>Status:</strong> ${bill.bill_status}</p>

                </div>

            </div>

        `;
        }

        catch (error) {

            container.innerHTML = `
            <div style="padding:2rem; text-align:center; color:red;">
                Something went wrong.
            </div>
        `;
        }
    }

    function closeViewBillModal() {

        document
            .getElementById('viewBillModal')
            .style.display = 'none';
    }

</script>
<div id="viewBillModal" style="
        display:none;
        position:fixed;
        inset:0;
        background:rgba(15,23,42,0.55);
        z-index:99999;
        justify-content:center;
        align-items:center;
    ">

    <div style="
            width:100%;
            max-width:650px;
            background:white;
            border-radius:16px;
            overflow:hidden;
        ">

        <div style="
                background:#4f46e5;
                color:white;
                padding:1.25rem 1.5rem;
                display:flex;
                justify-content:space-between;
                align-items:center;
            ">

            <h3 style="margin:0;">
                Bill Details
            </h3>

            <button onclick="closeViewBillModal()" style="
                    background:none;
                    border:none;
                    color:white;
                    font-size:1.5rem;
                    cursor:pointer;
                ">
                ×
            </button>

        </div>

        <div id="billDetailsContainer" style="padding:1.5rem;">

        </div>

    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>

    document
        .querySelector('[name="order_id"]')
        .addEventListener('change', function () {
            const selected =
                this.options[this.selectedIndex];

            document
                .getElementById('base_price')
                .value =
                selected.dataset.base || 0;

            document
                .getElementById('extra_charges')
                .value =
                selected.dataset.extra || 0;

            document
                .getElementById('advance')
                .value =
                selected.dataset.advance || 0;
            document.getElementById('order_type').value =
                selected.dataset.type || 'orders';
            const base =
                parseFloat(
                    selected.dataset.base || 0
                );

            const extra =
                parseFloat(
                    selected.dataset.extra || 0
                );

            const advance =
                parseFloat(
                    selected.dataset.advance || 0
                );
            const services = parseFloat(selected.dataset.services || 0);

            document.getElementById('additional_services').value = services;
            document.getElementById('additional_services_display').value = services;

            const subTotal = base + extra + services;

            const gstPercent =
                parseFloat(
                    document.getElementById('gst_percent').value
                ) || 0;

            const gstAmount =
                subTotal * (gstPercent / 100);

            const total =
                subTotal + gstAmount;

            document
                .getElementById('base_price')
                .value = base;

            document
                .getElementById('extra_charges')
                .value = extra;

            document
                .getElementById('advance')
                .value = advance;

            document
                .getElementById('total_amount')
                .value = total;
        });

</script>
<script>

    document
        .querySelectorAll('.editBillBtn')
        .forEach(button => {

            button.addEventListener('click', function () {
                document.getElementById('order_id').value =
                    this.dataset.order;
                document.getElementById('order_type').value =
                    this.dataset.type;

                document.getElementById('order_id').style.display =
                    'none';

                document.getElementById('order_code_display').style.display =
                    'block';

                document.getElementById('order_code_display').value =
                    this.dataset.orderno;
                document.getElementById('base_price').readOnly = false;
                document.getElementById('extra_charges').readOnly = false;
                document.getElementById('advance').readOnly = true;

                document
                    .getElementById('bill_id')
                    .value = this.dataset.id;

                document
                    .querySelector('[name="order_id"]')
                    .value = String(this.dataset.order);
                const currentOption = document.querySelector(
                    '#order_id option[data-order-id="' + this.dataset.order + '"][data-type="' + this.dataset.type + '"]'
                );

                if (currentOption) {

                    currentOption.disabled = false;

                }
                document
                    .querySelector('[name="base_price"]')
                    .value = this.dataset.base;

                document
                    .querySelector('[name="extra_charges"]')
                    .value = this.dataset.extra;
                document
                    .getElementById('gst_percent')
                    .value = this.dataset.gst;
                document.getElementById('additional_services').value =
                    this.dataset.services || 0;

                document.getElementById('additional_services_display').value =
                    this.dataset.services || 0;

                document.querySelector('[name="advance"]').value = this.dataset.advance;

                calculateTotal();

                document
                    .querySelector('[name="due_date"]')
                    .value = this.dataset.due;

                document
                    .getElementById('billModalTitle')
                    .innerText = 'Edit Bill';

                const submitBtn =
                    document.getElementById('billSubmitBtn');

                submitBtn.name = 'update_bill';

                submitBtn.innerText = 'Update Bill';

                document
                    .getElementById('createBillModal')
                    .style.display = 'flex';

            });

        });

</script>
<?php if (isset($_GET['success'])): ?>

<script>

    Swal.fire({

        icon: 'success',

        title: 'Bill Created Successfully'

    });

</script>

<?php endif; ?>

<?php if (isset($_GET['updated'])): ?>

<script>

    Swal.fire({

        icon: 'success',

        title: 'Bill Updated Successfully'

    });

</script>

<?php endif; ?>

<?php if (isset($_GET['deleted'])): ?>

<script>

    Swal.fire({

        icon: 'success',

        title: 'Bill Deleted Successfully'

    });

</script>

<?php endif; ?>
<script>

    document
        .querySelectorAll('.deleteBillBtn')
        .forEach(button => {

            button.addEventListener('click', function () {

                const id = this.dataset.id;

                Swal.fire({

                    title: 'Delete Bill?',

                    text: 'This bill will be hidden',

                    icon: 'warning',

                    showCancelButton: true,

                    confirmButtonText: 'Yes Delete'

                }).then((result) => {

                    if (result.isConfirmed) {

                        window.location =
                            'billing.php?delete_id=' + id;

                    }

                });

            });

        });
    function calculateTotal() {

        let base =
            parseFloat(
                document.getElementById('base_price').value
            ) || 0;

        let extra =
            parseFloat(
                document.getElementById('extra_charges').value
            ) || 0;

        let advance =
            parseFloat(
                document.getElementById('advance').value
            ) || 0;

        let services = parseFloat(
            document.getElementById('additional_services')?.value || 0
        ) || 0;

        let subtotal = base + extra + services;

        let gstPercent =
            parseFloat(
                document.getElementById('gst_percent').value
            ) || 0;

        let gst =
            subtotal * (gstPercent / 100);

        let total = subtotal + gst;

        document.getElementById('total_amount').value =
            total.toFixed(2);
    }

    document
        .getElementById('base_price')
        .addEventListener('input', calculateTotal);

    document
        .getElementById('extra_charges')
        .addEventListener('input', calculateTotal);

    document
        .getElementById('advance')
        .addEventListener('input', calculateTotal);
    document
        .getElementById('gst_percent')
        .addEventListener('input', calculateTotal);
    calculateTotal();

</script>
<?php if ($hasError): ?>

<script>

    document
        .getElementById('createBillModal')
        .style.display = 'flex';

</script>

<?php endif; ?>
<?php include 'includes/footer.php'; ?>