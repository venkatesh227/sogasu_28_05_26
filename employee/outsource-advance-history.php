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
// Opening Advance Balance
// =========================
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(
        CASE
            WHEN LOWER(status)='paid' THEN amount
            WHEN LOWER(status)='deducted' THEN -ABS(amount)
            ELSE 0
        END
    ),0)
    FROM employee_payments
    WHERE employee_id=?
    AND payment_type='Advance'
    AND payment_date < ?
");
$stmt->execute([$employee_id, $fromDate]);
$openingBalance = $stmt->fetchColumn();


// =========================
// Advance Transactions
// =========================
$stmt = $pdo->prepare("
    SELECT
        id,
        payment_date as txn_date,
        description,
        amount,
        status
    FROM employee_payments
    WHERE employee_id=?
    AND payment_type='Advance'
    AND payment_date BETWEEN ? AND ?
    ORDER BY payment_date ASC, id ASC
");
$stmt->execute([$employee_id, $fromDate, $toDate]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Summary calculations
$totalAdvanceTaken = 0;
$totalAdvanceRepaid = 0;
$closingBalance = $openingBalance;

foreach ($transactions as $txn) {
    if (strtolower($txn['status']) == 'deducted') {
        $totalAdvanceRepaid += abs($txn['amount']);
        $closingBalance -= abs($txn['amount']);
    } else {
        $totalAdvanceTaken += $txn['amount'];
        $closingBalance += $txn['amount'];
    }
}

$pageTitle = "Advance History";
$headerTitle = "Advance History";
$activePage = "advance";

include 'includes/outsource-header.php';
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">

<div class="container" style="padding-bottom:100px;">

    <!-- Summary -->
    <!-- Summary -->
    <div class="card" style="
    background:linear-gradient(135deg,#0f172a,#1e293b);
    color:white;
    padding:2rem;
    border-radius:24px;
    position:relative;
    overflow:hidden;
">
        <div>Outstanding Advance</div>

        <div style="font-size:2.3rem;font-weight:800;margin-top:10px;">
            ₹<?= number_format($closingBalance, 0) ?>
        </div>

        <div style="margin-top:1rem;font-size:.9rem;">
            Opening Balance: ₹<?= number_format($openingBalance, 0) ?>
        </div>

        <i class="ri-wallet-3-line" style="
        position:absolute;
        right:-10px;
        top:-10px;
        font-size:5rem;
        opacity:.15;
        transform:rotate(-15deg);
    "></i>
    </div>

    <!-- Filters -->
    <form method="GET" class="card" style="padding:1rem;margin:1rem 0;">
        <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:end;">
            <div>
                <label>From Date</label><br>
                <input type="date" name="from_date" value="<?= $fromDate ?>"
                    style="padding:.5rem;border:1px solid #ccc;border-radius:8px;">
            </div>

            <div>
                <label>To Date</label><br>
                <input type="date" name="to_date" value="<?= $toDate ?>"
                    style="padding:.5rem;border:1px solid #ccc;border-radius:8px;">
            </div>

            <div style="display:flex;gap:.5rem;">
                <button type="submit" style="
                    padding:.6rem 1rem;
                    border:none;
                    background:#7c3aed;
                    color:white;
                    border-radius:8px;
                    font-weight:600;
                ">Filter</button>

                <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>" style="
                    padding:.6rem 1rem;
                    background:#ef4444;
                    color:white;
                    text-decoration:none;
                    border-radius:8px;
                    font-weight:600;
                ">Reset</a>
            </div>
        </div>
    </form>

    <!-- Mini Summary -->
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:14px;margin-bottom:20px;">
        <div class="card" style="padding:1rem;">
            <div>Total Advance Taken</div>
            <div style="font-size:1.5rem;font-weight:700;color:#16a34a;">
                ₹<?= number_format($totalAdvanceTaken, 0) ?>
            </div>
        </div>

        <div class="card" style="padding:1rem;">
            <div>Total Advance Repaid</div>
            <div style="font-size:1.5rem;font-weight:700;color:#dc2626;">
                ₹<?= number_format($totalAdvanceRepaid, 0) ?>
            </div>
        </div>
    </div>

    <?php if (empty($transactions)): ?>
        <div class="card" style="padding:2rem;text-align:center;">
            No advance records found
        </div>
    <?php else: ?>

        <?php $runningBalance = $openingBalance; ?>

        <div class="card" style="padding:1rem;overflow:auto;">
            <table id="advanceTable" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>S.No</th>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Taken</th>
                        <th>Repaid</th>
                        <th>Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $index => $txn): ?>
                        <?php
                        $taken = 0;
                        $repaid = 0;

                        if (strtolower($txn['status']) == 'deducted') {
                            $repaid = abs($txn['amount']);
                            $runningBalance -= $repaid;
                        } else {
                            $taken = $txn['amount'];
                            $runningBalance += $taken;
                        }
                        ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= date('d-m-Y', strtotime($txn['txn_date'])) ?></td>
                            <td><?= htmlspecialchars($txn['description']) ?></td>
                            <td>₹<?= number_format($taken, 0) ?></td>
                            <td>₹<?= number_format($repaid, 0) ?></td>
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
        $('#advanceTable').DataTable({
            paging: true,
            searching: true,
            ordering: true,
            pageLength: 10
        });
    });
</script>

<?php include 'includes/outsource-bottom-nav.php'; ?>