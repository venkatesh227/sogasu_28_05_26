<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$pageTitle = "Procurement - Sogasu";
$activePage = "procurement";

include 'includes/header.php';
/* =========================
   DATABASE CONNECTION
========================= */

$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) {
    die("Connection Failed: " . mysqli_connect_error());
}

/* =========================
   FETCH UNIQUE CATEGORIES FOR FILTER DROPDOWN
========================= */

$categoriesQuery = mysqli_query($conn, "
    SELECT name, code
    FROM inventory_categories
    WHERE is_deleted = 0 AND status = 1
    ORDER BY name ASC
");

/* =========================
   FETCH STOCK INVENTORY FROM INVENTORY TABLE
========================= */

$selectedCategory = isset($_GET['category']) ? mysqli_real_escape_string($conn, $_GET['category']) : '';
$whereClause = "WHERE i.is_deleted = 0";
if ($selectedCategory !== '') {
    $whereClause .= " AND i.category = '$selectedCategory'";
}

$procurementQuery = mysqli_query($conn, "
    SELECT 
        i.id, 
        i.item_name, 
        i.quantity, 
        i.unit, 
        i.category AS category_code,
        c.name AS category_name
    FROM inventory i
    LEFT JOIN inventory_categories c ON c.code = i.category AND c.is_deleted = 0
    $whereClause
    ORDER BY i.id DESC
");

/* =========================
   FETCH ACTIVE ORDERS FOR SELECT DROPDOWN
========================= */

$ordersQuery = mysqli_query($conn, "
    SELECT id, order_code, 'orders' AS order_type
    FROM orders
    WHERE is_deleted = 0
      AND order_status = 'pending'

    UNION ALL

    SELECT id, order_code, 'customer_orders' AS order_type
    FROM customer_orders
    WHERE is_deleted = 0
      AND status = 'pending'

    UNION ALL

    SELECT id, order_code, 'outsource_orders' AS order_type
    FROM outsource_orders
    WHERE is_deleted = 0
      AND order_status = 'pending'

    ORDER BY id DESC
");
$orders = [];
while ($order = mysqli_fetch_assoc($ordersQuery)) {
    $orders[] = $order;
}

/* =========================
   FETCH ISSUE HISTORY (JOINED WITH INVENTORY & ORDERS)
========================= */

$issueQuery = mysqli_query($conn, "
    SELECT
        stock_issuance.*,
        inventory.item_name AS material_name,
        inventory.unit,
        employees.first_name,
        employees.last_name,
        COALESCE(
            o.order_code,
            co.order_code,
            oo.order_code
        ) AS order_code
    FROM stock_issuance

    LEFT JOIN inventory
        ON inventory.id = stock_issuance.procurement_id

    LEFT JOIN employees
        ON employees.id = stock_issuance.employee_id

    LEFT JOIN orders o
        ON o.id = stock_issuance.order_id
        AND (
                stock_issuance.order_type = 'orders'
                OR stock_issuance.order_type IS NULL
        )

    LEFT JOIN customer_orders co
        ON co.id = stock_issuance.order_id
       AND stock_issuance.order_type = 'customer_orders'

    LEFT JOIN outsource_orders oo
        ON oo.id = stock_issuance.order_id
       AND stock_issuance.order_type = 'outsource_orders'

    ORDER BY stock_issuance.id DESC
");
?>

<main class="main-content">

    <?php include 'includes/topbar.php'; ?>

    <div style="padding: 1rem;">

        <!-- PAGE HEADER -->

        <div style="
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-bottom:2rem;
        ">

            <div>
                <h2 style="
                    font-size:1.7rem;
                    font-weight:700;
                    color:#0f172a;
                    margin-bottom:5px;
                ">
                    Procurement (Advance Stock)
                </h2>

                <p style="
                    color:#64748b;
                    margin:0;
                ">
                    Manage raw materials and stock issuance
                </p>
            </div>

            <a href="inventory.php" class="btn btn-primary" style="
                    background:#4338ca;
                    border:none;
                    padding:12px 22px;
                    border-radius:8px;
                    font-weight:600;
                    text-decoration:none;
                    color:white;
                    display:inline-flex;
                    align-items:center;
                    gap:5px;
                ">
                <i class="ri-add-line"></i>
                Add Stock
            </a>

        </div>

        <!-- STOCK INVENTORY -->

        <div style="
            background:#fff;
            border:1px solid #e2e8f0;
            border-radius:12px;
            padding:1.5rem;
            margin-bottom:2rem;
        ">

            <div
                style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; gap: 1rem; flex-wrap: wrap;">
                <h3 style="font-size:1.2rem; font-weight:700; margin:0; color:#0f172a;">Stock Inventory</h3>
                <form method="GET" action="procurement.php"
                    style="display:inline-flex; align-items:center; gap:8px; margin:0;">
                    <label for="categoryFilter" style="font-size:14px; font-weight:600; color:#475569;">Filter
                        Category:</label>
                    <select name="category" id="categoryFilter" onchange="this.form.submit()"
                        style="height:38px; border:1px solid #dbe2ea; border-radius:8px; padding:0 12px; font-size:14px; background:#fff; outline:none; min-width: 160px; cursor: pointer; color: #1e293b; font-weight: 500;">
                        <option value="">All Categories</option>
                        <?php
                        $selectedCategory = isset($_GET['category']) ? $_GET['category'] : '';
                        while ($cat = mysqli_fetch_assoc($categoriesQuery)):
                            ?>
                            <option value="<?= htmlspecialchars($cat['code']) ?>" <?= $selectedCategory === $cat['code'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </form>
            </div>

            <table id="stockInventoryTable" style="width:100%;">

                <thead>

                    <tr>

                        <th>ITEM</th>
                        <th>CATEGORY</th>
                        <th>AVAILABLE QUANTITY</th>
                        <th>UNIT</th>
                        <th style="text-align:right;">ACTION</th>

                    </tr>

                </thead>

                <tbody>

                    <?php while ($row = mysqli_fetch_assoc($procurementQuery)): ?>

                        <tr>

                            <td style="font-weight:600;">
                                <?= htmlspecialchars($row['item_name']) ?>
                            </td>

                            <td>
                                <span
                                    style="background: #f1f5f9; color: #475569; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                                    <?= htmlspecialchars($row['category_name'] ?: ucfirst($row['category_code'] ?: 'N/A')) ?>
                                </span>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['quantity']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['unit']) ?>
                            </td>

                            <td style="text-align:right;">

                                <button class="btn btn-sm" onclick="openIssueModal(
                                            '<?= htmlspecialchars($row['id']) ?>',
                                            '<?= htmlspecialchars($row['item_name']) ?>',
                                            '<?= htmlspecialchars($row['quantity']) ?>',
                                            '<?= htmlspecialchars($row['unit']) ?>'
                                        )" style="
                                            background:#eef2ff;
                                            color:#4338ca;
                                            border:1px solid #c7d2fe;
                                            border-radius:12px;
                                            padding:8px 20px;
                                            font-weight:600;
                                        ">
                                    Issue
                                </button>

                            </td>

                        </tr>
                    <?php endwhile; ?>

                </tbody>

            </table>

        </div>

        <!-- ISSUE HISTORY -->

        <div style="
            background:#fff;
            border:1px solid #e2e8f0;
            border-radius:12px;
            padding:1.5rem;
        ">

            <h3 style="
                font-size:1.2rem;
                font-weight:700;
                margin-bottom:1rem;
                color:#0f172a;
            ">
                Issue History
            </h3>

            <table id="issueHistoryTable" style="width:100%;">

                <thead>

                    <tr>

                        <th>ITEM</th>
                        <th>ISSUED TO</th>
                        <th>QUANTITY</th>
                        <th>ORDER NO</th>
                        <th>DATE</th>

                    </tr>

                </thead>

                <tbody>

                    <?php while ($issue = mysqli_fetch_assoc($issueQuery)): ?>
                        <tr>

                            <td>
                                <?= htmlspecialchars($issue['material_name']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($issue['first_name']) ?>
                                <?= htmlspecialchars($issue['last_name']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($issue['quantity_issued']) ?>
                                <?= htmlspecialchars($issue['unit']) ?>
                            </td>

                            <td>
                                <?php if (!empty($issue['order_code'])): ?>
                                    <?php
                                    $viewPage = 'view-order.php';

                                    if ($issue['order_type'] === 'customer_orders') {
                                        $viewPage = 'view-customer-order.php';
                                    } elseif ($issue['order_type'] === 'outsource_orders') {
                                        $viewPage = 'view-outsource-order.php';
                                    }
                                    ?>
                                    <a href="<?= $viewPage ?>?id=<?= intval($issue['order_id']) ?>"
                                        style="font-weight:700; color:#4f46e5; text-decoration:none;">
                                        <i class="ri-external-link-line"
                                            style="vertical-align:middle; font-size:14px; margin-right:2px;"></i>
                                        #<?= htmlspecialchars($issue['order_code']) ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color:#94a3b8;">N/A</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= date('d M Y', strtotime($issue['issue_date'])) ?>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                </tbody>

            </table>

        </div>

    </div>

</main>

<!-- =========================
     ISSUE MODAL
========================= -->

<div id="issueModal" class="custom-modal">

    <div class="custom-modal-card" style="max-width:450px;">

        <div class="custom-modal-header">

            <h3>Issue Stock</h3>

            <i class="ri-close-line" onclick="closeModal('issueModal')"></i>

        </div>

        <form method="POST" action="save-stock-issue.php" novalidate onsubmit="return validateIssueForm()">
            <div class="custom-modal-body">

                <div style="
                    background:#f8fafc;
                    border:1px solid #e2e8f0;
                    padding:1rem;
                    border-radius:10px;
                    margin-bottom:1.5rem;
                ">

                    <h4 id="issueItemName" style="
                            margin:0;
                            font-size:1rem;
                            color:#0f172a;
                        ">
                        Item Name
                    </h4>

                    <p style="
                        margin-top:5px;
                        color:#64748b;
                    ">
                        Available :
                        <span id="issueAvailableQty">0</span>
                        <span id="issueUnit"></span>
                    </p>

                </div>

                <input type="hidden" name="item_id" id="hiddenItemId">
                <input type="hidden" name="item_name" id="hiddenItemName">
                <input type="hidden" name="unit" id="hiddenUnit">
                <input type="hidden" name="order_type" id="hiddenOrderType">

                <div class="form-group" style="margin-bottom:1.2rem;">

                    <label class="form-label" style="
                        font-size:15px;
                        font-weight:600;
                        color:#1e293b;
                        margin-bottom:10px;
                    ">
                        Department / Employee
                    </label>

                    <select name="issued_to" class="form-select" required style="
                        width:100%;
                        height:48px;
                        border:1px solid #dbe2ea;
                        border-radius:8px;
                        padding:0 14px;
                        font-size:15px;
                        background:#fff;
                    ">

                        <option value="">Select Employee</option>

                        <?php

                        $employeeQuery = mysqli_query($conn, "
                            SELECT *
                            FROM employees
                            WHERE status = 1
                            ORDER BY first_name ASC
                        ");

                        while ($employee = mysqli_fetch_assoc($employeeQuery)):

                            $employeeName = $employee['first_name'] . ' ' . $employee['last_name'];

                            ?>

                            <option value="<?= $employee['id'] ?>">

                                <?= $employee['first_name'] ?>
                                <?= $employee['last_name'] ?>

                            </option>

                        <?php endwhile; ?>

                    </select>
                    <small id="issued_to_error" class="validation-error"></small>
                </div>

                <div class="form-group" style="margin-bottom:1.2rem;">

                    <label class="form-label" style="
                        font-size:15px;
                        font-weight:600;
                        color:#1e293b;
                        margin-bottom:10px;
                    ">
                        Link to Order (Optional)
                    </label>

                    <select name="order_id" class="form-select" style="
                        width:100%;
                        height:48px;
                        border:1px solid #dbe2ea;
                        border-radius:8px;
                        padding:0 14px;
                        font-size:15px;
                        background:#fff;
                    ">

                        <option value="">Select Order No</option>

                        <?php foreach ($orders as $ord): ?>

                            <option value="<?= $ord['id'] ?>" data-type="<?= $ord['order_type'] ?>">
                                #<?= htmlspecialchars($ord['order_code']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="grid-2" style="
                    display:grid;
                    grid-template-columns:1fr 1fr;
                    gap:1rem;
                    margin-bottom:1.5rem;
                ">
                    <div class="form-group">

                        <label class="form-label">
                            Quantity
                        </label>

                        <input type="number" name="quantity" class="form-control" value="1" style="
                            height:48px;
                            border-radius:8px;
                        " required>
                        <small id="issue_quantity_error" class="validation-error"></small>
                    </div>

                    <div class="form-group">

                        <label class="form-label">
                            Issue Date
                        </label>

                        <input type="date" name="issue_date" class="form-control" value="<?= date('Y-m-d') ?>" style="
        height:48px;
        border-radius:8px;
    " required>
                        <small id="issue_date_error" class="validation-error"></small>
                    </div>

                </div>

                <button type="submit" class="btn btn-primary" style="
        width:100%;
        background:#4f46e5;
        border:none;
        padding:14px;
        border-radius:8px;
        font-weight:600;
        font-size:15px;
    ">
                    Issue Stock
                </button>

            </div>

        </form>

    </div>

</div>

<style>
    .custom-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        justify-content: center;
        align-items: center;
    }

    .custom-modal-card {
        background: #fff;
        width: 550px;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    }

    .custom-modal-header {
        background: #4338ca;
        color: #fff;
        padding: 1rem 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .custom-modal-header h3 {
        margin: 0;
        font-size: 1.1rem;
    }

    .custom-modal-header i {
        cursor: pointer;
        font-size: 1.5rem;
    }

    .custom-modal-body {
        padding: 1.5rem;
    }

    .grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    .form-group {
        margin-bottom: 1rem;
    }

    .form-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #334155;
    }

    .form-control,
    .form-select {
        width: 100%;
        height: 45px;
        border: 1px solid #dbe2ea;
        border-radius: 8px;
        padding: 0 12px;
        outline: none;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: #4338ca;
    }

    table.dataTable thead th {
        color: #64748b !important;
        font-size: 13px !important;
    }

    table.dataTable tbody td {
        padding: 16px 10px !important;
        border-bottom: 1px solid #f1f5f9 !important;
    }

    .validation-error {
        color: red !important;
        font-size: 13px;
        margin-top: 5px;
        display: block;
        font-weight: 600;
    }
</style>

<script>

    function closeModal(id) {

        document.getElementById(id).style.display = 'none';

    }

    function openIssueModal(id, name, qty, unit) {

        document.getElementById('hiddenItemId').value = id;

        document.getElementById('issueItemName').innerText = name;

        document.getElementById('issueAvailableQty').innerText = qty;

        document.getElementById('issueUnit').innerText = unit;

        document.getElementById('hiddenItemName').value = name;

        document.getElementById('hiddenUnit').value = unit;

        document.getElementById('issueModal').style.display = 'flex';

    }
    document.addEventListener('change', function (e) {
        if (e.target && e.target.name === 'order_id') {
            const selected = e.target.options[e.target.selectedIndex];
            document.getElementById('hiddenOrderType').value =
                selected.getAttribute('data-type') || '';

            console.log('order_type =', document.getElementById('hiddenOrderType').value);
        }
    });

</script>

<?php include __DIR__ . '/includes/datatable.php'; ?>

<script>

    initializeDataTable(
        'stockInventoryTable',
        'Stock Inventory'
    );

    initializeDataTable(
        'issueHistoryTable',
        'Issue History'
    );

</script>

<script>

    function validateIssueForm() {

        let isValid = true;

        document.getElementById('issued_to_error').innerHTML = '';

        document.getElementById('issue_quantity_error').innerHTML = '';

        document.getElementById('issue_date_error').innerHTML = '';

        let issued_to =
            document.getElementsByName('issued_to')[0].value.trim();

        let quantity =
            document.getElementsByName('quantity')[0].value.trim();

        let issue_date =
            document.getElementsByName('issue_date')[0].value.trim();

        let availableQty = parseFloat(document.getElementById('issueAvailableQty').innerText) || 0;

        if (issued_to == '') {

            document.getElementById('issued_to_error')
                .innerHTML = 'Employee field is required';

            isValid = false;
        }

        if (quantity == '') {

            document.getElementById('issue_quantity_error')
                .innerHTML = 'Quantity field is required';

            isValid = false;
        } else {
            let qtyVal = parseFloat(quantity);
            if (isNaN(qtyVal) || qtyVal <= 0) {
                document.getElementById('issue_quantity_error')
                    .innerHTML = 'Quantity must be a positive number';
                isValid = false;
            } else if (qtyVal > availableQty) {
                document.getElementById('issue_quantity_error')
                    .innerHTML = 'Quantity cannot exceed available stock (' + availableQty + ')';
                isValid = false;
            }
        }

        if (issue_date == '') {

            document.getElementById('issue_date_error')
                .innerHTML = 'Issue Date field is required';

            isValid = false;
        }

        return isValid;
    }
</script>
<?php if (isset($_GET['success']) && $_GET['success'] == 'stock_added'): ?>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>

        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: 'Stock Saved Successfully!',
            confirmButtonColor: '#4f46e5'
        });

        window.history.replaceState({}, document.title, window.location.pathname);

    </script>

<?php endif; ?>

<?php if (isset($_GET['success']) && $_GET['success'] == 'stock_issued'): ?>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: 'Stock Issued Successfully!',
            confirmButtonColor: '#4f46e5'
        });

        window.history.replaceState({}, document.title, window.location.pathname);
    </script>

<?php endif; ?>
<?php include 'includes/footer.php'; ?>