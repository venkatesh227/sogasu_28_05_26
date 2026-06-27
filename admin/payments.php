<?php
session_start();
include '../includes/db.php';
$pageTitle = "Payments - Sogasu";
$activePage = "payments";
include 'includes/header.php';
$from_date = $_GET['from_date'] ?? date('Y-m-01'); // current month 1st day
$to_date = $_GET['to_date'] ?? date('Y-m-d');    // today

$dateConditionExpenses = "";

if (!empty($from_date) && !empty($to_date)) {
    $dateConditionOrders = " AND DATE(o.created_at) BETWEEN '$from_date' AND '$to_date' ";
    $dateConditionBills =
        " AND DATE(created_at) BETWEEN '$from_date' AND '$to_date' ";
    $dateConditionExpenses = " AND DATE(expense_date) BETWEEN '$from_date' AND '$to_date' ";
}
$dateConditionBillPayments = "";

if (!empty($from_date) && !empty($to_date)) {
    $dateConditionBillPayments =
        " AND DATE(bp.payment_date) BETWEEN '$from_date' AND '$to_date' ";
}

$incomeStmt = $pdo->query("
    SELECT COALESCE(SUM(bp.payment_amount),0)
    FROM bill_payments bp
    WHERE bp.is_deleted = 0
    $dateConditionBillPayments
");
$totalIncome = $incomeStmt->fetchColumn();

$pendingStmt = $pdo->query("
    SELECT COALESCE(SUM(pending_amount),0)
    FROM bills
    WHERE pending_amount > 0
    AND is_deleted = 0
");
$pendingReceivables = $pendingStmt->fetchColumn();

$invoiceStmt = $pdo->query("
    SELECT COUNT(*)
    FROM bills
    WHERE pending_amount > 0
    AND is_deleted = 0
");
$pendingInvoices = $invoiceStmt->fetchColumn();

// Monthly Expenses
$expenseStmt = $pdo->query("
    SELECT COALESCE(SUM(amount),0)
    FROM expenses
    WHERE 1=1
    $dateConditionExpenses
");
$monthlyExpenses = $expenseStmt->fetchColumn();
$transactions = [];

// Income from orders
$stmt1 = $pdo->query("
    SELECT
        bp.payment_date as txn_date,
        COALESCE(o.order_code, co.order_code, oo.order_code) as reference,

        CASE
            WHEN bp.order_type = 'customer_orders' THEN 'Customer Order'
            ELSE CONCAT(c.first_name, ' ', c.last_name)
        END as description,

        'Income' as type,

        bp.payment_method,

        'success' as status,

        bp.payment_amount as amount

    FROM bill_payments bp

    LEFT JOIN orders o
        ON bp.order_type='orders'
        AND o.id=bp.order_id

    LEFT JOIN customer_orders co
        ON bp.order_type='customer_orders'
        AND co.id=bp.order_id

    LEFT JOIN outsource_orders oo
        ON bp.order_type='outsource_orders'
        AND oo.id=bp.order_id

    LEFT JOIN customers c
        ON c.id = COALESCE(o.customer_id, oo.customer_id)

    WHERE bp.is_deleted = 0
    $dateConditionBillPayments
");

// Expenses
$stmt2 = $pdo->query("
    SELECT created_at as txn_date,
           CONCAT('EXP-', id) as reference,
           expense_category as description,
           'Expense' as type,
           payment_method,
           status,
           amount
    FROM expenses
    WHERE 1=1
    $dateConditionExpenses
");

$transactions = array_merge(
    $stmt1->fetchAll(PDO::FETCH_ASSOC),
    $stmt2->fetchAll(PDO::FETCH_ASSOC)
);

usort($transactions, function ($a, $b) {
    return strtotime($b['txn_date']) - strtotime($a['txn_date']);
});
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div class="dashboard-container animate-fade-in" style="padding: 1.25rem;">

        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.5rem;">

            <div>
                <h1 style="font-size:2rem; font-weight:800; color:var(--text-dark); margin-bottom:0.35rem;">
                    Payments & Finance
                </h1>

                <p style="color:var(--text-muted); font-size:1rem;">
                    Track income, expenses and manage pending receivables.
                </p>
            </div>

            <button class="btn-premium">
                <i class="ri-add-line"></i>
                Record Expense
            </button>

        </div>

        <!-- Stats Grid -->
        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:1.5rem;">

            <!-- CARD 1 -->
            <div class="table-container" style="padding:1.25rem;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">

                    <div>
                        <div style="font-size:0.75rem; font-weight:700; color:#64748b; text-transform:uppercase;">
                            TOTAL INCOME (FEB)
                        </div>

                        <div style="font-size:2rem; font-weight:800; color:#0f172a; margin:0.75rem 0 0.35rem;">
                            ₹ <?= number_format($totalIncome, 2) ?>
                        </div>

                        <div style="font-size:0.85rem; color:#64748b;">
                            vs ₹ 1,08,200 last month
                        </div>
                    </div>

                    <div style="
                width:48px;
                height:48px;
                border-radius:12px;
                background:rgba(79,70,229,0.1);
                color:#4f46e5;
                display:flex;
                align-items:center;
                justify-content:center;
                font-size:1.3rem;
                flex-shrink:0;
            ">
                        <i class="ri-line-chart-line"></i>
                    </div>

                </div>
            </div>

            <!-- CARD 2 -->
            <div class="table-container" style="padding:1.25rem;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">

                    <div>
                        <div style="font-size:0.75rem; font-weight:700; color:#64748b; text-transform:uppercase;">
                            PENDING RECEIVABLES
                        </div>

                        <div style="font-size:2rem; font-weight:800; color:#0f172a; margin:0.75rem 0 0.35rem;">
                            ₹ <?= number_format($pendingReceivables, 2) ?>
                        </div>

                        <div style="font-size:0.85rem; color:#64748b;">
                            <?= $pendingInvoices ?> outstanding invoices
                        </div>
                    </div>

                    <div style="
                width:48px;
                height:48px;
                border-radius:12px;
                background:rgba(245,158,11,0.1);
                color:#f59e0b;
                display:flex;
                align-items:center;
                justify-content:center;
                font-size:1.3rem;
                flex-shrink:0;
            ">
                        <i class="ri-error-warning-line"></i>
                    </div>

                </div>
            </div>

            <!-- CARD 3 -->
            <div class="table-container" style="padding:1.25rem;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">

                    <div>
                        <div style="font-size:0.75rem; font-weight:700; color:#64748b; text-transform:uppercase;">
                            MONTHLY EXPENSES
                        </div>

                        <div style="font-size:2rem; font-weight:800; color:#0f172a; margin:0.75rem 0 0.35rem;">
                            ₹ <?= number_format($monthlyExpenses, 2) ?>
                        </div>

                        <div style="font-size:0.85rem; color:#64748b;">
                            Salaries, Rent & Materials
                        </div>
                    </div>

                    <div style="
                width:48px;
                height:48px;
                border-radius:12px;
                background:rgba(239,68,68,0.1);
                color:#ef4444;
                display:flex;
                align-items:center;
                justify-content:center;
                font-size:1.3rem;
                flex-shrink:0;
            ">
                        <i class="ri-wallet-3-line"></i>
                    </div>

                </div>
            </div>

        </div>
        <form method="GET" style="display:flex; gap:10px; align-items:end; margin-bottom:20px;">
            <div>
                <label>From Date</label><br>
                <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>" class="form-control">
            </div>
            <div>
                <label>To Date</label><br>
                <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>" class="form-control">
            </div>
            <button type="submit" class="btn-premium">
                Filter
            </button>
            <a href="payments.php" class="btn btn-secondary">Reset</a>
        </form>

    </div>

    <!-- Transaction Table -->
    <div class="glass-card"
        style="padding:0; overflow:hidden; border-radius:18px; border:1px solid #eef2f7; box-shadow:0 4px 20px rgba(15,23,42,0.04);">
        <div
            style="padding:1rem 1.25rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-dark); margin: 0;">Recent
                Transactions</h3>
            <div style="display: flex; gap: 1rem;">
                <select class="form-control" style="
                        padding:0.55rem 0.9rem;
                        border-radius:10px;
                        font-size:0.85rem;
                        font-weight:600;
                        border:1px solid #e2e8f0;
                        background:#fff;
                        min-width:180px;
                        ">
                    <option>All Transactions</option>
                    <option>Income</option>
                    <option>Expense</option>
                    <option>Receivable</option>
                </select>
            </div>
        </div>

        <div style="padding:1rem 1.25rem;">
            <table id="paymentsTable" class="premium-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Reference</th>
                        <th>Description / Party</th>
                        <th>Type</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th style="text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $txn): ?>
                        <tr>
                            <td><?= date('d M, h:i A', strtotime($txn['txn_date'])) ?></td>

                            <td><?= htmlspecialchars($txn['reference']) ?></td>

                            <td><?= htmlspecialchars($txn['description']) ?></td>

                            <td><?= $txn['type'] ?></td>

                            <td><?= $txn['payment_method'] ?: '-' ?></td>

                            <td><?= ucfirst($txn['status']) ?></td>

                            <td style="text-align:right;">
                                <?= $txn['type'] === 'Expense' ? '- ₹ ' : '+ ₹ ' ?>
                                <?= number_format($txn['amount'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    </div>
</main>

<style>
    .card-income {
        --primary: #10b981;
        --primary-light: #d1fae5;
    }

    .card-warning {
        --primary: #f59e0b;
        --primary-light: #fef3c7;
    }

    .card-expense {
        --primary: #ef4444;
        --primary-light: #fee2e2;
    }

    .premium-stat-card {
        background: #fff;
        border: 1px solid #eef2f7;
        border-radius: 18px;
        padding: 1.4rem;
        box-shadow: 0 4px 20px rgba(15, 23, 42, 0.04);
    }

    .stat-header {
        margin-bottom: 1rem;
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
    }

    .stat-label {
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    .stat-value {
        font-size: 1.75rem;
        font-weight: 800;
        margin: 0.35rem 0;
    }

    .stat-footer {
        font-size: 0.85rem;
    }

    .premium-table td {
        padding: 1rem 0.75rem;
    }

    .premium-table th {
        padding: 0.9rem 0.75rem;
        font-size: 0.75rem;
        letter-spacing: .04em;
    }

    .main-content {
        background: #f8fafc;
    }
</style>

<?php include __DIR__ . '/includes/datatable.php'; ?>
<script>
    $(document).ready(function () {
        initializeDataTable('paymentsTable', 'Payments & Transactions');
    });
</script>

<?php include 'includes/footer.php'; ?>