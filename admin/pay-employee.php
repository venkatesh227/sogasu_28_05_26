<?php
session_start();
include '../includes/db.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: payroll.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND is_deleted=0");
$stmt->execute([$id]);
$employee = $stmt->fetch();
$currentMonth = date('m');
$currentYear = date('Y');

$stmt = $pdo->prepare("
SELECT payment_date,amount
FROM employee_payments
WHERE employee_id = ?
AND payment_type='Salary'
AND MONTH(payment_date)=?
AND YEAR(payment_date)=?
LIMIT 1
");

$stmt->execute([$id, $currentMonth, $currentYear]);

$salaryPaid = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header("Location: payroll.php");
    exit;
}

$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-t');

// Fetch Attendance Stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE employee_id = ? AND attendance_date BETWEEN ? AND ? AND status = 'Present'");
$stmt->execute([$id, $from_date, $to_date]);
$present_days = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE employee_id = ? AND attendance_date BETWEEN ? AND ? AND status = 'Half Day'");
$stmt->execute([$id, $from_date, $to_date]);
$half_days = $stmt->fetchColumn() ?: 0;

// Fetch Approved OT Details
$stmt = $pdo->prepare("SELECT * FROM employee_overtime WHERE employee_id = ? AND status = 'Approved' AND ot_date BETWEEN ? AND ? ORDER BY ot_date ASC");
$stmt->execute([$id, $from_date, $to_date]);
$approved_ots = $stmt->fetchAll();

$pending_ot = 0;
foreach ($approved_ots as $ot)
    $pending_ot += $ot['amount'];

$daily_rate = $employee['base_salary'] / 30;
$attendance_pay = ($present_days * $daily_rate) + ($half_days * ($daily_rate / 2));

// Calculate Outstanding Advance
$stmt = $pdo->prepare("SELECT SUM(amount) FROM employee_payments WHERE employee_id = ? AND payment_type = 'Advance' AND status = 'Paid'");
$stmt->execute([$id]);
$adv_given = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("SELECT SUM(amount) FROM employee_payments WHERE employee_id = ? AND payment_type = 'Advance' AND status = 'Deducted'");
$stmt->execute([$id]);
$adv_repaid = $stmt->fetchColumn() ?: 0;

$outstanding_advance = $adv_given - $adv_repaid;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $currentMonth = date('m');
    $currentYear = date('Y');

    $stmt = $pdo->prepare("
SELECT COUNT(*)
FROM employee_payments
WHERE employee_id=?
AND payment_type='Salary'
AND MONTH(payment_date)=?
AND YEAR(payment_date)=?
");

    $stmt->execute([$id, $currentMonth, $currentYear]);

    if ($stmt->fetchColumn() > 0) {

        $_SESSION['error'] = "Salary already paid for this month.";

        header("Location: employee-history.php?id=" . $id);

        exit;
    }
    $base_salary = floatval($_POST['base_salary'] ?? 0);
    $overtime = floatval($_POST['overtime'] ?? 0);
    $bonus = floatval($_POST['bonus'] ?? 0);
    $lates = floatval($_POST['lates'] ?? 0);
    $advance_repay = floatval($_POST['advance_repay'] ?? 0);
    $deductions = $lates + $advance_repay;

    $notes = $_POST['notes'] ?? '';
    $method = $_POST['method'] ?? 'Bank Transfer';

    $payment_date = date('Y-m-d');

    // Insert Salary based on Amount Paying Now
    $paying_now = floatval($_POST['paying_now'] ?? 0);
    $salary_to_log = $paying_now - $overtime - $bonus + $deductions;

    if ($salary_to_log != 0) {
        $stmt = $pdo->prepare("INSERT INTO employee_payments (employee_id, payment_date, description, payment_type, amount, status) VALUES (?, ?, ?, 'Salary', ?, 'Paid')");
        $stmt->execute([$id, $payment_date, 'Salary Payment (' . $method . ') ' . ($notes ? "- $notes" : ""), $salary_to_log]);
    }

    // Insert Overtime
    if ($overtime > 0) {
        $stmt = $pdo->prepare("INSERT INTO employee_payments (employee_id, payment_date, description, payment_type, amount, status) VALUES (?, ?, ?, 'Overtime', ?, 'Paid')");
        $stmt->execute([$id, $payment_date, 'Overtime Payment', $overtime]);

        $pdo->prepare("UPDATE employee_overtime SET status = 'Paid' WHERE employee_id = ? AND status IN ('Pending', 'Approved')")->execute([$id]);
    }

    // Insert Bonus
    if ($bonus > 0) {
        $stmt = $pdo->prepare("INSERT INTO employee_payments (employee_id, payment_date, description, payment_type, amount, status) VALUES (?, ?, ?, 'Bonus', ?, 'Paid')");
        $stmt->execute([$id, $payment_date, 'Bonus/Incentive', $bonus]);
    }

    // Insert Deductions (Lates/Fines)
    if ($lates > 0) {
        $stmt = $pdo->prepare("INSERT INTO employee_payments (employee_id, payment_date, description, payment_type, amount, status) VALUES (?, ?, ?, 'Fine', ?, 'Deducted')");
        $stmt->execute([$id, $payment_date, 'Late/Fine Deduction', $lates]);
    }

    // Insert Advance Repayment
    if ($advance_repay > 0) {
        $stmt = $pdo->prepare("INSERT INTO employee_payments (employee_id, payment_date, description, payment_type, amount, status) VALUES (?, ?, ?, 'Advance', ?, 'Deducted')");
        $stmt->execute([$id, $payment_date, 'Advance Repayment', $advance_repay]);
    }

    header("Location: employee-history.php?id=" . $id);
    exit;
}

