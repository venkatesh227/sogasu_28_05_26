<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/db.php';
require_once '../admin/attendance_calculator.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$language = $_SESSION['language'] ?? 'en';

// Include translations
require_once __DIR__ . '/includes/translations.php';
$t = $translations[$language] ?? $translations['en'];

// Fetch employee data
$stmt = $pdo->prepare("SELECT id, preferred_language FROM employees WHERE user_id = ? AND is_deleted = 0");
$stmt->execute([$user_id]);
$emp = $stmt->fetch();

if (!$emp) {
    die("Employee record not found.");
}

$employee_id = $emp['id'];
$stmt = $pdo->prepare("
    SELECT pay_cycle
    FROM employees
    WHERE id = ?
");
$stmt->execute([$employee_id]);
$payCycle = $stmt->fetchColumn();
if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = $emp['preferred_language'] ?? 'en';
    $language = $_SESSION['language'];
    $t = $translations[$language] ?? $translations['en'];
}

// 1. Fetch Payments (Salary, Advance Deductions, etc.)                   
$stmt = $pdo->prepare("SELECT id, payment_date as date, description, payment_type as type, amount, status, 'payment' as source FROM employee_payments WHERE employee_id = ? ORDER BY payment_date DESC LIMIT 20");
$stmt->execute([$employee_id]);
$payments = $stmt->fetchAll();

// 2. Fetch Overtime
$stmt = $pdo->prepare("SELECT id, ot_date as date, description, 'OT' as type, amount, status, 'ot' as source FROM employee_overtime WHERE employee_id = ? ORDER BY ot_date DESC LIMIT 20");
$stmt->execute([$employee_id]);
$overtimes = $stmt->fetchAll();

