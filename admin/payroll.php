<?php
session_start();
include '../includes/db.php';
require_once 'attendance_calculator.php';

// Handle Status Toggle via AJAX (kept for compatibility)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['status'])) {
    $stmt = $pdo->prepare("UPDATE employees SET status=? WHERE id=?");
    echo json_encode([
        'success' => $stmt->execute([$_POST['status'], $_POST['id']])
    ]);
    exit;
}
$employee_type = $_GET['employee_type'] ?? 'inhouse';

if (!in_array($employee_type, ['inhouse', 'outsource'])) {
    $employee_type = 'inhouse';
}

// Preset and Date Logic
$preset = $_GET['preset'] ?? 'this_week';
if ($preset === 'this_week') {
    $from_date = date('Y-m-d', strtotime('monday this week'));
    $to_date = date('Y-m-d', strtotime('sunday this week'));
} elseif ($preset === 'last_week') {
    $from_date = date('Y-m-d', strtotime('monday last week'));
    $to_date = date('Y-m-d', strtotime('sunday last week'));
} elseif ($preset === 'this_month') {
    $from_date = date('Y-m-01');
    $to_date = date('Y-m-t');
} elseif ($preset === 'last_month') {
    $from_date = date('Y-m-01', strtotime('first day of last month'));
    $to_date = date('Y-m-t', strtotime('last day of last month'));
} else {
    $from_date = $_GET['from_date'] ?? date('Y-m-d', strtotime('monday this week'));
    $to_date = $_GET['to_date'] ?? date('Y-m-d', strtotime('sunday this week'));
    $preset = 'custom';
}

