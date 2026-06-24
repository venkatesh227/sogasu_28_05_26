<?php
session_start();
include '../includes/db.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: payroll.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND is_deleted = 0");
$stmt->execute([$id]);
$employee = $stmt->fetch();

if (!$employee) {
    header("Location: payroll.php");
    exit;
}

/* Date Filter */
$from_date = $_GET['from_date'] ?? date('Y-m-d', strtotime('monday this week'));
$to_date = $_GET['to_date'] ?? date('Y-m-d');

/* Completed Outsource Orders within selected period */
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) AS completed_orders,
        COALESCE(SUM(outsource_credit), 0) AS total_earned
    FROM outsource_orders
    WHERE assigned_employee_id = ?
    AND order_status = 'completed'
    AND is_deleted = 0
    AND DATE(updated_at) BETWEEN ? AND ?
");
$stmt->execute([$id, $from_date, $to_date]);
$outsource_data = $stmt->fetch();

$completed_orders = $outsource_data['completed_orders'] ?? 0;
$total_earned = $outsource_data['total_earned'] ?? 0;

/* Total Paid to Outsource Employee */
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount),0)
    FROM employee_payments
    WHERE employee_id = ?
    AND payment_type = 'Outsource Payment'
    AND status = 'Paid'
");
$stmt->execute([$id]);
$total_paid = $stmt->fetchColumn();

$pending_amount = max($total_earned - $total_paid, 0);

/* Outstanding Advance */
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount),0)
    FROM employee_payments
    WHERE employee_id = ?
    AND payment_type = 'Advance'
    AND status = 'Paid'
");
$stmt->execute([$id]);
$adv_given = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount),0)
    FROM employee_payments
    WHERE employee_id = ?
    AND payment_type = 'Advance'
    AND status = 'Deducted'
");
$stmt->execute([$id]);
$adv_repaid = $stmt->fetchColumn();