// Merge and sort by date                      
$history = $payments;
usort($history, function ($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Show only current month activity                               
$currentMonth = date('Y-m');
$history = array_filter($history, function ($item) use ($currentMonth) {
    return strpos($item['date'], $currentMonth) === 0;
});

$history = array_values($history);

// 3. Calculate payroll-based earnings for the current period
$totalEarned = 0;
$pendingAmount = 0;

$stmt = $pdo->prepare("SELECT pay_cycle, base_salary, default_shift_id FROM employees WHERE id = ?");
$stmt->execute([$employee_id]);
$payrollEmployee = $stmt->fetch(PDO::FETCH_ASSOC);
$payCycle = $payrollEmployee['pay_cycle'] ?? '';
$baseSalary = (float) ($payrollEmployee['base_salary'] ?? 0);
$defaultShiftId = $payrollEmployee['default_shift_id'] ?? null;

if (stripos($payCycle, 'Weekly') !== false) {
    $from_date = date('Y-m-d', strtotime('monday this week'));
    $to_date = date('Y-m-d', strtotime('sunday this week'));
} elseif (stripos($payCycle, 'Daily') !== false) {
    $from_date = date('Y-m-d');
    $to_date = date('Y-m-d');
} else {
    $from_date = date('Y-m-01');
    $to_date = date('Y-m-t');
}

$attendanceSummary = calculateAttendanceSummary($pdo, $from_date, $to_date);

$payCycleKey = strtolower(trim($payCycle));

switch ($payCycleKey) {
    case 'daily':
        $salary_type = 'daily';
        $per_day = $baseSalary;
        break;

    case 'weekly (saturday)':
        $salary_type = 'weekly';
        $per_day = $baseSalary / 7;
        break;

    case 'bi-weekly':
        $salary_type = 'biweekly';
        $per_day = $baseSalary / 14;
        break;

    case 'monthly (1st)':
        $salary_type = 'monthly';
        $per_day = $baseSalary / date('t', strtotime($from_date));
        break;

    default:
        $salary_type = 'monthly';
        $per_day = $baseSalary / date('t', strtotime($from_date));
}

$present_days = 0;
$half_days = 0;
$late_days = 0;

$period = new DatePeriod(
    new DateTime($from_date),
    new DateInterval('P1D'),
    (new DateTime($to_date))->modify('+1 day')
);

foreach ($period as $day) {
    $dayNo = (int) $day->format('j');
    $status = $attendanceSummary[$employee_id][$dayNo] ?? null;

    if ($status === null) {
        continue;
    }

    switch ($status) {
        case 'Present':
            $present_days++;
            break;

        case 'Late':
            $late_days++;
            break;

        case 'Half Day':
            $half_days++;
            break;
    }
}

$paid_present_days = $present_days + $late_days;

$approved_ot_stmt = $pdo->prepare("SELECT COALESCE(SUM(ot_minutes),0) FROM attendance WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?");
$approved_ot_stmt->execute([$employee_id, $from_date, $to_date]);
$approved_ot_minutes = intval($approved_ot_stmt->fetchColumn());
$approved_ot_hours = $approved_ot_minutes / 60;

$working_hours = 8;

$shiftTypeStmt = $pdo->prepare("SELECT COALESCE((SELECT sr.shift_type_id FROM shift_roster sr WHERE sr.employee_id = ? AND sr.roster_date <= ? ORDER BY sr.roster_date DESC LIMIT 1), ?) AS shift_type_id");
$shiftTypeStmt->execute([$employee_id, $to_date, $defaultShiftId]);
$shift_type_id = $shiftTypeStmt->fetchColumn();

if (!empty($shift_type_id)) {
    $shiftStmt = $pdo->prepare("SELECT start_time, end_time FROM shift_types WHERE id = ? LIMIT 1");
    $shiftStmt->execute([$shift_type_id]);
    $shift = $shiftStmt->fetch(PDO::FETCH_ASSOC);

    if ($shift) {
        $start = strtotime($shift['start_time']);
        $end = strtotime($shift['end_time']);

        if ($end < $start) {
            $end += 86400;
        }

        $working_hours = ($end - $start) / 3600;

        if ($working_hours <= 0) {
            $working_hours = 8;
        }
    }
}

switch ($salary_type) {
    case 'daily':
        $hourly_rate = $baseSalary / $working_hours;
        break;

    case 'weekly':
        $hourly_rate = ($baseSalary / 7) / $working_hours;
        break;

    case 'biweekly':
        $hourly_rate = ($baseSalary / 14) / $working_hours;
        break;

    case 'monthly':
        $daysInMonth = date('t', strtotime($from_date));
        $hourly_rate = ($baseSalary / $daysInMonth) / $working_hours;
        break;

    default:
        $hourly_rate = ($baseSalary / 30) / $working_hours;
        break;
}

$otStmt = $pdo->query("SELECT ot_percentage FROM ot_rate_settings ORDER BY id DESC LIMIT 1");
$otRate = (float) $otStmt->fetchColumn();
$otRate = floatval($otRate);
$bonus_per_hour = ($hourly_rate * $otRate) / 100;
$ot_rate_per_hour = $hourly_rate + $bonus_per_hour;
$approved_ot_amount = $approved_ot_hours * $ot_rate_per_hour;

$attendance_salary = 0;

if ($present_days == 0 && $late_days == 0 && $half_days == 0) {
    $attendance_salary = 0;
} else {
    $attendance_salary = ($paid_present_days * $per_day) + ($half_days * ($per_day / 2));
    $attendance_salary = round(max(0, $attendance_salary), 2);
}

$total = $attendance_salary + $approved_ot_amount;

$checkStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM employee_payments WHERE employee_id = ? AND payment_type = 'Salary'");
$checkStmt->execute([$employee_id]);
$paidAmount = (float) $checkStmt->fetchColumn();

$advanceStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM employee_payments WHERE employee_id = ? AND payment_date BETWEEN ? AND ? AND payment_type = 'Advance' AND LOWER(status) = 'deducted'");
$advanceStmt->execute([$employee_id, $from_date, $to_date]);
$advanceRecovered = (float) $advanceStmt->fetchColumn();

$salaryPayable = round(max(0, $total - $advanceRecovered), 2);
$paidAmount = round($paidAmount, 2);
$remainingAmount = round(max(0, $salaryPayable - $paidAmount), 2);
$totalEarned = $remainingAmount;
$pendingAmount = $remainingAmount;


$pageTitle = $t['my_earnings'] . " - Sogasu Staff";
$headerTitle = $t['earnings'];
$activePage = "earnings";
include 'includes/header.php';
?>

<div class="container" style="padding-bottom: 100px;">

    <!-- Earnings Summary Card -->
    <div class="card"
        style="background: linear-gradient(135deg, #0f172a, #1e293b); color: white; border: none; padding: 2rem 1.5rem; border-radius: 24px; position: relative; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.4);">
        <div
            style="position: absolute; right: -30px; top: -30px; font-size: 10rem; opacity: 0.05; transform: rotate(-15deg);">
            <i class="ri-bank-card-line"></i>
        </div>

        <div style="position: relative; z-index: 1;">
            <div
                style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1.5px; opacity: 0.7; margin-bottom: 0.5rem;">
                Total Earnings</div>
            <div style="font-size: 2.75rem; font-weight: 800; line-height: 1; margin-bottom: 1.5rem;">
                ₹<?= number_format($totalEarned, 0) ?></div>

            <div style="display: flex; gap: 0.75rem;">
                <div
                    style="background: rgba(34, 197, 94, 0.2); border: 1px solid rgba(34, 197, 94, 0.3); padding: 0.5rem 1rem; border-radius: 12px; display: flex; align-items: center; gap: 0.5rem;">
                    <div style="width: 8px; height: 8px; background: #22c55e; border-radius: 50%;"></div>
                    <span style="font-size: 0.75rem; font-weight: 600;">Salary Paid</span>
                </div>
                <?php if ($pendingAmount > 0): ?>
                    <div
                        style="background: rgba(245, 158, 11, 0.2); border: 1px solid rgba(245, 158, 11, 0.3); padding: 0.5rem 1rem; border-radius: 12px; display: flex; align-items: center; gap: 0.5rem;">
                        <div style="width: 8px; height: 8px; background: #f59e0b; border-radius: 50%;"></div>
                        <span style="font-size: 0.75rem; font-weight: 600;">₹<?= number_format($pendingAmount, 0) ?>
                            Pending</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- History List -->
    <div class="section-title"
        style="margin-top: 2rem; display: flex; justify-content: space-between; align-items: center;">
        <span>Recent Activity</span>
        <i class="ri-filter-3-line" style="color: #64748b;"></i>
    </div>

    <?php if (empty($history)): ?>
        <div class="card" style="text-align: center; padding: 3rem 1.5rem; border-style: dashed;">
            <div
                style="width: 60px; height: 60px; background: #f1f5f9; color: #94a3b8; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                <i class="ri-history-line" style="font-size: 1.5rem;"></i>
            </div>
            <div style="color: #64748b; font-size: 0.9rem;">No payment history found.</div>
        </div>
    <?php else: ?>
        <div class="card" style="padding: 0; overflow: hidden; border-radius: 20px;">
            <?php foreach ($history as $index => $item): ?>
                <div
                    style="padding: 1.25rem; border-bottom: <?= ($index === count($history) - 1) ? 'none' : '1px solid #f1f5f9' ?>; display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <div
                            style="width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; 
                            background: <?= ($item['source'] === 'ot') ? '#f0f9ff' : ($item['type'] === 'Advance Deduction' ? '#fef2f2' : '#f0fdf4') ?>; 
                            color: <?= ($item['source'] === 'ot') ? '#0369a1' : ($item['type'] === 'Advance Deduction' ? '#b91c1c' : '#15803d') ?>;">
                            <i class="<?= ($item['source'] === 'ot') ? 'ri-time-line' : ($item['type'] === 'Advance Deduction' ? 'ri-hand-coin-line' : 'ri-wallet-3-line') ?>"
                                style="font-size: 1.4rem;"></i>
                        </div>
                        <div>
                            <div style="font-weight: 700; font-size: 1rem; color: #1e293b;">
                                <?= htmlspecialchars($item['description'] ?: ($item['source'] === 'ot' ? 'Overtime Pay' : 'Salary Payout')) ?>
                            </div>
                            <div style="font-size: 0.75rem; color: #64748b; font-weight: 500;">
                                <?= date('D, d M', strtotime($item['date'])) ?> • <?= $item['type'] ?></div>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div
                            style="font-weight: 800; font-size: 1.1rem; color: <?= ($item['type'] === 'Advance Deduction') ? '#ef4444' : '#22c55e' ?>;">
                            <?= ($item['type'] === 'Advance Deduction') ? '- ' : '+ ' ?>₹<?= number_format($item['amount'], 0) ?>
                        </div>
                        <div
                            style="font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; 
                            color: <?= ($item['status'] === 'Paid' || $item['status'] === 'Approved') ? '#10b981' : '#f59e0b' ?>;">
                            <?= $item['status'] ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<style>
    .container {
        animation: fadeIn 0.4s ease-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<?php include 'includes/bottom-nav.php'; ?>