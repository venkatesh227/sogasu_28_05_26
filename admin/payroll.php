<?php
session_start();
include '../includes/db.php';

// Handle Status Toggle via AJAX (kept for compatibility)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['status'])) {
    $stmt = $pdo->prepare("UPDATE employees SET status=? WHERE id=?");
    echo json_encode([
        'success' => $stmt->execute([$_POST['status'], $_POST['id']])
    ]);
    exit;
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
        (SELECT SUM(hours) FROM employee_overtime WHERE employee_id = employees.id AND status = 'Approved' AND ot_date BETWEEN :from_date3 AND :to_date3) as approved_ot_hours,
        (SELECT SUM(amount) FROM employee_overtime WHERE employee_id = employees.id AND status = 'Approved' AND ot_date BETWEEN :from_date4 AND :to_date4) as approved_ot_amount,
        (SELECT SUM(hours) FROM employee_overtime WHERE employee_id = employees.id AND status = 'Pending' AND ot_date BETWEEN :from_date5 AND :to_date5) as pending_ot_hours,
        (SELECT SUM(CASE WHEN status = 'Paid' THEN amount ELSE -amount END) FROM employee_payments WHERE employee_id = employees.id AND payment_type = 'Advance') as advance_dues
    FROM employees
    LEFT JOIN users ON employees.user_id = users.id
    WHERE employees.is_deleted = 0
    ORDER BY employees.id DESC
");

$stmt->execute([
    'from_date1' => $from_date, 'to_date1' => $to_date,
    'from_date2' => $from_date, 'to_date2' => $to_date,
    'from_date3' => $from_date, 'to_date3' => $to_date,
    'from_date4' => $from_date, 'to_date4' => $to_date,
    'from_date5' => $from_date, 'to_date5' => $to_date
]);

$employees = $stmt->fetchAll();

// Paid in this period calculation
$statsStmt = $pdo->prepare("SELECT SUM(amount) FROM employee_payments WHERE payment_date BETWEEN ? AND ? AND payment_type = 'Salary'");
$statsStmt->execute([$from_date, $to_date]);
$paidThisPeriod = $statsStmt->fetchColumn() ?: 0;

$totalDue = 0;
$pendingCount = 0;
$totalOT = 0;

