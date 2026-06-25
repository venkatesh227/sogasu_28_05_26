<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT id, first_name, last_name
    FROM employees
    WHERE user_id = ?
    AND employee_type = 'outsource'
    AND is_deleted = 0
");
$stmt->execute([$user_id]);
$employee = $stmt->fetch();

if (!$employee) {
    die("Outsource employee not found");
}

$employee_id = $employee['id'];

$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate   = $_GET['to_date'] ?? date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT
        id,
        payment_date,
        amount
    FROM employee_payments
    WHERE employee_id = ?
    AND payment_type NOT LIKE '%Advance%'
    AND payment_date BETWEEN ? AND ?
    ORDER BY payment_date DESC
");
$stmt->execute([$employee_id, $fromDate, $toDate]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalPayments = 0;
foreach ($payments as $payment) {
    $totalPayments += $payment['amount'];
}

$pageTitle = "My Payments";
$headerTitle = "Payments";
$activePage = "payments";

include 'includes/outsource-header.php';
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">

<div class="container" style="padding-bottom:100px;">

    <!-- Summary Card -->
    <div class="card" style="
        background: linear-gradient(135deg, #0f172a, #1e293b);
        color:white;
        border:none;
        padding:2rem 1.5rem;
        border-radius:24px;
        position:relative;
        overflow:hidden;
        box-shadow:0 10px 25px rgba(15,23,42,.4);
    ">
        <div style="position:absolute;right:-30px;top:-30px;font-size:8rem;opacity:.05;">
            <i class="ri-bank-card-line"></i>
        </div>

        <div style="position:relative;z-index:1;">
            <div style="
                font-size:.85rem;
                text-transform:uppercase;
                letter-spacing:1.5px;
                opacity:.7;
                margin-bottom:.5rem;">
                Total Payments
            </div>

            <div style="
                font-size:2.75rem;
                font-weight:800;
                margin-bottom:1.5rem;">
                ₹<?= number_format($totalPayments, 0) ?>
            </div>

            <div style="
                background:rgba(34,197,94,.2);
                border:1px solid rgba(34,197,94,.3);
                padding:.5rem 1rem;
                border-radius:12px;
                display:inline-flex;
                align-items:center;
                gap:.5rem;">
                <div style="
                    width:8px;
                    height:8px;
                    background:#22c55e;
                    border-radius:50%;">
                </div>

                <span style="font-size:.75rem;font-weight:600;">
                    <?= count($payments) ?> Payments
                </span>
            </div>
        </div>
    </div>

    <!-- Filter -->
    <form method="GET" class="card" style="padding:1rem;margin:1rem 0;">
        <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:end;">

            <div>
                <label>From Date</label><br>
                <input type="date" name="from_date"
                    value="<?= $fromDate ?>"
                    style="padding:.5rem;border:1px solid #ccc;border-radius:8px;">
            </div>

            <div>
                <label>To Date</label><br>
                <input type="date" name="to_date"
                    value="<?= $toDate ?>"
                    style="padding:.5rem;border:1px solid #ccc;border-radius:8px;">
            </div>

            <div style="display:flex;gap:.5rem;">
                <button type="submit"
                    style="padding:.6rem 1rem;border:none;background:#7c3aed;color:white;border-radius:8px;">
                    Filter
                </button>

                <a href="outsource-payments.php"
                    style="
                        padding:.6rem 1rem;
                        background:#ef4444;
                        color:white;
                        text-decoration:none;
                        border-radius:8px;
                        display:flex;
                        align-items:center;">
                    Reset
                </a>
            </div>
        </div>
    </form>

    <!-- History -->
    <div class="section-title" style="
        margin-top:2rem;
        display:flex;
        justify-content:space-between;
        align-items:center;">
        <span>Payment History</span>
        <i class="ri-history-line"></i>
    </div>

    <?php if (empty($payments)): ?>
        <div class="card" style="
            text-align:center;
            padding:3rem 1.5rem;
            border-style:dashed;">
            No payments found
        </div>
    <?php else: ?>

        <div class="card" style="padding:1rem;overflow:auto;">
            <table id="paymentsTable" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>S.No</th>
                        <th>Date</th>
                        <th>Payment ID</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $index => $payment): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= date('d-m-Y', strtotime($payment['payment_date'])) ?></td>
                            <td>PAY-<?= str_pad($payment['id'], 5, '0', STR_PAD_LEFT) ?></td>
                            <td>₹<?= number_format($payment['amount'], 0) ?></td>
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
    $('#paymentsTable').DataTable({
        paging: true,
        searching: true,
        ordering: true,
        pageLength: 10
    });
});
</script>

<?php include 'includes/outsource-bottom-nav.php'; ?>