$pageTitle = "Process Payment - Sogasu";
$activePage = "payroll";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div>
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Process Payment</h2>
                <p class="text-muted">
                    <?= htmlspecialchars(trim($employee['first_name'] . ' ' . $employee['last_name'])) ?> -
                    <?= htmlspecialchars($employee['pay_cycle']) ?>
                </p>
            </div>
            <button class="btn" onclick="history.back()"
                style="background: white; border: 1px solid #e2e8f0; color: #64748b;">
                <i class="ri-arrow-left-line"></i> Cancel
            </button>
        </div>
    </div>

    <!-- Unified Dashboard Row -->
    <div style="display: flex; gap: 0.5rem; align-items: stretch;  flex-wrap: wrap;">

        <!-- Filters Card -->
        <div
            style="background: white; border: 1px solid #e2e8f0; padding: 0.5rem 0.75rem; border-radius: 10px; flex: 2; min-width: 300px; display: flex; flex-direction: column; justify-content: center;">
            <form method="GET" style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="hidden" name="id" value="<?= $id ?>">
                <div style="display: flex; flex-direction: column; gap: 1px; flex: 1;">
                    <span
                        style="font-size: 0.6rem; color: #94a3b8; font-weight: 700; text-transform: uppercase;">From</span>
                    <input type="date" <?= $salaryPaid ? 'disabled' : '' ?> name="from_date" value="<?= $from_date ?>"
                        style="border: 1px solid #e2e8f0; border-radius: 4px; padding: 2px 6px; font-size: 0.8rem; outline: none; width: 100%;">
                </div>
                <div style="display: flex; flex-direction: column; gap: 1px; flex: 1;">
                    <span
                        style="font-size: 0.6rem; color: #94a3b8; font-weight: 700; text-transform: uppercase;">To</span>
                    <input type="date" <?= $salaryPaid ? 'disabled' : '' ?> name="to_date" value="<?= $to_date ?>"
                        style="border: 1px solid #e2e8f0; border-radius: 4px; padding: 2px 6px; font-size: 0.8rem; outline: none; width: 100%;">
                </div>
                <button type="submit" <?= $salaryPaid ? 'disabled' : '' ?>
                    style="background: #4338ca; color: white; border: none; border-radius: 4px; padding: 10px 8px; cursor: pointer; display: flex; align-items: center; justify-content: center;"
                    title="Update">
                    <i class="ri-refresh-line" style="font-size: 1rem;"></i>
                </button>
            </form>
        </div>

        <!-- Attendance Card -->
        <div
            style="background: white; border: 1px solid #e2e8f0; padding: 0.5rem 0.75rem; border-radius: 10px; border-left: 3px solid #4338ca; flex: 1; min-width: 140px; display: flex; flex-direction: column; justify-content: center;">
            <div
                style="font-size: 0.6rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">
                Attendance</div>
            <div style="display: flex; gap: 0.5rem;">
                <div>
                    <div style="font-size: 1rem; font-weight: 800; color: #1e293b;"><?= $present_days ?></div>
                    <div style="font-size: 0.55rem; color: #059669; font-weight: 600;">Present</div>
                </div>
                <div style="border-left: 1px solid #e2e8f0; padding-left: 0.5rem;">
                    <div style="font-size: 1rem; font-weight: 800; color: #1e293b;"><?= $half_days ?></div>
                    <div style="font-size: 0.55rem; color: #d97706; font-weight: 600;">Half</div>
                </div>
            </div>
        </div>

        <!-- Overtime Card -->
        <div
            style="background: white; border: 1px solid #e2e8f0; padding: 0.5rem 0.75rem; border-radius: 10px; border-left: 3px solid #059669; flex: 1; min-width: 120px; display: flex; flex-direction: column; justify-content: center;">
            <div
                style="font-size: 0.6rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">
                Overtime</div>
            <div style="font-size: 1rem; font-weight: 800; color: #1e293b;">₹ <?= number_format($pending_ot, 0) ?></div>
        </div>

        <!-- Advance Card -->
        <div
            style="background: white; border: 1px solid #e2e8f0; padding: 0.5rem 0.75rem; border-radius: 10px; border-left: 3px solid #ef4444; flex: 1; min-width: 120px; display: flex; flex-direction: column; justify-content: center;">
            <div
                style="font-size: 0.6rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">
                Advance</div>
            <div style="font-size: 1rem; font-weight: 800; color: #ef4444;">₹
                <?= number_format($outstanding_advance, 0) ?>
            </div>
        </div>

    </div>

    <form method="POST" style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
        <input type="hidden" name="base_salary" id="base_salary" value="<?= $attendance_pay ?>">

        <!-- Left Column: Payment Calculations -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">

            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Salary Breakdown
                </h3>

                <div style="margin-bottom: 1.5rem;">
                    <div
                        style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; color: #64748b; font-size: 0.95rem;">
                        <span>Attendance Salary (Calculated)</span>
                        <span style="font-weight: 600; color: #1e293b;">₹
                            <?= number_format($attendance_pay, 2) ?></span>
                    </div>
                    <div style="font-size: 0.7rem; color: #94a3b8;">Based on <?= $present_days ?> full days and
                        <?= $half_days ?> half days.
                    </div>
                </div>

                <div style="border-top: 1px dashed #cbd5e1; margin-bottom: 1.5rem;"></div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Add Overtime Amount (Optional)</label>
                    <div style="position: relative;">
                        <span
                            style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #64748b;">₹</span>
                        <input type="number" step="0.01" name="overtime" <?= $salaryPaid ? 'disabled' : '' ?> id="overtime" class="form-control"
                            style="padding-left: 2rem;" placeholder="0.00"
                            value="<?= $pending_ot > 0 ? $pending_ot : '' ?>" oninput="calculateNet()">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Add Bonus / Incentive (Optional)</label>
                    <div style="position: relative;">
                        <span
                            style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #64748b;">₹</span>
                        <input type="number" step="0.01" name="bonus" <?= $salaryPaid ? 'disabled' : '' ?> id="bonus"
                            class="form-control" style="padding-left: 2rem;" placeholder="0.00"
                            oninput="calculateNet()">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Lates / Fines</label>
                        <div style="position: relative;">
                            <span
                                style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #64748b;">₹</span>
                            <input type="number" step="0.01" name="lates" <?= $salaryPaid ? 'disabled' : '' ?> id="lates" class="form-control"
                                style="padding-left: 2rem;" placeholder="0.00" oninput="calculateNet()">
                        </div>
                    </div>

                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <label class="form-label">Advance Repayment</label>
                            <span style="font-size: 0.85rem; color: #dc2626; font-weight: 700;">Due:
                                ₹<?= number_format($outstanding_advance, 2) ?></span>
                        </div>
                        <div style="position: relative;">
                            <span
                                style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #64748b;">₹</span>
                            <input type="number" step="0.01" name="advance_repay" <?= $salaryPaid ? 'disabled' : '' ?> id="advance_repay"
                                class="form-control" style="padding-left: 2rem;" placeholder="0.00"
                                oninput="calculateNet()">
                        </div>
                    </div>
                </div>
                <input type="hidden" name="deductions" id="deductions" value="0">

            </div>

            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Payment Method
                </h3>

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                    <label class="payment-method-card selected">
                        <input type="radio" <?= $salaryPaid ? 'disabled' : '' ?> name="method" value="Bank Transfer" checked>
                        <i class="ri-bank-card-line"></i>
                        <span>Bank Transfer</span>
                    </label>
                    <label class="payment-method-card">
                        <input type="radio" <?= $salaryPaid ? 'disabled' : '' ?> name="method" value="Cash">
                        <i class="ri-hand-coin-line"></i>
                        <span>Cash</span>
                    </label>
                    <label class="payment-method-card">
                        <input type="radio" <?= $salaryPaid ? 'disabled' : '' ?> name="method" value="UPI / GPay">
                        <i class="ri-qr-code-line"></i>
                        <span>UPI / GPay</span>
                    </label>
                </div>

                <div class="form-group" style="margin-top: 1.5rem;">
                    <label class="form-label">Transaction Reference / Notes</label>
                    <input type="text" name="notes" <?= $salaryPaid ? 'disabled' : '' ?> class="form-control" placeholder="e.g. UPI Ref: 1234567890">
                </div>
            </div>

        </div>

        <!-- Right Column: Final Summary -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">

            <div
                style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px; border-top: 4px solid #10b981;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Final Payout</h3>

                <div style="display:flex;justify-content:space-between;margin-bottom:0.5rem;color:#64748b;">
                    <span>Gross Total</span>

                    <span id="gross_total">
                        ₹ <?= number_format($attendance_pay + $pending_ot, 2) ?>
                    </span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 1.5rem; color: #ef4444;">
                    <span>Total Deductions</span>
                    <span id="total_deductions">- ₹ 0.00</span>
                </div>

                <div
                    style="background: #f0fdf4; padding: 1.5rem; border-radius: 8px; text-align: center; margin-bottom: 1.5rem; border: 1px solid #bbf7d0;">
                    <div style="font-size: 0.9rem; color: #166534; margin-bottom: 0.5rem;">Net Payable Amount</div>
                    <div id="net_payable" style="font-size: 2rem; font-weight: 700; color: #15803d;">₹
                        <?= number_format($attendance_pay + $pending_ot, 2) ?>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label"
                        style="font-size: 0.95rem; color: #1e293b; margin-bottom: 0.5rem; display: block;">Amount Paying
                        Now</label>
                    <div style="position: relative;">
                        <span
                            style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #64748b; font-weight: bold;">₹</span>
                        <input type="number" step="0.01" name="paying_now" id="paying_now" class="form-control"
                            style="padding-left: 2rem; font-size: 1.25rem; font-weight: 700; color: #1e293b;"
                            value="<?= number_format($attendance_pay + $pending_ot, 2, '.', '') ?>">
                    </div>
                </div>

                <?php if ($salaryPaid): ?>

                    <button type="button" disabled class="btn"
                        style="width:100%;background:#22c55e;color:#fff;cursor:not-allowed;">
                        Salary Already Paid
                    </button>

                    <div
                        style="margin-top:12px;padding:12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;font-size:14px;color:#475569;line-height:1.8;">

                        <strong>Paid Amount :</strong>
                        ₹ <?= number_format($salaryPaid['amount'], 2) ?>

                        <br>

                        <strong>Paid On :</strong>
                        <?= date('d-m-Y', strtotime($salaryPaid['payment_date'])) ?>

                    </div>

                <?php else: ?>

                    <button type="submit" class="btn btn-primary w-full"
                        style="justify-content:center;width:100%;margin-bottom:1rem;padding:1rem;font-size:1rem;">
                        Confirm Payment
                    </button>

                <?php endif; ?>
                <button type="button" onclick="history.back()" class="btn w-full"
                    style="justify-content: center; width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b;">Cancel</button>

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
        font-weight: 500;
        color: #334155;
    }

    .form-control,
    .form-select {
        padding: 0.75rem;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 0.95rem;
        width: 100%;
        outline: none;
        transition: border-color 0.2s;
        font-family: inherit;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--primary);
    }

    .payment-method-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
        padding: 1rem;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        color: #64748b;
    }

    .payment-method-card i {
        font-size: 1.5rem;
    }

    .payment-method-card span {
        font-size: 0.85rem;
        font-weight: 500;
    }

    .payment-method-card input {
        display: none;
    }

    .payment-method-card:hover {
        background: #f8fafc;
        border-color: var(--primary);
        color: var(--primary);
    }

    .payment-method-card:has(input:checked),
    .payment-method-card.selected {
        background: #eef2ff;
        border-color: var(--primary);
        color: var(--primary);
        font-weight: 600;
    }
