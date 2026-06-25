<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT id
    FROM employees
    WHERE user_id = ?
    AND employee_type='outsource'
    AND is_deleted=0
");
$stmt->execute([$user_id]);
$employee = $stmt->fetch();

if (!$employee) {
    die("Outsource employee not found");
}

$employee_id = $employee['id'];

$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate = $_GET['to_date'] ?? date('Y-m-d');


// =========================
// Opening Balance
// =========================

// earnings before from date
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(outsource_credit),0)
    FROM outsource_orders
    WHERE assigned_employee_id=?
    AND order_status='completed'
    AND is_deleted=0
    AND DATE(created_at) < ?
");
$stmt->execute([$employee_id, $fromDate]);
$prevEarnings = $stmt->fetchColumn();

// payments before from date
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount),0)
    FROM employee_payments
    WHERE employee_id=?
    AND payment_type != 'Advance'
    AND payment_date < ?
");
$stmt->execute([$employee_id, $fromDate]);
$prevPayments = $stmt->fetchColumn();

$openingBalance = $prevEarnings - $prevPayments;


// =========================
// Earnings transactions
// =========================
$stmt = $pdo->prepare("
    SELECT 
        DATE(created_at) as txn_date,
        outsource_credit as earnings,
        0 as payments,
        CONCAT('ORD-', id) as reference_id
    FROM outsource_orders
    WHERE assigned_employee_id=?
    AND order_status='completed'
    AND is_deleted=0
    AND DATE(created_at) BETWEEN ? AND ?
");
$stmt->execute([$employee_id, $fromDate, $toDate]);
$earnings = $stmt->fetchAll(PDO::FETCH_ASSOC);


// =========================
// Payment transactions
// =========================
$stmt = $pdo->prepare("
    SELECT
        payment_date as txn_date,
        0 as earnings,
        amount as payments,
        CONCAT('PAY-', LPAD(id, 5, '0')) as reference_id
    FROM employee_payments
    WHERE employee_id=?
    AND payment_type != 'Advance'
    AND payment_date BETWEEN ? AND ?
");
$stmt->execute([$employee_id, $fromDate, $toDate]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Merge transactions
$transactions = array_merge($earnings, $payments);

usort($transactions, function ($a, $b) {
    return strtotime($a['txn_date']) - strtotime($b['txn_date']);
});

$runningBalance = $openingBalance;

$pageTitle = "Ledger";
$headerTitle = "Ledger";
$activePage = "ledger";
$closingBalance = $openingBalance;

foreach ($transactions as $txn) {
    $closingBalance += $txn['earnings'];
    $closingBalance -= $txn['payments'];
}

include 'includes/outsource-header.php';
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">

<div class="container" style="padding-bottom:100px;">

    <!-- Summary -->
    <div class="card" style="
        background:linear-gradient(135deg,#0f172a,#1e293b);
        color:white;
        padding:2rem;
        border-radius:24px;
    ">
        <div>Current Balance</div>

        <div style="font-size:2.5rem;font-weight:800;margin-top:10px;">
            ₹<?= number_format($closingBalance, 0) ?>
        </div>

        <div style="margin-top:1rem;font-size:.9rem;">
            Opening Balance: ₹<?= number_format($openingBalance, 0) ?>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="card" style="padding:1rem;margin:1rem 0;">
        <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:end;">
            <div>
                <label>From Date</label><br>
                <input type="date" name="from_date" value="<?= $fromDate ?>" style="
                        padding:.5rem;
                        border:1px solid #ccc;
                        border-radius:8px;
                    ">
            </div>

            <div>
                <label>To Date</label><br>
                <input type="date" name="to_date" value="<?= $toDate ?>" style="
                        padding:.5rem;
                        border:1px solid #ccc;
                        border-radius:8px;
                    ">
            </div>

            <div style="display:flex;gap:.5rem;align-items:center;">

                <button type="submit" style="
                    padding:.6rem 1rem;
                    border:none;
                    background:#7c3aed;
                    color:white;
                    border-radius:8px;
                    cursor:pointer;
                    font-weight:600;
                ">
                    Filter
                </button>

                <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>" style="
                    padding:.6rem 1rem;
                    background:#ef4444;
                    color:white;
                    text-decoration:none;
                    border-radius:8px;
                    display:flex;
                    align-items:center;
                    font-weight:600;
                ">
                    Reset
                </a>

            </div>
        </div>
    </form>

    <div class="section-title" style="margin-top:20px;">
        Ledger History
    </div>

    <?php if (empty($transactions)): ?>
        <div class="card" style="padding:2rem;text-align:center;">
            No transactions found
        </div>
    <?php else: ?>

        <div class="card" style="padding:1rem;overflow:auto;">
            <table id="ledgerTable" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>S.No</th>
                        <th>Date</th>
                        <th>Reference ID</th>
                        <th>Earnings</th>
                        <th>Payments</th>
                        <th>Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $index => $txn): ?>
                        <?php
                        $runningBalance += $txn['earnings'];
                        $runningBalance -= $txn['payments'];
                        ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= date('d-m-Y', strtotime($txn['txn_date'])) ?></td>
                            <td><?= $txn['reference_id'] ?></td>
                            <td>₹<?= number_format($txn['earnings'], 0) ?></td>
                            <td>₹<?= number_format($txn['payments'], 0) ?></td>
                            <td>₹<?= number_format($runningBalance, 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

<script>
    $(document).ready(function () {
        $('#ledgerTable').DataTable({
            paging: true,
            searching: true,
            ordering: true,
            pageLength: 10
        });
    });
</script>

<?php include 'includes/outsource-bottom-nav.php'; ?>