// Fetch employees with attendance, overtime and advance details for the selected period
$stmt = $pdo->prepare("
    SELECT 
        employees.id,
        employees.first_name,
        employees.last_name,
        employees.base_salary,
        employees.pay_cycle,
        employees.payment_model,
        employees.status AS emp_status,
        users.status AS user_status,
        (SELECT COUNT(*) FROM attendance WHERE employee_id = employees.id AND attendance_date BETWEEN :from_date1 AND :to_date1 AND status = 'Present') as present_days,
        (SELECT COUNT(*) FROM attendance WHERE employee_id = employees.id AND attendance_date BETWEEN :from_date2 AND :to_date2 AND status = 'Half Day') as half_days,
        (SELECT COUNT(*) FROM attendance
        WHERE employee_id = employees.id
        AND attendance_date BETWEEN :from_date6 AND :to_date6
        AND status = 'Absent') AS absent_days,

        (SELECT COUNT(*) FROM attendance
        WHERE employee_id = employees.id
        AND attendance_date BETWEEN :from_date7 AND :to_date7
        AND status = 'Late') AS late_days,
        (
        SELECT COALESCE(SUM(ot_minutes),0)
        FROM attendance
        WHERE employee_id = employees.id
        AND attendance_date BETWEEN :from_date3 AND :to_date3
        ) AS approved_ot_minutes,
        (SELECT SUM(hours) FROM employee_overtime WHERE employee_id = employees.id AND status = 'Pending' AND ot_date BETWEEN :from_date5 AND :to_date5) as pending_ot_hours,
        (SELECT SUM(CASE WHEN status = 'Paid' THEN amount ELSE -amount END) FROM employee_payments WHERE employee_id = employees.id AND payment_type = 'Advance') as advance_dues,
        (
            SELECT COALESCE(

                (
                    SELECT sr.shift_type_id
                    FROM shift_roster sr
                    WHERE sr.employee_id = employees.id
                    AND sr.roster_date <= :to_date_shift
                    ORDER BY sr.roster_date DESC
                    LIMIT 1
                ),

                employees.default_shift_id

            )
        ) AS shift_type_id
    FROM employees
    LEFT JOIN users ON employees.user_id = users.id
    WHERE employees.is_deleted = 0
    AND employees.employee_type = :employee_type
    ORDER BY employees.id DESC
");

$stmt->execute([
    'from_date1' => $from_date,
    'to_date1' => $to_date,
    'from_date2' => $from_date,
    'to_date2' => $to_date,
    'from_date3' => $from_date,
    'to_date3' => $to_date,
    'from_date5' => $from_date,
    'to_date5' => $to_date,
    'from_date6' => $from_date,
    'to_date6' => $to_date,
    'from_date7' => $from_date,
    'to_date7' => $to_date,
    'employee_type' => $employee_type,
    'to_date_shift' => $to_date
]);

$employees = $stmt->fetchAll();
$attendanceSummary = calculateAttendanceSummary(
    $pdo,
    $from_date,
    $to_date
);

// Paid in this period calculation

$paymentType = ($employee_type == 'outsource') ? 'Outsource Payment' : 'Salary';

$statsStmt = $pdo->prepare("
    SELECT SUM(amount) 
    FROM employee_payments 
    WHERE payment_date BETWEEN ? AND ? 
    AND payment_type = ?
");
$statsStmt->execute([$from_date, $to_date, $paymentType]);
$paidThisPeriod = $statsStmt->fetchColumn() ?: 0;

$totalDue = 0;
$pendingCount = 0;
$totalOT = 0;
$totalOutsourceEmployees = count($employees);

foreach ($employees as &$row) {
    if ($employee_type == 'outsource') {
        $earnStmt = $pdo->prepare("
    SELECT COALESCE(SUM(outsource_credit),0)
    FROM outsource_orders
    WHERE assigned_employee_id = ?
    AND order_status = 'completed'
    AND is_deleted = 0
    AND DATE(updated_at) BETWEEN ? AND ?
");
        $earnStmt->execute([$row['id'], $from_date, $to_date]);
        $row['outsource_earnings'] = $earnStmt->fetchColumn();

        $paidStmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount),0)
    FROM employee_payments
    WHERE employee_id = ?
    AND payment_type = 'Outsource Payment'
    AND status = 'Paid'
    AND payment_date BETWEEN ? AND ?
");
        $paidStmt->execute([$row['id'], $from_date, $to_date]);
        $row['outsource_paid'] = $paidStmt->fetchColumn();

        $row['outsource_balance'] =
            max($row['outsource_earnings'] - $row['outsource_paid'], 0);

        $row['calculated_total'] = $row['outsource_balance'];
    }
    $baseSalary = floatval($row['base_salary']);

    switch (strtolower(trim($row['pay_cycle']))) {

        case 'daily':
            $per_day = $baseSalary;
            break;

        case 'weekly':
            $per_day = $baseSalary / 7;
            break;

        default:
            $per_day = $baseSalary / 30;
            break;
    }
    if ($employee_type == 'inhouse') {

        $present_days = 0;
        $half_days = 0;
        $late_days = 0;
        $absent_days = 0;

        $period = new DatePeriod(
            new DateTime($from_date),
            new DateInterval('P1D'),
            (new DateTime($to_date))->modify('+1 day')
        );

        foreach ($period as $day) {

            $dayNo = (int) $day->format('j');

            $status = $attendanceSummary[$row['id']][$dayNo] ?? null;

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

                case 'Absent':
                    $absent_days++;
                    break;
            }
        }

        $row['present_days'] = $present_days;
        $row['half_days'] = $half_days;
        $row['late_days'] = $late_days;
        $row['absent_days'] = $absent_days;

        // Only Present days get full salary.
        // Half Day gets half salary.
        // Late is counted separately.
        // Late (1st & 2nd) employees are also paid as Present
        $paid_present_days = $present_days + $late_days;
        // ================= OT CALCULATION =================

        $approved_ot_minutes = intval($row['approved_ot_minutes']);
        $approved_ot_hours = $approved_ot_minutes / 60;
        // Get Working Hours from Assigned Shift

        $working_hours = 8; // fallback

        if (!empty($row['shift_type_id'])) {

            $shiftStmt = $pdo->prepare("
        SELECT start_time, end_time
        FROM shift_types
        WHERE id = ?
        LIMIT 1
    ");

            $shiftStmt->execute([$row['shift_type_id']]);

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

        // Hourly Rate based ONLY on Pay Cycle

        switch (strtolower(trim($row['pay_cycle']))) {

            case 'daily':
                $hourly_rate = $baseSalary / $working_hours;
                break;

            case 'weekly':
                $hourly_rate = ($baseSalary / 7) / $working_hours;
                break;

            case 'monthly':
                $hourly_rate = ($baseSalary / 30) / $working_hours;
                break;

            default:
                $hourly_rate = ($baseSalary / 30) / $working_hours;
                break;
        }

        // Read OT Percentage
        $otStmt = $pdo->query("
    SELECT ot_percentage
    FROM ot_rate_settings
    ORDER BY id DESC
    LIMIT 1
    ");

        $otRate = (float) $otStmt->fetchColumn();

        $otRate = floatval($otRate);

        $bonus_per_hour = ($hourly_rate * $otRate) / 100;

        $ot_rate_per_hour = $hourly_rate + $bonus_per_hour;

        $approved_ot_amount = $approved_ot_hours * $ot_rate_per_hour;

        // Save values
        $row['approved_ot_hours'] = $approved_ot_hours;
        $row['approved_ot_amount'] = $approved_ot_amount;

        // ================= END OT =================

        $attendance_salary =
            ($paid_present_days * $per_day)
            +
            ($half_days * ($per_day / 2));

        $total = $attendance_salary;

        // Add approved OT
        $total += $approved_ot_amount;
        $row['attendance_salary'] = $attendance_salary;
        $row['approved_ot_amount'] = $approved_ot_amount;
    }
    if ($employee_type == 'outsource') {
        $total = $row['outsource_balance'];
    }

    // Don't deduct advance here.
    // Don't deduct bonus here.
    // Don't deduct fines here.



    // Check if salary has already been paid for this exact period
    if ($employee_type == 'outsource') {
        if ($row['outsource_earnings'] <= 0) {
            $payment_status = 'No Earnings';
        } elseif ($row['outsource_balance'] > 0) {
            $payment_status = 'Pending';
        } else {
            $payment_status = 'Paid';
        }
        $row['calculated_total'] = $row['outsource_balance'];
    } else {
        // Salary already paid in this period
        $checkStmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount),0)
    FROM employee_payments
    WHERE employee_id = ?
    AND payment_date BETWEEN ? AND ?
    AND payment_type = 'Salary'
");

        $checkStmt->execute([$row['id'], $from_date, $to_date]);
        $paidAmount = (float) $checkStmt->fetchColumn();


        // Advance recovered in this period
        $advanceStmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount),0)
            FROM employee_payments
            WHERE employee_id = ?
            AND payment_date BETWEEN ? AND ?
            AND payment_type = 'Advance'
            AND LOWER(status) = 'deducted'
        ");

        $advanceStmt->execute([$row['id'], $from_date, $to_date]);
        $advanceRecovered = (float) $advanceStmt->fetchColumn();


        // Same calculation used in pay-employee.php
        $salaryPayable = max(0, $total - $advanceRecovered);

        $remainingAmount = max(0, $salaryPayable - $paidAmount);

        $row['calculated_total'] = $remainingAmount;

        if ($paidAmount <= 0) {
            $payment_status = 'Pending';
        } elseif ($remainingAmount > 0) {
            $payment_status = 'Partially Paid';
        } else {
            $payment_status = 'Paid';
        }
    }

    $row['payment_status'] = $payment_status;

    if ($payment_status == 'Pending' && $row['calculated_total'] > 0) {
        $totalDue += $row['calculated_total'];
        $pendingCount++;
    }
    if ($employee_type == 'inhouse') {
        $totalOT += $approved_ot_hours;
    }
}
unset($row);