</style>

<script>
    // Simple script to toggle selected class for radio buttons styling
    const methods = document.querySelectorAll('.payment-method-card');
    methods.forEach(method => {
        method.addEventListener('click', () => {
            methods.forEach(m => m.classList.remove('selected'));
            method.classList.add('selected');
        });
    });

    function calculateNet() {
        const base = parseFloat(document.getElementById('base_salary').value) || 0;
        const ot = parseFloat(document.getElementById('overtime').value) || 0;
        const bonus = parseFloat(document.getElementById('bonus').value) || 0;
        const lates = parseFloat(document.getElementById('lates').value) || 0;
        const adv_repay = parseFloat(document.getElementById('advance_repay').value) || 0;

        const deductions = lates + adv_repay;
        document.getElementById('deductions').value = deductions;

        const gross = base + ot + bonus;
        const net = gross - deductions;

        document.getElementById('gross_total').innerText = '₹ ' + gross.toFixed(2);
        document.getElementById('total_deductions').innerText = '- ₹ ' + deductions.toFixed(2);
        document.getElementById('net_payable').innerText = '₹ ' + net.toFixed(2);
        document.getElementById('paying_now').value = net.toFixed(2);
    }

    // Run once on load to populate accurate net if overtime is pre-filled
    <?php if (!$salaryPaid): ?>
        window.addEventListener('DOMContentLoaded', calculateNet);
    <?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>