<?php
session_start();
include '../includes/db.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: payroll.php");
    exit;
}

$stmt = $pdo->prepare("SELECT e.*, j.role_name FROM employees e LEFT JOIN job_roles j ON e.job_role = j.id WHERE e.id = ? AND e.is_deleted=0");
$stmt->execute([$id]);
$employee = $stmt->fetch();
if (!$employee) {
    header("Location: payroll.php");
    exit;
}
$is_outsource = ($employee['employee_type'] === 'outsource');

$stmt = $pdo->prepare("SELECT * FROM employee_payments WHERE employee_id = ? ORDER BY payment_date DESC, id DESC");
$stmt->execute([$id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
$outsourceEarned = 0;
$outsourcePaid = 0;
$outsourcePending = 0;

if ($is_outsource) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(outsource_credit),0)
        FROM outsource_orders
        WHERE assigned_employee_id = ?
        AND order_status = 'completed'
        AND is_deleted = 0
    ");
    $stmt->execute([$id]);
    $outsourceEarned = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount),0)
    FROM employee_payments
    WHERE employee_id = ?
      AND payment_type = 'Outsource Payment'
      AND LOWER(status) = 'paid'
");
    $stmt->execute([$id]);
    $outsourcePaid = $stmt->fetchColumn();

    $outsourcePending = max($outsourceEarned - $outsourcePaid, 0);
}

$overtimes = [];

