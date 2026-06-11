<?php
$pageTitle = "Expenses - Sogasu";
$activePage = "expenses";
require_once '../includes/db.php';

$currentDateTime = date('Y-m-d H:i:s');

$successMessage = '';
$errorMessage = '';
$category_name_error = '';
$status_error = '';
$expense_category_error = '';
$amount_error = '';
$expense_date_error = '';

/* =====================================================
   CATEGORY SAVE / UPDATE
===================================================== */

if (isset($_POST['save_category'])) {

    $id = $_POST['id'] ?? '';

    $category_name = trim($_POST['category_name']);
    $status = trim($_POST['status']);

    /* VALIDATIONS */

    if (empty($category_name)) {

        $category_name_error = "Category name is required";

    } elseif (empty($status)) {

        $status_error = "Status is required";

    } else {

        // DUPLICATE CHECK

        $check = $pdo->prepare("
            SELECT id
            FROM expense_categories
            WHERE LOWER(category_name) = LOWER(?)
            AND is_deleted = 0
            AND id != ?
        ");

        $check->execute([
            $category_name,
            $id ?: 0
        ]);

        if ($check->rowCount() > 0) {

            $errorMessage = "Category already exists";

        } else {

            /* UPDATE */

            if (!empty($id)) {

                $stmt = $pdo->prepare("
                    UPDATE expense_categories
                    SET
                        category_name = ?,
                        status = ?,
                        updated_at = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $category_name,
                    $status,
                    $currentDateTime,
                    $id
                ]);

                $successMessage = "Category updated successfully";

            } else {

                /* INSERT */

                $stmt = $pdo->prepare("
                    INSERT INTO expense_categories
                    (
                        category_name,
                        status,
                        created_at
                    )
                    VALUES (?, ?, ?)
                ");

                $stmt->execute([
                    $category_name,
                    $status,
                    $currentDateTime
                ]);

                $successMessage = "Category added successfully";
            }
        }
    }
}
/* =====================================================
   CATEGORY DELETE
===================================================== */

if (
    isset($_POST['delete_category'])
    && !empty($_POST['delete_category_id'])
) {

    $stmt = $pdo->prepare("
        UPDATE expense_categories
        SET is_deleted = 1
        WHERE id = ?
    ");

    $stmt->execute([
        $_POST['delete_category_id']
    ]);

    $successMessage = "Category deleted successfully";
}
/* =====================================================
   EXPENSE DELETE
===================================================== */

if (
    isset($_POST['delete_expense'])
    && !empty($_POST['delete_expense_id'])
) {

    $stmt = $pdo->prepare("
        DELETE FROM expenses
        WHERE id = ?
    ");

    $stmt->execute([
        $_POST['delete_expense_id']
    ]);

    $successMessage = "Expense deleted successfully";
}

/* =====================================================
   EXPENSE SAVE / UPDATE
===================================================== */

if (isset($_POST['save_expense'])) {

    $expense_id = $_POST['expense_id'] ?? '';

    $expense_category = trim($_POST['expense_category']);
    $description = trim($_POST['description']);
    $amount = trim($_POST['amount']);
    $payment_method = trim($_POST['payment_method']);
    $status = trim($_POST['expense_status']);
    $expense_date = trim($_POST['expense_date']);

    /* VALIDATIONS */

    $hasExpenseError = false;

    if ($expense_category === '') {

        $expense_category_error = "Please select category";
        $hasExpenseError = true;
    }

    if ($amount === '') {

        $amount_error = "Amount is required";
        $hasExpenseError = true;

    } elseif (!is_numeric($amount)) {

        $amount_error = "Enter valid amount";
        $hasExpenseError = true;

    } elseif ($amount <= 0) {

        $amount_error = "Amount should be greater than 0";
        $hasExpenseError = true;
    }

    if ($expense_date === '') {

        $expense_date_error = "Date is required";
        $hasExpenseError = true;

    } elseif ($expense_date > date('Y-m-d')) {

        $expense_date_error = "Future dates are not allowed";
        $hasExpenseError = true;
    }

    if (!$hasExpenseError) {

        /* UPDATE */

        if (!empty($expense_id)) {

            $stmt = $pdo->prepare("
                UPDATE expenses
                SET
                    expense_category = ?,
                    description = ?,
                    amount = ?,
                    payment_method = ?,
                    status = ?,
                    expense_date = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $expense_category,
                $description,
                $amount,
                $payment_method,
                $status,
                $expense_date,
                $expense_id
            ]);

            $successMessage = "Expense updated successfully";

        } else {

            /* INSERT */

            $stmt = $pdo->prepare("
                INSERT INTO expenses
                (
                    expense_category,
                    description,
                    amount,
                    payment_method,
                    status,
                    expense_date
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $expense_category,
                $description,
                $amount,
                $payment_method,
                $status,
                $expense_date
            ]);

            $successMessage = "Expense added successfully";
        }
    }
}

/* =====================================================
   FETCH ACTIVE CATEGORIES
===================================================== */

$categoryStmt = $pdo->query("
    SELECT *
    FROM expense_categories
    WHERE status = 'Active'
    AND is_deleted = 0
    ORDER BY id DESC
");

$categories = $categoryStmt->fetchAll();

/* =====================================================
   FETCH ALL CATEGORIES
===================================================== */

$allCategoriesStmt = $pdo->query("
    SELECT *
    FROM expense_categories
    WHERE is_deleted = 0
    ORDER BY id DESC
");

$allCategories = $allCategoriesStmt->fetchAll();

/* =====================================================
   FETCH EXPENSES
===================================================== */

$expenseStmt = $pdo->query("
    SELECT *
    FROM expenses
    ORDER BY id DESC
");

$expenses = $expenseStmt->fetchAll();

/* =====================================================
   MONTHLY SUMMARY
===================================================== */

$month = date('m');
$year = date('Y');

$summaryStmt = $pdo->prepare("
    SELECT
        SUM(amount) as total,
        SUM(CASE WHEN status='Paid' THEN amount ELSE 0 END) as paid,
        SUM(CASE WHEN status='Pending' THEN amount ELSE 0 END) as pending
    FROM expenses
    WHERE MONTH(expense_date)=?
    AND YEAR(expense_date)=?
");

$summaryStmt->execute([$month, $year]);

$summary = $summaryStmt->fetch();

include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div class="dashboard-container animate-fade-in" style="padding: 1.5rem;">

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <h1
                    style="font-size: 1.75rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.5rem; letter-spacing: -0.02em;">
                    Expenses</h1>
                <p style="color: var(--text-muted); font-size: 1rem; font-weight: 500;">Track and manage all business
                    expenditures.</p>
            </div>

            <div style="display: flex; gap: 0.75rem;">

                <button type="button" onclick="openCategoryModal()" class="btn-premium" style="
                    background:#fff !important;
                    color:#111827 !important;
                    border:1px solid #e2e8f0;
                    padding:10px 18px;
                    border-radius:10px;
                    box-shadow:none;
                    ">

                    <i class="ri-add-line"></i>

                    Add Category

                </button>

                <button type="button" onclick="openExpenseModal()" class="btn-premium">

                    <i class="ri-add-line"></i>

                    Record New Expense

                </button>

            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1.5rem;">
            <!-- Expense Categories -->
            <div class="table-container" style="padding: 0; overflow: hidden; border-radius: 16px;">
                <div
                    style="padding: 1rem 1.25rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-dark); margin: 0;">Expense
                        Categories</h3>
                </div>
                <div style="padding: 1rem 1.25rem;">
                    <table class="premium-table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Category Name</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        <tbody>

                            <?php foreach ($allCategories as $cat): ?>

                                <tr>

                                    <td style="font-weight: 700; color: var(--text-dark);">
                                        <?= htmlspecialchars($cat['category_name']) ?>
                                    </td>

                                    <td style="text-align: right;">

                                        <div style="display:flex; gap:8px; justify-content:flex-end;">

                                            <button type="button" class="btn-icon-p"
                                                onclick='editCategory(<?= json_encode($cat) ?>)'>

                                                <i class="ri-pencil-line"></i>

                                            </button>

                                            <button type="button" class="btn-icon-p"
                                                onclick="deleteCategory(<?= $cat['id'] ?>)" style="color:#dc2626;">

                                                <i class="ri-delete-bin-line"></i>

                                            </button>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>
                </div>
            </div>

            <!-- Monthly Summary -->
            <div class="table-container"
                style="padding: 1.5rem; border-radius: 16px; display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-dark); margin-bottom: 1.5rem;">
                        Monthly Summary (Feb)</h3>

                    <div class="premium-stat-card card-expense"
                        style="padding: 1.5rem; margin-bottom: 1.5rem; background: #fef2f2;">
                        <div class="stat-header">
                            <div class="stat-icon" style="background: white;"><i class="ri-hand-coin-line"></i></div>
                            <div class="stat-trend up" style="background: #e11d48; color: white;"><i
                                    class="ri-arrow-up-s-line"></i> High</div>
                        </div>
                        <div class="stat-label" style="color: #be123c;">Total Monthly Expenses</div>
                        <div class="stat-value" style="color: #be123c;">₹
                            <?= number_format($summary['total'] ?? 0, 2) ?>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <div style="padding: 1.25rem; background: #f0fdf4; border-radius: 16px; border: 1px solid #dcfce7;">
                        <p
                            style="margin: 0; font-size: 0.75rem; color: #16a34a; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
                            Paid</p>
                        <h3 style="margin: 0; color: #166534; font-weight: 700;">₹
                            <?= number_format($summary['paid'] ?? 0, 2) ?>
                        </h3>
                    </div>
                    <div style="padding: 1.25rem; background: #fffbeb; border-radius: 16px; border: 1px solid #fef3c7;">
                        <p
                            style="margin: 0; font-size: 0.75rem; color: #d97706; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
                            Pending</p>
                        <h3 style="margin: 0; color: #92400e; font-weight: 700;">₹
                            <?= number_format($summary['pending'] ?? 0, 2) ?>
                        </h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="table-container" style="padding: 0; overflow: hidden; border-radius: 16px;">
            <div
                style="padding: 1rem 1.25rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-dark); margin: 0;">Expense
                    Transactions</h3>
            </div>

            <div style="padding: 1rem 1.25rem;">
                <table id="expenseTransactionsTable" class="premium-table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Category</th>
                            <th>Method</th>
                            <th>Remarks</th>
                            <th>Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php foreach ($expenses as $expense): ?>

                            <tr>

                                <td style="font-weight: 600; color: var(--text-muted);">
                                    <?= date('d M Y', strtotime($expense['expense_date'])) ?>
                                </td>

                                <td style="font-weight: 900; color: #e11d48; font-size: 1.1rem;">
                                    ₹ <?= number_format($expense['amount'], 2) ?>
                                </td>

                                <td style="font-weight: 700; color: var(--text-dark);">
                                    <?= htmlspecialchars($expense['expense_category']) ?>
                                </td>

                                <td style="font-weight: 600; color: var(--text-muted);">
                                    <?= htmlspecialchars($expense['payment_method']) ?>
                                </td>

                                <td style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">
                                    <?= htmlspecialchars($expense['description']) ?>
                                </td>

                                <td>

                                    <?php if ($expense['status'] == 'Paid'): ?>

                                        <span class="status-badge"
                                            style="background: #f0fdf4; color: #16a34a; border-color: #dcfce7;">

                                            Paid

                                        </span>

                                    <?php else: ?>

                                        <span class="status-badge"
                                            style="background: #fffbeb; color: #d97706; border-color: #fef3c7;">

                                            Pending

                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td style="text-align: right;">

                                    <button class="btn-icon-p" onclick='editExpense(<?= json_encode($expense) ?>)'>

                                        <i class="ri-pencil-line"></i>

                                    </button>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Category Modal -->
<div id="categoryModal" class="premium-modal-overlay">
    <div class="glass-card premium-modal-content" style="max-width: 450px;">

        <form method="POST">

            <input type="hidden" name="id" id="category_id">

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">

                <h3 style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--text-dark);">

                    Add Category

                </h3>
                <button type="button" onclick="closeModal('categoryModal')" class="btn-icon-p">

                    <i class="ri-close-line"></i>

                </button>

            </div>

            <div style="margin-bottom: 2rem;">

                <label
                    style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">

                    Category Name <span style="color:red;">*</span>

                </label>

                <input type="text" name="category_name" id="category_name" placeholder="e.g. Marketing, Logistics"
                    style="width: 100%; padding: 1.25rem; border: 1.5px solid #e2e8f0; border-radius: 16px; font-family: 'Outfit', sans-serif; font-size: 1rem; outline: none; background: #f8fafc; transition: all 0.2s;"
                    onfocus="this.style.borderColor='var(--primary)'; this.style.background='white';">
                <?php if (!empty($category_name_error)): ?>

                    <p style="color:red; font-size:13px; margin-top:6px;">
                        <?= $category_name_error ?>
                    </p>

                <?php endif; ?>

            </div>

            <div style="margin-bottom: 2rem;">

                <label
                    style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">

                    Status

                </label>

                <select name="status" id="category_status"
                    style="width: 100%; padding: 1rem; border: 1.5px solid #e2e8f0; border-radius: 16px; font-family: 'Outfit', sans-serif; font-size: 1rem; outline: none; background: #f8fafc; transition: all 0.2s;">

                    <option value="Active">Active</option>

                    <option value="Inactive">Inactive</option>

                </select>
                <?php if (!empty($status_error)): ?>

                    <p style="color:red; font-size:13px; margin-top:6px;">
                        <?= $status_error ?>
                    </p>

                <?php endif; ?>

            </div>

            <div style="display:flex; gap:12px; margin-top:20px;">
                <button type="submit" name="save_category" class="btn-premium" style="flex:1; padding:12px;">

                    Save Category

                </button>

                <button type="button" onclick="closeCategoryModal()" style="
                flex:1;
                background:#fff;
                border:1px solid #e2e8f0;
                padding:12px;
                border-radius:10px;
                cursor:pointer;
                font-weight:600;
                ">

                    Cancel

                </button>
            </div>

        </form>

    </div>
</div>

<!-- Expense Modal -->
<div id="expenseModal" class="premium-modal-overlay">
    <form method="POST" novalidate>
        <div class="glass-card premium-modal-content" style="max-width: 600px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h3 style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--text-dark);">New Expense</h3>
                <button type="button" onclick="event.preventDefault(); closeModal('expenseModal');" class="btn-icon-p">

                    <i class="ri-close-line"></i>

                </button>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                <div>
                    <label
                        style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">Category
                        <span style="color:red;">*</span></label>
                    <select name="expense_category"
                        style="width: 100%; padding: 1rem; border: 1.5px solid #e2e8f0; border-radius: 10px; background: #f8fafc; outline: none; font-family: 'Outfit';">

                        <option value="">Select Category</option>

                        <?php foreach ($categories as $category): ?>

                            <option value="<?= $category['category_name'] ?>">
                                <?= htmlspecialchars($category['category_name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                    <?php if (!empty($expense_category_error)): ?>

                        <small style="color:#dc2626; font-size:13px; margin-top:6px; display:block;">

                            <?= $expense_category_error ?>

                        </small>

                    <?php endif; ?>
                </div>
                <div>
                    <label
                        style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">Payment
                        Method</label>
                    <select name="payment_method"
                        style="width: 100%; padding: 1rem; border: 1.5px solid #e2e8f0; border-radius: 10px; background: #f8fafc; outline: none; font-family: 'Outfit'; ">
                        <option>Cash</option>
                        <option>UPI</option>
                        <option>Bank Transfer</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                <div>
                    <label
                        style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">Amount
                        (₹) <span style="color:red;">*</span></label>
                    <input type="number" placeholder="0.00" name="amount"
                        style="width: 100%; padding: 1rem; border: 1.5px solid #e2e8f0; border-radius: 10px; background: #f8fafc; outline: none;">
                    <?php if (!empty($amount_error)): ?>

                        <small style="color:#dc2626; font-size:13px; margin-top:6px; display:block;">

                            <?= $amount_error ?>

                        </small>

                    <?php endif; ?>
                </div>
                <div style="margin-bottom: 1.5rem;">

                    <label
                        style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">

                        Status

                    </label>

                    <select name="expense_status"
                        style="width: 100%; padding: 1rem; border: 1.5px solid #e2e8f0; border-radius: 10px; background: #f8fafc; outline: none; font-family: 'Outfit';">

                        <option value="Paid">Paid</option>
                        <option value="Pending">Pending</option>

                    </select>

                </div>
                <div>
                    <label
                        style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">Date
                        <span style="color:red;">*</span></label>
                    <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>"
                        style="width: 100%; padding: 1rem; border: 1.5px solid #e2e8f0; border-radius: 10px; background: #f8fafc; outline: none; font-family: 'Outfit';">
                    <?php if (!empty($expense_date_error)): ?>

                        <small style="color:#dc2626; font-size:13px; margin-top:6px; display:block;">

                            <?= $expense_date_error ?>

                        </small>

                    <?php endif; ?>
                </div>
            </div>

            <div style="margin-bottom: 2rem;">
                <label
                    style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">Remarks</label>
                <textarea name="description" rows="3" placeholder="Optional notes about this expense..."
                    style="width: 100%; padding: 1rem; border: 1.5px solid #e2e8f0; border-radius: 10px; background: #f8fafc; outline: none; resize: none; font-family: 'Outfit';"></textarea>
            </div>

            <div style="display:flex; gap:12px; margin-top:20px;">

                <button type="submit" name="save_expense" class="btn-premium" style="flex:1; padding:12px;">

                    Record Expense

                </button>

                <button type="button" onclick="closeModal('expenseModal')" style="
                    flex:1;
                    background:#ffffff;
                    color:#111827;
                    border:1px solid #d1d5db;
                    padding:12px;
                    border-radius:10px;
                    cursor:pointer;
                    font-weight:600;
                    ">

                    Cancel

                </button>

            </div>
        </div>
    </form>
</div>

<style>
    .premium-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(8px);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
        animation: fadeIn 0.3s ease-out;
    }

    .premium-modal-content {
        width: 100%;
        padding: 2rem;
        animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .card-expense {
        --primary: #ef4444;
        --primary-light: #fee2e2;
    }

    .premium-table th {
        font-size: 0.75rem;
        padding: 0.85rem 0.75rem;
        letter-spacing: .03em;
    }

    .premium-table td {
        padding: 0.9rem 0.75rem;
    }

    .table-container {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
    }

    .main-content {
        background: #f8fafc;
    }

    .premium-table td {
        padding: 0.85rem 0.75rem;
    }

    .premium-table th {
        padding: 0.8rem 0.75rem;
        font-size: 0.74rem;
    }

    .table-container {
        background: #fff;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px) scale(0.95);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
</style>

<script>
    function openCategoryModal() { document.getElementById('categoryModal').style.display = 'flex'; }
    function openExpenseModal() { document.getElementById('expenseModal').style.display = 'flex'; }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }

    // Close modal on click outside
    window.onclick = function (event) {
        if (event.target.classList.contains('premium-modal-overlay')) {
            event.target.style.display = 'none';
        }
    }

    $(document).ready(function () {
        initializeDataTable('expenseTransactionsTable', 'Expense Transactions');
    });
</script>
<form method="POST" id="deleteCategoryForm">

    <input type="hidden" name="delete_category_id" id="delete_category_id">

    <input type="hidden" name="delete_category" value="1">

</form>
<?php if (!empty($successMessage)): ?>

    <script>
        document.addEventListener('DOMContentLoaded', function () {

            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: '<?= addslashes($successMessage) ?>',
                timer: 2000,
                showConfirmButton: false
            });

        });
    </script>

<?php endif; ?>
<script>
    function openCategoryModal() {
        document.getElementById('categoryModal').style.display = 'flex';

        document.getElementById('category_id').value = '';

        document.getElementById('category_name').value = '';

        document.getElementById('category_status').value = 'Active';

        document.querySelector('#categoryModal h3').innerText = 'Add Category';

        document.querySelector('[name="save_category"]').innerText = 'Save Category';
    }

    function editCategory(data) {
        document.getElementById('categoryModal').style.display = 'flex';

        document.getElementById('category_id').value = data.id;

        document.getElementById('category_name').value = data.category_name;

        document.getElementById('category_status').value = data.status;

        document.querySelector('#categoryModal h3').innerText = 'Edit Category';

        document.querySelector('[name="save_category"]').innerText = 'Update Category';
    }

    function deleteCategory(id) {
        Swal.fire({

            title: 'Delete Category?',
            text: "This category will be removed.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#4f46e5',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, Delete'

        }).then((result) => {

            if (result.isConfirmed) {

                document.getElementById('delete_category_id').value = id;

                document.getElementById('deleteCategoryForm').submit();
            }
        });
    }
    function closeCategoryModal() {
        document.getElementById('categoryModal').style.display = 'none';
    }
    <?php if (
        !empty($category_name_error) ||
        !empty($status_error) ||
        !empty($errorMessage)
    ): ?>

        document.addEventListener('DOMContentLoaded', function () {

            document.getElementById('categoryModal').style.display = 'flex';

        });

    <?php endif; ?>
</script>
<script>

    function deleteExpense(id) {

        Swal.fire({
            title: 'Delete Expense?',
            text: "This action cannot be undone.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#4f46e5',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, Delete'
        }).then((result) => {

            if (result.isConfirmed) {

                document.getElementById('delete_expense_id').value = id;

                document.getElementById('deleteExpenseForm').submit();
            }

        });
    }

    function editExpense(expense) {

        openExpenseModal();

        document.querySelector('[name="expense_category"]').value = expense.expense_category;

        document.querySelector('[name="payment_method"]').value = expense.payment_method;

        document.querySelector('[name="amount"]').value = expense.amount;

        document.querySelector('[name="expense_date"]').value = expense.expense_date;

        document.querySelector('[name="description"]').value = expense.description;

        document.querySelector('[name="expense_status"]').value = expense.status;

        let hiddenId = document.getElementById('expense_id');

        if (!hiddenId) {

            hiddenId = document.createElement('input');

            hiddenId.type = 'hidden';

            hiddenId.name = 'expense_id';

            hiddenId.id = 'expense_id';

            document.querySelector('#expenseModal form').appendChild(hiddenId);
        }

        hiddenId.value = expense.id;
    }
    <?php if (
        !empty($expense_category_error) ||
        !empty($amount_error) ||
        !empty($expense_date_error)
    ): ?>

        document.addEventListener('DOMContentLoaded', function () {

            openExpenseModal();

        });

    <?php endif; ?>

</script>

<?php include __DIR__ . '/includes/datatable.php'; ?>
<?php include 'includes/footer.php'; ?>