$pageTitle = "Payroll Dashboard - Sogasu";
$activePage = "payroll";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="display: flex; flex-direction: column; gap: 1.5rem; width: 100%; max-width: 100%; ">

        <!-- Premium Page Header -->

        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">
                    <?= ucfirst($employee_type) ?> Payroll Portal
                </h2>
                <p style="color: #64748b; margin-top: 0.25rem;">Calculate employee attendance salaries, approve
                    overtime, and process payouts.</p>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <a href="add-employee.php" class="btn btn-primary"
                    style="background: #4f46e5; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; color: white; display: inline-flex; align-items: center; gap: 0.5rem;">
                    <i class="ri-user-add-line"></i> Add New Employee
                </a>
            </div>
        </div>
        <!-- Filter Bar Card -->
        <div class="table-container" style="padding: 1.25rem; margin-top: 0;">
            <form method="GET" style="display: flex; gap: 1.5rem; align-items: flex-end; flex-wrap: wrap;">
                <input type="hidden" name="employee_type" value="<?= $employee_type ?>">
                <div class="filter-item">
                    <span class="label">Pay Period Preset</span>
                    <select name="preset" id="periodPreset" class="premium-input"
                        style="width: auto; font-weight: 700; color: var(--text-dark);"
                        onchange="handlePresetChange(this.value)">
                        <option value="this_week" <?= $preset == 'this_week' ? 'selected' : '' ?>>This Week (Mon - Sun)
                        </option>
                        <option value="last_week" <?= $preset == 'last_week' ? 'selected' : '' ?>>Last Week (Mon - Sun)
                        </option>
                        <option value="this_month" <?= $preset == 'this_month' ? 'selected' : '' ?>>This Month</option>
                        <option value="last_month" <?= $preset == 'last_month' ? 'selected' : '' ?>>Last Month</option>
                        <option value="custom" <?= $preset == 'custom' ? 'selected' : '' ?>>Custom Range</option>
                    </select>
                </div>

                <div class="filter-item" id="fromDateGroup">
                    <span class="label">From Date</span>
                    <input type="date" name="from_date" id="from_date" value="<?= $from_date ?>" class="premium-input"
                        style="font-weight: 700;">
                </div>

                <div class="filter-item" id="toDateGroup">
                    <span class="label">To Date</span>
                    <input type="date" name="to_date" id="to_date" value="<?= $to_date ?>" class="premium-input"
                        style="font-weight: 700;">
                </div>
                <div>
                    <button type="submit" class="btn-premium" style="padding: 0.65rem 1.5rem; border-radius: 12px;">
                        <i class="ri-refresh-line"></i> Apply Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Stats Grid -->
        <section class="premium-stats-grid">
            <div class="glass-card premium-stat-card blue"
                style="padding: 1.25rem; display: flex; flex-direction: row; align-items: center; justify-content: space-between; gap: 1rem;">
                <div>
                    <div class="label"
                        style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">
                        Total Period Due</div>
                    <div class="value"
                        style="font-size: 1.5rem; font-weight: 800; color: var(--text-dark); margin-top: 0.25rem;">₹
                        <?= number_format($totalDue, 2) ?>
                    </div>
                </div>
                <div class="icon-box"
                    style="width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; background: #e0e7ff; color: #4338ca;">
                    <i class="ri-wallet-3-line"></i>
                </div>
            </div>

            <div class="glass-card premium-stat-card orange"
                style="padding: 1.25rem; display: flex; flex-direction: row; align-items: center; justify-content: space-between; gap: 1rem;">
                <div>
                    <div class="label"
                        style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">
                        Pending Payments</div>
                    <div class="value"
                        style="font-size: 1.5rem; font-weight: 800; color: var(--text-dark); margin-top: 0.25rem;">
                        <?= $pendingCount ?>
                    </div>
                </div>
                <div class="icon-box"
                    style="width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; background: #ffedd5; color: #c2410c;">
                    <i class="ri-time-line"></i>
                </div>
            </div>

            <div class="glass-card premium-stat-card purple"
                style="padding: 1.25rem; display: flex; flex-direction: row; align-items: center; justify-content: space-between; gap: 1rem;">
                <div>
                    <div class="label"
                        style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">

                        <?= $employee_type == 'outsource' ? 'Total Employees' : 'Total OT Hours' ?>

                    </div>

                    <div class="value"
                        style="font-size: 1.5rem; font-weight: 800; color: var(--text-dark); margin-top: 0.25rem;">

                        <?php if ($employee_type == 'outsource'): ?>
                            <?= $totalOutsourceEmployees ?>
                        <?php else: ?>
                            <?= number_format($totalOT, 1) ?>h
                        <?php endif; ?>

                    </div>
                </div>

                <div class="icon-box"
                    style="width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; background: #f3e8ff; color: #7e22ce;">
                    <i class="ri-history-line"></i>
                </div>
            </div>

            <div class="glass-card premium-stat-card green"
                style="padding: 1.25rem; display: flex; flex-direction: row; align-items: center; justify-content: space-between; gap: 1rem;">
                <div>
                    <div class="label"
                        style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">
                        <?= $employee_type == 'outsource' ? 'Processed Payouts' : 'Processed / Paid' ?>
                    </div>
                    <div class="value"
                        style="font-size: 1.5rem; font-weight: 800; color: var(--text-dark); margin-top: 0.25rem;">₹
                        <?= number_format($paidThisPeriod, 2) ?>
                    </div>
                </div>
                <div class="icon-box"
                    style="width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; background: #dcfce7; color: #15803d;">
                    <i class="ri-checkbox-circle-line"></i>
                </div>
            </div>
        </section>
        <div style="
            background:#ffffff;
            border-radius:18px;
            padding:8px;
            display:flex;
            width:100%;
            box-shadow:0 4px 20px rgba(0,0,0,0.06);
            margin-bottom:20px;
        ">

            <a href="?employee_type=inhouse&preset=<?= $preset ?>&from_date=<?= $from_date ?>&to_date=<?= $to_date ?>"
                style="
                width:50%;
                text-align:center;
                padding:12px 20px;
                border-radius:14px;
                text-decoration:none;
                font-size:18px;
                font-weight:700;
                transition:0.3s;
                background:<?= $employee_type == 'inhouse' ? 'linear-gradient(135deg,#4f46e5,#6366f1)' : '#f8fafc' ?>;
                color:<?= $employee_type == 'inhouse' ? '#fff' : '#334155' ?>;
                box-shadow:<?= $employee_type == 'inhouse' ? '0 8px 20px rgba(79,70,229,0.25)' : 'none' ?>;
                ">
                Inhouse Employees
            </a>

            <a href="?employee_type=outsource&preset=<?= $preset ?>&from_date=<?= $from_date ?>&to_date=<?= $to_date ?>"
                style="
                width:50%;
                text-align:center;
                padding:12px 20px;
                border-radius:14px;
                text-decoration:none;
                font-size:18px;
                font-weight:700;
                transition:0.3s;
                background:<?= $employee_type == 'outsource' ? 'linear-gradient(135deg,#4f46e5,#6366f1)' : '#f8fafc' ?>;
                color:<?= $employee_type == 'outsource' ? '#fff' : '#334155' ?>;
                box-shadow:<?= $employee_type == 'outsource' ? '0 8px 20px rgba(79,70,229,0.25)' : 'none' ?>;
                ">
                Outsource Employees
            </a>

        </div>

        <!-- Payroll Table Card -->
        <div class="table-container" style="padding: 1.5rem;">
            <div
                style="padding-bottom: 1.25rem; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-dark); margin: 0;">Payroll Report</h3>
                <span
                    style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; background: #eef2ff; padding: 4px 12px; border-radius: 20px;">
                    Period: <strong><?= date('M d, Y', strtotime($from_date)) ?></strong> to
                    <strong><?= date('M d, Y', strtotime($to_date)) ?></strong>
                </span>
            </div>

            <div style="overflow-x: auto;">
                <table id="payrollTable" class="table">
                    <thead>
                        <tr>
                            <th>Staff Member</th>
                            <?php if ($employee_type == 'outsource'): ?>
                                <th>Earnings</th>
                                <th>Payments</th>
                                <th>Balance</th>
                            <?php else: ?>
                                <th>Base Earnings</th>
                                <th>Overtime (OT)</th>
                                <th>Deductions</th>
                                <th>Net Payable</th>
                            <?php endif; ?>
                            <th>Payment Status</th>
                            <th style="text-align: right;">Operations</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $row): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div class="premium-avatar">
                                            <?= strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 700; color: var(--text-dark);">
                                                <?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?>
                                            </div>
                                            <div
                                                style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">
                                                <?= htmlspecialchars($row['pay_cycle']) ?> Cycle
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <?php if ($employee_type == 'outsource'): ?>

                                    <td>
                                        <div style="font-weight:700;color:var(--success);">
                                            ₹<?= number_format($row['outsource_earnings'], 2) ?>
                                        </div>
                                        <div style="font-size:0.75rem;color:var(--text-muted);">
                                            Completed Orders
                                        </div>
                                    </td>

                                    <td>
                                        <div style="font-weight:700;color:#2563eb;">
                                            ₹<?= number_format($row['outsource_paid'], 2) ?>
                                        </div>
                                        <div style="font-size:0.75rem;color:var(--text-muted);">
                                            Paid Amount
                                        </div>
                                    </td>

                                    <td>
                                        <div style="font-weight:800;color:var(--primary);font-size:1.1rem;">
                                            ₹<?= number_format($row['outsource_balance'], 2) ?>
                                        </div>
                                    </td>

                                <?php else: ?>

                                    <td>
                                        <div style="font-weight: 700; color: var(--text-dark);">₹
                                            <?= number_format($row['base_salary'] ?: 0, 2) ?>
                                        </div>
                                        <div style="font-size:12px;margin-top:3px;line-height:1.5;">

                                            <span style="color:#16a34a;font-weight:700;">
                                                P : <?= $row['present_days'] ?>
                                            </span>

                                            |

                                            <span style="color:#f59e0b;font-weight:700;">
                                                H : <?= $row['half_days'] ?>
                                            </span>

                                            <br>

                                            <span style="color:#ef4444;font-weight:700;">
                                                A : <?= $row['absent_days'] ?>
                                            </span>

                                            |

                                            <span style="color:#0ea5e9;font-weight:700;">
                                                L : <?= $row['late_days'] ?>
                                            </span>

                                        </div>
                                    </td>

                                    <td>
                                        <div style="font-weight: 700; color: var(--success);">
                                            <?= number_format($row['approved_ot_hours'] ?: 0, 1) ?>h
                                        </div>
                                        <div style="font-size: 0.7rem; color: var(--text-muted);">
                                            Val: ₹<?= number_format($row['approved_ot_amount'] ?: 0, 2) ?>
                                        </div>
                                    </td>

                                    <td>
                                        <?php if ($row['advance_dues'] > 0): ?>

                                            <div style="font-size:12px;">
                                                <div style="color:#ef4444;font-weight:700;">
                                                    Advance
                                                </div>

                                                <div style="font-weight:700;">
                                                    ₹<?= number_format($row['advance_dues'], 2) ?>
                                                </div>
                                            </div>

                                        <?php else: ?>

                                            -

                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div style="font-weight: 800; color: var(--primary); font-size: 1.1rem;">
                                            ₹<?= number_format($row['calculated_total'], 2) ?>
                                        </div>
                                    </td>

                                <?php endif; ?>
                                <td>
                                    <?php
                                    $badgeClass = 'badge-pending';

                                    if ($row['payment_status'] == 'Paid') {
                                        $badgeClass = 'badge-active';
                                    } elseif ($row['payment_status'] == 'Partially Paid') {
                                        $badgeClass = 'badge-warning';
                                    } elseif (
                                        $employee_type == 'outsource' &&
                                        $row['payment_status'] == 'No Earnings'
                                    ) {
                                        $badgeClass = 'badge-neutral';
                                    }
                                    ?>

                                    <span class="premium-badge <?= $badgeClass ?>">
                                        <?= $row['payment_status'] ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                        <?php
                                        $pay_url = ($employee_type == 'outsource')
                                            ? "pay-outsource-employee.php"
                                            : "pay-employee.php";
                                        ?>

                                        <a href="<?= $pay_url ?>?id=<?= $row['id'] ?>&from_date=<?= $from_date ?>&to_date=<?= $to_date ?>"
                                            class="btn-premium"
                                            style="padding: 0.4rem 1rem; font-size: 0.8rem; text-decoration: none;">
                                            Pay Now
                                        </a>
                                        <a href="give-advance.php?id=<?= $row['id'] ?>" class="btn-icon-p"
                                            title="Give Advance" style="color: var(--warning);"><i
                                                class="ri-hand-coin-line"></i></a>
                                        <a href="employee-history.php?id=<?= $row['id'] ?>" class="btn-icon-p"
                                            title="History"><i class="ri-history-line"></i></a>
                                    </div>
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
    .filter-item {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .filter-item .label {
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
    }

    .btn-icon-p {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: #f8fafc;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #f1f5f9;
        color: var(--text-muted);
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        font-size: 1.1rem;
    }

    .btn-icon-p:hover {
        background: white;
        transform: translateY(-2px);
        box-shadow: var(--shadow-sm);
        border-color: var(--primary-light);
        color: var(--primary);
    }

    .badge-neutral {
        background: #e2e8f0;
        color: #475569;
    }

    .badge-warning {
        background: #fef3c7;
        color: #b45309;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php include __DIR__ . '/includes/datatable.php'; ?>
<script>
    $(document).ready(function () {
        initializeDataTable('payrollTable', 'Weekly Payroll Report');
        handlePresetChange(document.getElementById('periodPreset').value);
    });

    function handlePresetChange(preset) {
        const fromGroup = document.getElementById('fromDateGroup');
        const toGroup = document.getElementById('toDateGroup');

        if (preset === 'custom') {
            fromGroup.style.opacity = '1';
            fromGroup.style.pointerEvents = 'auto';
            toGroup.style.opacity = '1';
            toGroup.style.pointerEvents = 'auto';
        } else {
            fromGroup.style.opacity = '0.5';
            fromGroup.style.pointerEvents = 'none';
            toGroup.style.opacity = '0.5';
            toGroup.style.pointerEvents = 'none';

            const today = new Date();
            let fromDate = '';
            let toDate = '';

            if (preset === 'this_week') {
                const currentDay = today.getDay();
                const distanceToMonday = currentDay === 0 ? -6 : 1 - currentDay;
                const monday = new Date(today);
                monday.setDate(today.getDate() + distanceToMonday);
                fromDate = formatDate(monday);

                const sunday = new Date(monday);
                sunday.setDate(monday.getDate() + 6);
                toDate = formatDate(sunday);
            } else if (preset === 'last_week') {
                const currentDay = today.getDay();
                const distanceToMonday = currentDay === 0 ? -6 : 1 - currentDay;
                const monday = new Date(today);
                monday.setDate(today.getDate() + distanceToMonday - 7);
                fromDate = formatDate(monday);

                const sunday = new Date(monday);
                sunday.setDate(monday.getDate() + 6);
                toDate = formatDate(sunday);
            } else if (preset === 'this_month') {
                fromDate = formatDate(new Date(today.getFullYear(), today.getMonth(), 1));
                toDate = formatDate(new Date(today.getFullYear(), today.getMonth() + 1, 0));
            } else if (preset === 'last_month') {
                fromDate = formatDate(new Date(today.getFullYear(), today.getMonth() - 1, 1));
                toDate = formatDate(new Date(today.getFullYear(), today.getMonth(), 0));
            }

            document.getElementById('from_date').value = fromDate;
            document.getElementById('to_date').value = toDate;
        }
    }

    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
</script>

<?php include 'includes/footer.php'; ?>