if (!$is_outsource) {
    $stmt = $pdo->prepare("
        SELECT * 
        FROM employee_overtime 
        WHERE employee_id = ? 
        ORDER BY ot_date DESC, id DESC
    ");
    $stmt->execute([$id]);
    $overtimes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate totals
$totalSalary = 0;
$totalOT = 0;
$totalBonus = 0;
$totalDeductions = 0;
$totalUnpaidOT = 0;

foreach ($payments as $p) {

    if ($p['payment_type'] == 'Salary') {
        $totalSalary += $p['amount'];
    }

    if ($p['payment_type'] == 'Outsource Payment') {
        $totalSalary += $p['amount'];
    }

    if ($p['payment_type'] == 'Overtime') {
        $totalOT += $p['amount'];
    }

    if ($p['payment_type'] == 'Bonus') {
        $totalBonus += $p['amount'];
    }

    // Count only actual deductions
    if (
        ($p['payment_type'] == 'Advance' && strtolower($p['status']) == 'deducted') ||
        $p['payment_type'] == 'Fine'
    ) {
        $totalDeductions += abs($p['amount']);
    }

}

foreach ($overtimes as $ot) {
    if ($ot['status'] == 'Pending') {
        $totalUnpaidOT += $ot['amount'];
    }
}

$netEarnings = $totalSalary + $totalOT + $totalBonus;

$pageTitle = "Payroll History - Sogasu";
$activePage = "payroll";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="display: flex; flex-direction: column; gap: 1.5rem; width: 100%; max-width: 100%;">

        <!-- Premium Header Area -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div class="premium-avatar"
                    style="width: 52px; height: 52px; font-size: 1.35rem; border-radius: 50%; background: #e0e7ff; color: #4338ca; font-weight: 800; display: flex; align-items: center; justify-content: center; box-shadow: var(--shadow-sm);">
                    <?= strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1)) ?>
                </div>
                <div>
                    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                        <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">
                            <?= htmlspecialchars(trim($employee['first_name'] . ' ' . $employee['last_name'])) ?>
                        </h2>
                        <span
                            style="background: #eef2ff; color: #4338ca; padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;"><?= htmlspecialchars($employee['role_name'] ?? $employee['job_role'] ?? 'Employee') ?></span>
                    </div>
                    <p style="color: #64748b; margin-top: 0.25rem; margin-bottom: 0;">Employee payment history and
                        transaction reports</p>
                </div>
            </div>
            <a href="payroll.php" class="btn btn-secondary"
                style="background: white; border: 1px solid #cbd5e1; color: #475569; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s;">
                <i class="ri-arrow-left-line"></i> Back to Payroll
            </a>
        </div>

        <!-- Main Content Row -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start;">

            <!-- Left Column: Transactions & Logs -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">

                <!-- Payment Transactions Card -->
                <div class="table-container" style="padding: 1.5rem; margin-top: 0;">
                    <div
                        style="padding-bottom: 1.25rem; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
                        <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-dark); margin: 0;">Payment
                            Transactions</h3>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="table" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Type</th>
                                    <th style="text-align: right;">Amount</th>
                                    <th style="text-align: right;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($payments) > 0): ?>
                                    <?php foreach ($payments as $pay): ?>
                                        <tr>
                                            <td style="font-weight: 600; color: var(--text-dark);">
                                                <?= date('d M, Y', strtotime($pay['payment_date'])) ?>
                                            </td>
                                            <td style="font-weight: 700; color: var(--text-dark);">
                                                <?= htmlspecialchars($pay['description']) ?>
                                            </td>
                                            <td>
                                                <span
                                                    style="font-size: 0.75rem; font-weight: 600; color: var(--text-muted); background: #f8fafc; padding: 2px 8px; border-radius: 4px; border: 1px solid #f1f5f9;"><?= htmlspecialchars($pay['payment_type']) ?></span>
                                            </td>
                                            <td
                                                style="text-align: right; font-weight: 800; <?= strtolower($pay['status']) == 'deducted' || $pay['amount'] < 0 ? 'color: var(--danger);' : 'color: var(--text-dark);' ?>">
                                                <?= strtolower($pay['status']) == 'deducted' || $pay['amount'] < 0 ? '- ' : '' ?>₹
                                                <?= number_format(abs($pay['amount']), 2) ?>
                                            </td>
                                            <td style="text-align: right;">
                                                <?php if (strtolower($pay['status']) == 'paid'): ?>
                                                    <span class="premium-badge badge-active">Paid</span>
                                                <?php elseif (strtolower($pay['status']) == 'deducted'): ?>
                                                    <span class="premium-badge badge-delayed">Deducted</span>
                                                <?php else: ?>
                                                    <span
                                                        class="premium-badge badge-pending"><?= htmlspecialchars($pay['status']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5"
                                            style="padding: 2.5rem; text-align: center; color: var(--text-muted); font-weight: 500;">
                                            No payment history found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if (!$is_outsource): ?>
                    <!-- Overtime History Card -->
                    <div class="table-container" style="padding: 1.5rem; margin-top: 0;">
                        <div
                            style="padding-bottom: 1.25rem; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
                            <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-dark); margin: 0;">Logged
                                Overtime</h3>
                        </div>

                        <div style="overflow-x: auto;">
                            <table class="table" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th style="text-align: center;">Hours</th>
                                        <th style="text-align: right;">Amount</th>
                                        <th style="text-align: right;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($overtimes) > 0): ?>
                                        <?php foreach ($overtimes as $ot): ?>
                                            <tr>
                                                <td style="font-weight: 600; color: var(--text-dark);">
                                                    <?= date('d M, Y', strtotime($ot['ot_date'])) ?>
                                                </td>
                                                <td style="font-weight: 700; color: var(--text-dark);">
                                                    <?= htmlspecialchars($ot['description'] ?: 'Overtime Work') ?>
                                                </td>
                                                <td style="text-align: center; font-weight: 600; color: var(--text-muted);">
                                                    <?= number_format($ot['hours'], 1) ?>h @ <?= $ot['multiplier'] ?>x
                                                </td>
                                                <td style="text-align: right; font-weight: 800; color: var(--success);">
                                                    ₹ <?= number_format($ot['amount'], 2) ?>
                                                </td>
                                                <td style="text-align: right;">
                                                    <?php if ($ot['status'] == 'Paid'): ?>
                                                        <span class="premium-badge badge-active">Paid</span>
                                                    <?php elseif ($ot['status'] == 'Approved'): ?>
                                                        <span class="premium-badge badge-delivered">Approved</span>
                                                    <?php else: ?>
                                                        <span
                                                            class="premium-badge badge-pending"><?= htmlspecialchars($ot['status']) ?></span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5"
                                                style="padding: 2.5rem; text-align: center; color: var(--text-muted); font-weight: 500;">
                                                No overtime logs found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right Column: Summary Stats & Performance -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">

                <!-- Payment Summary Card -->
                <div class="glass-card" style="padding: 1.5rem;">
                    <h3
                        style="font-size: 1.1rem; font-weight: 700; color: var(--text-dark); margin-bottom: 1.25rem; margin-top: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="ri-pie-chart-line" style="color: var(--primary);"></i>
                        <?= $is_outsource ? 'Outsource Earnings Summary' : 'Summary (' . date('Y') . ')' ?>
                    </h3>

                    <div style="display: flex; flex-direction: column; gap: 0.85rem;">

                        <?php if ($is_outsource): ?>

                            <div style="display:flex; justify-content:space-between;">
                                <span style="color: var(--text-muted);">Total Earned</span>
                                <span style="font-weight:700;">₹ <?= number_format($outsourceEarned, 2) ?></span>
                            </div>

                            <div style="display:flex; justify-content:space-between;">
                                <span style="color: var(--text-muted);">Total Paid</span>
                                <span style="font-weight:700;">₹ <?= number_format($outsourcePaid, 2) ?></span>
                            </div>

                            <div style="display:flex; justify-content:space-between;">
                                <span style="color: var(--text-muted);">Pending Amount</span>
                                <span style="font-weight:700; color:#dc2626;">
                                    ₹ <?= number_format($outsourcePending, 2) ?>
                                </span>
                            </div>

                        <?php else: ?>

                            <div
                                style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem;">
                                <span style="color: var(--text-muted); font-weight: 500;">Total Salary Paid</span>
                                <span style="font-weight: 700; color: var(--text-dark);">₹
                                    <?= number_format($totalSalary, 2) ?></span>
                            </div>

                            <div
                                style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem;">
                                <span style="color: var(--text-muted); font-weight: 500;">Total OT Paid</span>
                                <span style="font-weight: 700; color: var(--text-dark);">₹
                                    <?= number_format($totalOT, 2) ?></span>
                            </div>

                            <div
                                style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem;">
                                <span style="color: var(--text-muted); font-weight: 500;">Bonuses / Incentives</span>
                                <span style="font-weight: 700; color: var(--success);">₹
                                    <?= number_format($totalBonus, 2) ?></span>
                            </div>

                            <div
                                style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem;">
                                <span style="color: var(--text-muted); font-weight: 500;">Deductions / Advance</span>
                                <span style="font-weight: 700; color: var(--danger);">- ₹
                                    <?= number_format($totalDeductions, 2) ?></span>
                            </div>

                            <div style="border-top: 1px solid #f1f5f9; margin: 0.5rem 0;"></div>

                            <div
                                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <span style="font-weight: 700; color: var(--text-dark); font-size: 0.9rem;">Net Total
                                    Paid</span>
                                <span style="font-weight: 800; color: var(--primary); font-size: 1.3rem;">
                                    ₹ <?= number_format($netEarnings, 2) ?>
                                </span>
                            </div>

                        <?php endif; ?>

                    </div>
                </div>
                <?php if (!$is_outsource): ?>
                    <!-- Performance Card -->
                    <div class="glass-card" style="padding: 1.5rem;">
                        <h3
                            style="font-size: 1.1rem; font-weight: 700; color: var(--text-dark); margin-bottom: 1.25rem; margin-top: 0; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="ri-line-chart-line" style="color: var(--primary);"></i> Performance
                        </h3>

                        <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                            <div class="form-group" style="gap: 0.35rem;">
                                <div
                                    style="display: flex; justify-content: space-between; font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
                                    <span>Attendance Rate</span>
                                    <span style="color: var(--success);">98%</span>
                                </div>
                                <div style="height: 6px; background: #eef2ff; border-radius: 20px; overflow: hidden;">
                                    <div style="height: 100%; background: var(--success); width: 98%; border-radius: 20px;">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group" style="gap: 0.35rem;">
                                <div
                                    style="display: flex; justify-content: space-between; font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
                                    <span>On-Time Delivery</span>
                                    <span style="color: var(--primary);">92%</span>
                                </div>
                                <div style="height: 6px; background: #eef2ff; border-radius: 20px; overflow: hidden;">
                                    <div style="height: 100%; background: var(--primary); width: 92%; border-radius: 20px;">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

        </div>
    </div>
</main>

<style>
    .form-group {
        display: flex;
        flex-direction: column;
    }
</style>

<?php include 'includes/footer.php'; ?>