foreach ($employees as &$row) {
    $monthly_base = floatval($row['base_salary']);
    $per_day = $monthly_base / 30; // Assuming 30 days for daily rate
    
    $present_days = intval($row['present_days']);
    $half_days = intval($row['half_days']);
    $approved_ot_amount = floatval($row['approved_ot_amount']);
    $advances = floatval($row['advance_dues']);
    
    // Formula: (Present * Per Day) + (Half * Half Salary) + OT - Advances
    $attendance_salary = ($present_days * $per_day) + ($half_days * ($per_day / 2));
    $total = $attendance_salary + $approved_ot_amount - $advances;
    
    // Check if salary has already been paid for this exact period
    $checkStmt = $pdo->prepare("SELECT SUM(amount) FROM employee_payments WHERE employee_id = ? AND payment_date BETWEEN ? AND ? AND payment_type = 'Salary'");
    $checkStmt->execute([$row['id'], $from_date, $to_date]);
    $paidAmount = $checkStmt->fetchColumn();
    $payment_status = $paidAmount > 0 ? 'Paid' : 'Pending';
    
    $row['calculated_total'] = max(0, $total); // Salary cannot be negative
    $row['payment_status'] = $payment_status;
    
    if ($payment_status == 'Pending') {
        $totalDue += $row['calculated_total'];
        $pendingCount++;
    }
    $totalOT += floatval($row['approved_ot_hours']);
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
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Weekly Payroll Portal</h2>
                <p style="color: #64748b; margin-top: 0.25rem;">Calculate employee attendance salaries, approve overtime, and process payouts.</p>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <a href="add-employee.php" class="btn btn-primary" style="background: #4f46e5; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; color: white; display: inline-flex; align-items: center; gap: 0.5rem;">
                    <i class="ri-user-add-line"></i> Add New Employee
                </a>
            </div>
        </div>

        <!-- Filter Bar Card -->
        <div class="table-container" style="padding: 1.25rem; margin-top: 0;">
            <form method="GET" style="display: flex; gap: 1.5rem; align-items: flex-end; flex-wrap: wrap;">
                <div class="filter-item">
                    <span class="label">Pay Period Preset</span>
                    <select name="preset" id="periodPreset" class="premium-input" style="width: auto; font-weight: 700; color: var(--text-dark);" onchange="handlePresetChange(this.value)">
                        <option value="this_week" <?= $preset == 'this_week' ? 'selected' : '' ?>>This Week (Mon - Sun)</option>
                        <option value="last_week" <?= $preset == 'last_week' ? 'selected' : '' ?>>Last Week (Mon - Sun)</option>
                        <option value="this_month" <?= $preset == 'this_month' ? 'selected' : '' ?>>This Month</option>
                        <option value="last_month" <?= $preset == 'last_month' ? 'selected' : '' ?>>Last Month</option>
                        <option value="custom" <?= $preset == 'custom' ? 'selected' : '' ?>>Custom Range</option>
                    </select>
                </div>
                
                <div class="filter-item" id="fromDateGroup">
                    <span class="label">From Date</span>
                    <input type="date" name="from_date" id="from_date" value="<?= $from_date ?>" class="premium-input" style="font-weight: 700;">
                </div>

                <div class="filter-item" id="toDateGroup">
                    <span class="label">To Date</span>
                    <input type="date" name="to_date" id="to_date" value="<?= $to_date ?>" class="premium-input" style="font-weight: 700;">
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
            <div class="glass-card premium-stat-card blue" style="padding: 1.25rem; display: flex; flex-direction: row; align-items: center; justify-content: space-between; gap: 1rem;">
                <div>
                    <div class="label" style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Total Period Due</div>
                    <div class="value" style="font-size: 1.5rem; font-weight: 800; color: var(--text-dark); margin-top: 0.25rem;">₹ <?= number_format($totalDue, 2) ?></div>
                </div>
                <div class="icon-box" style="width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; background: #e0e7ff; color: #4338ca;">
                    <i class="ri-wallet-3-line"></i>
                </div>
            </div>

            <div class="glass-card premium-stat-card orange" style="padding: 1.25rem; display: flex; flex-direction: row; align-items: center; justify-content: space-between; gap: 1rem;">
                <div>
                    <div class="label" style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Pending Payments</div>
                    <div class="value" style="font-size: 1.5rem; font-weight: 800; color: var(--text-dark); margin-top: 0.25rem;"><?= $pendingCount ?></div>
                </div>
                <div class="icon-box" style="width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; background: #ffedd5; color: #c2410c;">
                    <i class="ri-time-line"></i>
                </div>
            </div>

            <div class="glass-card premium-stat-card purple" style="padding: 1.25rem; display: flex; flex-direction: row; align-items: center; justify-content: space-between; gap: 1rem;">
                <div>
                    <div class="label" style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Total OT Hours</div>
                    <div class="value" style="font-size: 1.5rem; font-weight: 800; color: var(--text-dark); margin-top: 0.25rem;"><?= number_format($totalOT, 1) ?>h</div>
                </div>
                <div class="icon-box" style="width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; background: #f3e8ff; color: #7e22ce;">
                    <i class="ri-history-line"></i>
                </div>
            </div>

            <div class="glass-card premium-stat-card green" style="padding: 1.25rem; display: flex; flex-direction: row; align-items: center; justify-content: space-between; gap: 1rem;">
                <div>
                    <div class="label" style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Processed / Paid</div>
                    <div class="value" style="font-size: 1.5rem; font-weight: 800; color: var(--text-dark); margin-top: 0.25rem;">₹ <?= number_format($paidThisPeriod, 2) ?></div>
                </div>
                <div class="icon-box" style="width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; background: #dcfce7; color: #15803d;">
                    <i class="ri-checkbox-circle-line"></i>
                </div>
            </div>
        </section>

        <!-- Payroll Table Card -->
        <div class="table-container" style="padding: 1.5rem;">
            <div style="padding-bottom: 1.25rem; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-dark); margin: 0;">Payroll Report</h3>
                <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; background: #eef2ff; padding: 4px 12px; border-radius: 20px;">
                    Period: <strong><?= date('M d, Y', strtotime($from_date)) ?></strong> to <strong><?= date('M d, Y', strtotime($to_date)) ?></strong>
                </span>
            </div>

            <div style="overflow-x: auto;">
                <table id="payrollTable" class="table">
                    <thead>
                        <tr>
                            <th>Staff Member</th>
                            <th>Base Earnings</th>
                            <th>Overtime (OT)</th>
                            <th>Deductions</th>
                            <th>Net Payable</th>
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
                                            <div style="font-weight: 700; color: var(--text-dark);"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></div>
                                            <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;"><?= htmlspecialchars($row['pay_cycle']) ?> Cycle</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-dark);">₹ <?= number_format($row['base_salary'] ?: 0, 2) ?></div>
                                    <div style="font-size: 0.75rem; display: flex; gap: 0.5rem; margin-top: 2px;">
                                        <span style="color: var(--success); font-weight: 700;"><?= $row['present_days'] ?>P</span>
                                        <span style="color: var(--warning); font-weight: 700;"><?= $row['half_days'] ?>H</span>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: var(--success);"><?= number_format($row['approved_ot_hours'] ?: 0, 1) ?>h</div>
                                    <div style="font-size: 0.7rem; color: var(--text-muted);">Val: ₹<?= number_format($row['approved_ot_amount'] ?: 0, 2) ?></div>
                                    <?php if ($row['pending_ot_hours'] > 0): ?>
                                        <div style="margin-top: 4px; font-size: 0.65rem; color: var(--danger); font-weight: 700;"><i class="ri-error-warning-line"></i> <?= $row['pending_ot_hours'] ?>h Unapproved</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['advance_dues'] > 0): ?>
                                        <div style="font-weight: 700; color: var(--danger);">₹ <?= number_format($row['advance_dues'], 2) ?></div>
                                        <div style="font-size: 0.7rem; color: var(--text-muted);">Advance Recovery</div>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); opacity: 0.5;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight: 800; color: var(--primary); font-size: 1.1rem;">₹ <?= number_format($row['calculated_total'], 2) ?></div>
                                </td>
                                <td>
                                    <span class="premium-badge <?= $row['payment_status'] == 'Paid' ? 'badge-active' : 'badge-pending' ?>">
                                        <?= $row['payment_status'] ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                        <a href="pay-employee.php?id=<?= $row['id'] ?>&from_date=<?= $from_date ?>&to_date=<?= $to_date ?>" class="btn-premium" style="padding: 0.4rem 1rem; font-size: 0.8rem; text-decoration: none;">Pay Now</a>
                                        <a href="give-advance.php?id=<?= $row['id'] ?>" class="btn-icon-p" title="Give Advance" style="color: var(--warning);"><i class="ri-hand-coin-line"></i></a>
                                        <a href="employee-history.php?id=<?= $row['id'] ?>" class="btn-icon-p" title="History"><i class="ri-history-line"></i></a>
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
    .filter-item { display: flex; flex-direction: column; gap: 0.25rem; }
    .filter-item .label { font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }
    
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
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php include __DIR__ . '/includes/datatable.php'; ?>
<script>
    $(document).ready(function() {
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