$outstanding_advance = $adv_given - $adv_repaid;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $advance_repay = floatval($_POST['advance_repay'] ?? 0);
    $deductions = $advance_repay;

    $notes = $_POST['notes'] ?? '';
    $method = $_POST['method'] ?? 'Bank Transfer';

    $payment_date = date('Y-m-d');
    $paying_now = floatval($_POST['paying_now'] ?? 0);
    if ($paying_now > $pending_amount) {
        die("Cannot pay more than pending amount");
    }

    // Insert Outsource Payment
    $salary_to_log = $paying_now;

    if ($salary_to_log > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO employee_payments 
            (employee_id, payment_date, description, payment_type, amount, status) 
            VALUES (?, ?, ?, 'Outsource Payment', ?, 'Paid')
        ");

        $stmt->execute([
            $id,
            $payment_date,
            'Outsource Payment (' . $method . ')' . ($notes ? " - $notes" : ""),
            $salary_to_log
        ]);
    }
    // Advance Recovery
    if ($advance_repay > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO employee_payments 
            (employee_id, payment_date, description, payment_type, amount, status) 
            VALUES (?, ?, ?, 'Advance', ?, 'Deducted')
        ");

        $stmt->execute([
            $id,
            $payment_date,
            'Advance Repayment',
            $advance_repay
        ]);
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
                <input type="hidden" id="gross_total_hidden" value="<?= $pending_amount ?>">
                <input type="hidden" name="id" value="<?= $id ?>">
                <div style="display: flex; flex-direction: column; gap: 1px; flex: 1;">
                    <span
                        style="font-size: 0.6rem; color: #94a3b8; font-weight: 700; text-transform: uppercase;">From</span>
                    <input type="date" name="from_date" value="<?= $from_date ?>"
                        style="border: 1px solid #e2e8f0; border-radius: 4px; padding: 2px 6px; font-size: 0.8rem; outline: none; width: 100%;">
                </div>
                <div style="display: flex; flex-direction: column; gap: 1px; flex: 1;">
                    <span
                        style="font-size: 0.6rem; color: #94a3b8; font-weight: 700; text-transform: uppercase;">To</span>
                    <input type="date" name="to_date" value="<?= $to_date ?>"
                        style="border: 1px solid #e2e8f0; border-radius: 4px; padding: 2px 6px; font-size: 0.8rem; outline: none; width: 100%;">
                </div>
                <button type="submit"
                    style="background: #4338ca; color: white; border: none; border-radius: 4px; padding: 10px 8px; cursor: pointer; display: flex; align-items: center; justify-content: center;"
                    title="Update">
                    <i class="ri-refresh-line" style="font-size: 1rem;"></i>
                </button>
            </form>
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
    <div style="display:flex; gap:1rem; margin:1rem 0; flex-wrap:wrap;">
        <div class="table-container" style="padding:1rem; min-width:180px;">
            <small>Total Earned</small>
            <h3>₹<?= number_format($total_earned, 2) ?></h3>
        </div>

        <div class="table-container" style="padding:1rem; min-width:180px;">
            <small>Total Paid</small>
            <h3>₹<?= number_format($total_paid, 2) ?></h3>
        </div>

        <div class="table-container" style="padding:1rem; min-width:180px;">
            <small>Pending</small>
            <h3>₹<?= number_format($pending_amount, 2) ?></h3>
        </div>

        <div class="table-container" style="padding:1rem; min-width:180px;">
            <small>Completed Orders</small>
            <h3><?= $completed_orders ?></h3>
        </div>
    </div>

    <form method="POST" style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
        <!-- Left Column: Payment Calculations -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">

            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">
                    Payout Adjustments
                </h3>

                <div style="border-top: 1px dashed #cbd5e1; margin-bottom: 1.5rem;"></div>

                <div style="display: grid; grid-template-columns: 1fr; gap: 1rem;">
                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <label class="form-label">Advance Repayment</label>
                            <span style="font-size: 0.85rem; color: #dc2626; font-weight: 700;">Due:
                                ₹<?= number_format($outstanding_advance, 2) ?></span>
                        </div>
                        <div style="position: relative;">
                            <span
                                style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #64748b;">₹</span>
                            <input type="number" step="0.01" name="advance_repay" id="advance_repay"
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
                        <input type="radio" name="method" value="Bank Transfer" checked>
                        <i class="ri-bank-card-line"></i>
                        <span>Bank Transfer</span>
                    </label>
                    <label class="payment-method-card">
                        <input type="radio" name="method" value="Cash">
                        <i class="ri-hand-coin-line"></i>
                        <span>Cash</span>
                    </label>
                    <label class="payment-method-card">
                        <input type="radio" name="method" value="UPI / GPay">
                        <i class="ri-qr-code-line"></i>
                        <span>UPI / GPay</span>
                    </label>
                </div>

                <div class="form-group" style="margin-top: 1.5rem;">
                    <label class="form-label">Transaction Reference / Notes</label>
                    <input type="text" name="notes" class="form-control" placeholder="e.g. UPI Ref: 1234567890">
                </div>
            </div>

        </div>

        <!-- Right Column: Final Summary -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">

            <div
                style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px; border-top: 4px solid #10b981;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Final Payout</h3>

                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; color: #64748b;">
                    <span>Gross Total</span>
                    <span id="gross_total">₹ <?= number_format($pending_amount, 2) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 1.5rem; color: #ef4444;">
                    <span>Total Deductions</span>
                    <span id="total_deductions">- ₹ 0.00</span>
                </div>

                <div
                    style="background: #f0fdf4; padding: 1.5rem; border-radius: 8px; text-align: center; margin-bottom: 1.5rem; border: 1px solid #bbf7d0;">
                    <div style="font-size: 0.9rem; color: #166534; margin-bottom: 0.5rem;">Net Payable Amount</div>
                    <div id="net_payable" style="font-size: 2rem; font-weight: 700; color: #15803d;">₹
                        <?= number_format($pending_amount, 2) ?>
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
                            value="<?= $pending_amount ?>">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-full"
                    style="justify-content: center; width: 100%; margin-bottom: 1rem; padding: 1rem; font-size: 1rem;">
                    Confirm Payment <i class="ri-arrow-right-line" style="margin-left: 0.5rem;"></i>
                </button>
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
        const base = parseFloat(document.getElementById('gross_total_hidden').value) || 0;
        const adv_repay = parseFloat(document.getElementById('advance_repay').value) || 0;

        const deductions = adv_repay;
        document.getElementById('deductions').value = deductions;

        const gross = base;
        const net = Math.max(gross - deductions, 0);

        document.getElementById('gross_total').innerText = '₹ ' + gross.toFixed(2);
        document.getElementById('total_deductions').innerText = '- ₹ ' + deductions.toFixed(2);
        document.getElementById('net_payable').innerText = '₹ ' + net.toFixed(2);
        document.getElementById('paying_now').value = net.toFixed(2);
    }

    // Run once on load to populate accurate net if overtime is pre-filled
    window.addEventListener('DOMContentLoaded', calculateNet);
</script>

<?php include 'includes/footer.php'; ?>