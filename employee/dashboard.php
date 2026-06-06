<?php
session_start();
require_once '../includes/db.php';

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

// Fetch employee data and job role
$stmt = $pdo->prepare("
    SELECT e.id, e.first_name, e.last_name, e.preferred_language, e.job_role, e.pay_cycle
    FROM employees e 
    WHERE e.user_id = ? AND e.is_deleted = 0
");
$stmt->execute([$user_id]);
$emp = $stmt->fetch();

$employee_id = null;
$payCycle = '';
$periodLabel = date('M');
if ($emp) {
    $employee_id = $emp['id'];
    $employee_name = $emp['first_name'] . ' ' . ($emp['last_name'] ?? '');
    $role_name = $emp['job_role'] ?? '';
    $payCycle = $emp['pay_cycle'] ?? '';

    if (isset($role_name) && strcasecmp($role_name, 'Supervisor') === 0) {
        include 'supervisor-dashboard.php';
        exit();
    }
}

function getCurrentPayCycleCondition($column, $payCycle) {
    if (stripos($payCycle, 'Weekly') !== false) {
        return "YEARWEEK($column, 1) = YEARWEEK(CURRENT_DATE(), 1)";
    }
    if (stripos($payCycle, 'Daily') !== false) {
        return "DATE($column) = CURRENT_DATE()";
    }
    return "MONTH($column) = MONTH(CURRENT_DATE()) AND YEAR($column) = YEAR(CURRENT_DATE())";
}

function getCurrentPeriodLabel($payCycle) {
    if (stripos($payCycle, 'Weekly') !== false) {
        return 'This Week';
    }
    if (stripos($payCycle, 'Daily') !== false) {
        return date('d M Y');
    }
    return date('F Y');
}

$periodLabel = getCurrentPeriodLabel($payCycle);

// Fetch pending tasks count
$stmt = $pdo->prepare("SELECT COUNT(*) as pending_count FROM orders WHERE assigned_employee_id = ? AND order_status IN ('pending', 'processing', 'ready', 'pattern_making', 'cutting', 'embroidery', 'stitching', 'finishing', 'completed') AND is_deleted = 0");
$stmt->execute([$employee_id]);
$pending = $stmt->fetch();
$pending_tasks = $pending['pending_count'] ?? 0;

// Fetch Active Tasks (Urgent first)
$stmt = $pdo->prepare("
    SELECT 
        o.*,
        sc.name AS garment,
        sc.image AS garment_img,
        c.first_name as cust_first,
        c.last_name as cust_last,
        (SELECT image_path FROM order_images WHERE order_id = o.id AND image_type = 'fabric' LIMIT 1) as fabric_img
    FROM orders o
    LEFT JOIN sub_categories sc ON o.sub_category_id = sc.id
    LEFT JOIN customers c ON o.customer_id = c.id
    WHERE o.assigned_employee_id = ? 
    AND o.order_status IN ('pending', 'processing', 'ready', 'pattern_making', 'cutting', 'embroidery', 'stitching', 'finishing', 'completed')
    AND o.is_deleted = 0
    ORDER BY o.id DESC
    LIMIT 10
");
$stmt->execute([$employee_id]);
$active_tasks = $stmt->fetchAll();

// Fetch stats for the dashboard
$currentMonth = date('Y-m');
$totalEarned = 0;

$dateClause = getCurrentPayCycleCondition('payment_date', $payCycle);
$stmt = $pdo->prepare("SELECT SUM(amount) FROM employee_payments WHERE employee_id = ? AND status IN ('Paid', 'Approved') AND payment_type != 'Advance Deduction' AND $dateClause");
$stmt->execute([$emp['id']]);
$totalEarned += $stmt->fetchColumn() ?: 0;

$dateClause = getCurrentPayCycleCondition('payment_date', $payCycle);
$stmt = $pdo->prepare("SELECT SUM(amount) FROM employee_payments WHERE employee_id = ? AND status IN ('Paid', 'Approved') AND payment_type = 'Advance Deduction' AND $dateClause");
$stmt->execute([$emp['id']]);
$totalEarned -= $stmt->fetchColumn() ?: 0;
// Get Active Tasks Count
$stmt = $pdo->prepare("
    SELECT (
        (SELECT COUNT(*) 
         FROM orders
         WHERE assigned_employee_id = ?
         AND order_status NOT IN ('delivered', 'cancelled')
         AND is_deleted = 0)

        +

        (SELECT COUNT(*) 
         FROM customer_orders
         WHERE assigned_employee_id = ?
         AND status NOT IN ('delivered', 'cancelled')
         AND is_deleted = 0)
    ) as total_tasks
");

$stmt->execute([
    $emp['id'],
    $emp['id']
]);

$activeTasksCount = $stmt->fetchColumn();


// Check for today's holiday
$stmt = $pdo->prepare("SELECT * FROM holidays WHERE holiday_date = CURRENT_DATE()");
$stmt->execute();
$today_holiday = $stmt->fetch();

$pageTitle = "Employee Dashboard - Sogasu";
$headerTitle = "My Portal";
$activePage = "dashboard";

// Handle OT Submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_ot') {
    $date = $_POST['ot_date'];
    $hours = (float)$_POST['hours'];
    $desc = $_POST['description'];
    
    // Fetch Rate for specific date range first (Prioritize specific/shorter ranges)
    $stmt = $pdo->prepare("SELECT ot_percentage FROM ot_rate_settings 
                           WHERE ? BETWEEN from_date AND to_date 
                           ORDER BY (to_date - from_date) ASC, id DESC 
                           LIMIT 1");
    $stmt->execute([$date]);
    $rate = $stmt->fetchColumn();
    
    if (!$rate) {
        // Fallback to Global OT Rate
        $rate = $pdo->query("SELECT setting_value FROM global_settings WHERE setting_key = 'global_ot_rate'")->fetchColumn() ?: 100;
    }
    
    $salaryStmt = $pdo->prepare("SELECT base_salary FROM employees WHERE id = ?");
    $salaryStmt->execute([$employee_id]);
    $salaryAmount = floatval($salaryStmt->fetchColumn() ?: 0);

    // OT payout calculates hourly pay from monthly salary and adds the OT premium.
    $hourlyRate = $salaryAmount / 30 / 8;
    $baseAmount = $hourlyRate * $hours;
    $amount = $baseAmount + ($baseAmount * $rate / 100);
    
    $stmt = $pdo->prepare("INSERT INTO employee_overtime (employee_id, ot_date, hours, amount, description, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
    if ($stmt->execute([$employee_id, $date, $hours, $amount, $desc])) {
        header("Location: dashboard.php?ot_success=1");
        exit;
    }
}

// Fetch OT History for this employee
$otStmt = $pdo->prepare("SELECT * FROM employee_overtime WHERE employee_id = ? ORDER BY ot_date DESC LIMIT 10");
$otStmt->execute([$employee_id]);
$ot_history = $otStmt->fetchAll();

include 'includes/header.php';
?>

<div class="container" style="padding-bottom: 100px;">
    <?php if ($today_holiday): ?>
        <div style="background: linear-gradient(135deg, #fdf2f8, #fbcfe8); border: 1px solid #db2777; border-radius: 16px; padding: 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 1rem; box-shadow: 0 4px 12px rgba(219, 39, 119, 0.1);">
            <div style="width: 48px; height: 48px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #db2777; font-size: 1.5rem;">
                <i class="ri-calendar-check-line"></i>
            </div>
            <div>
                <div style="font-weight: 800; color: #9d174d; font-size: 1.1rem;"><?= htmlspecialchars($today_holiday['name']) ?></div>
                <div style="font-size: 0.85rem; color: #db2777; font-weight: 600;">Today is a <?= $today_holiday['type'] ?>! Enjoy your day.</div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Top Summary Card -->
    <div class="card" style="background: linear-gradient(135deg, #db2777, #9d174d); border: none; padding: 1.5rem; color: white; border-radius: 20px; position: relative; overflow: hidden; margin-bottom: 1.5rem;">
        <div style="font-size: 0.8rem; opacity: 0.8; margin-bottom: 0.25rem;">Total Earned (<?= htmlspecialchars($periodLabel) ?>)</div>
        <div style="font-size: 2rem; font-weight: 800; margin-bottom: 1rem;">₹ <?= number_format($totalEarned, 0) ?></div>
        
        <div style="display: flex; justify-content: space-between; align-items: flex-end;">
            <div>
                 <div style="font-size: 0.75rem; opacity: 0.8; margin-bottom: 0.1rem;">Active Tasks</div>
                 <div style="font-size: 1.1rem; font-weight: 700;"><?= $activeTasksCount ?> Jobs</div>
            </div>
            <button onclick="window.location.href='tasks.php'" style="background: rgba(255, 255, 255, 0.2); border: 1px solid rgba(255, 255, 255, 0.3); color: white; padding: 0.4rem 0.8rem; border-radius: 8px; font-size: 0.75rem; font-weight: 600;">View All <i class="ri-arrow-right-s-line"></i></button>
        </div>
        
        <!-- Decoration -->
        <i class="ri-wallet-3-line" style="position: absolute; right: -10px; top: -10px; font-size: 5rem; opacity: 0.15; transform: rotate(-15deg);"></i>
    </div>


    <!-- Active Tasks Section -->
    <div class="section-title">
        <span>Current Workload</span>
        <span style="font-size: 0.75rem; background: #fee2e2; color: #ef4444; padding: 2px 8px; border-radius: 4px; font-weight: 700;">URGENT</span>
    </div>

    <?php if (empty($active_tasks)): ?>
        <div class="card" style="text-align: center; padding: 3rem 1.5rem; border-style: dashed;">
            <div style="width: 60px; height: 60px; background: #f0fdf4; color: #22c55e; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                <i class="ri-checkbox-circle-fill" style="font-size: 2rem;"></i>
            </div>
            <div style="font-weight: 700; color: #1e293b; margin-bottom: 0.25rem;">All Caught Up!</div>
            <div style="font-size: 0.85rem; color: #64748b;">No pending tasks assigned to you.</div>
        </div>
    <?php else: ?>
        <?php foreach ($active_tasks as $job): ?>
            <div class="card" onclick="window.location.href='task-detail.php?id=<?= $job['id'] ?>'" style="padding: 0; overflow: hidden; position: relative;">
                <div style="display: flex;">
                    <!-- Image Preview -->
                    <div style="width: 90px; height: 110px; flex-shrink: 0;">
                        <?php 
                        $displayImg = !empty($job['fabric_img']) ? '../'.$job['fabric_img'] : (!empty($job['garment_img']) ? '../admin/uploads/'.$job['garment_img'] : null);
                        if ($displayImg): ?>
                            <img src="<?= $displayImg ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: #94a3b8;">
                                <i class="ri-image-line" style="font-size: 1.5rem;"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Details -->
                    <div style="padding: 1rem; flex-grow: 1;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.25rem;">
                            <div style="font-weight: 700; color: #1e293b;"><?= htmlspecialchars($job['garment']) ?></div>
                            <span style="font-size: 0.65rem; font-weight: 700; color: #4338ca; background: #e0e7ff; padding: 2px 6px; border-radius: 4px;">#<?= $job['order_code'] ?></span>
                        </div>
                        <div style="font-size: 0.8rem; color: #64748b; margin-bottom: 0.75rem;"><?= htmlspecialchars($job['cust_first'] . ' ' . $job['cust_last']) ?></div>
                        
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div style="display: flex; align-items: center; gap: 0.25rem; font-size: 0.75rem; font-weight: 600; color: <?= (strtotime($job['due_date']) < time()) ? '#ef4444' : '#64748b' ?>;">
                                <i class="ri-time-line"></i>
                                <?= date('d M', strtotime($job['due_date'])) ?>
                                <?= (strtotime($job['due_date']) < time()) ? '(Overdue)' : '' ?>
                            </div>
                            <div style="font-size: 0.75rem; font-weight: 700; color: <?= $job['order_status'] == 'processing' ? '#db2777' : '#94a3b8' ?>;">
                                <?= $job['order_status'] == 'processing' ? 'IN PROGRESS' : 'READY TO START' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="section-title" style="margin-top: 1.5rem;">Quick Tools</div>
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem;">
        <button onclick="window.location.href='attendance.php'" style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 0.5rem; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; cursor: pointer;">
            <div style="width: 36px; height: 36px; background: #ecfdf5; color: #059669; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                <i class="ri-fingerprint-line" style="font-size: 1.2rem;"></i>
            </div>
            <span style="font-size: 0.75rem; font-weight: 600; color: #475569;">Attendance</span>
        </button>

        <button onclick="window.location.href='roster.php'" style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 0.5rem; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; cursor: pointer;">
            <div style="width: 36px; height: 36px; background: #fffbeb; color: #d97706; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                <i class="ri-calendar-event-line" style="font-size: 1.2rem;"></i>
            </div>
            <span style="font-size: 0.75rem; font-weight: 600; color: #475569;">Shift</span>
        </button>

        <button onclick="window.location.href='holidays.php'" style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 0.5rem; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; cursor: pointer;">
            <div style="width: 36px; height: 36px; background: #fef2f2; color: #dc2626; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                <i class="ri-sun-line" style="font-size: 1.2rem;"></i>
            </div>
            <span style="font-size: 0.75rem; font-weight: 600; color: #475569;">Holidays</span>
        </button>

        <button onclick="document.getElementById('add-ot-modal').style.display = 'flex';" style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 0.5rem; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; cursor: pointer;">
            <div style="width: 36px; height: 36px; background: #fdf2f8; color: #db2777; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                <i class="ri-timer-flash-line" style="font-size: 1.2rem;"></i>
            </div>
            <span style="font-size: 0.75rem; font-weight: 600; color: #475569;">Add OT</span>
        </button>
        
        <button onclick="window.location.href='earnings.php'" style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 0.5rem; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; cursor: pointer;">
            <div style="width: 36px; height: 36px; background: #f0fdf4; color: #166534; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                <i class="ri-wallet-3-line" style="font-size: 1.2rem;"></i>
            </div>
            <span style="font-size: 0.75rem; font-weight: 600; color: #475569;">Earnings</span>
        </button>

        <button onclick="window.location.href='leaves.php'" style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 0.5rem; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; cursor: pointer;">
            <div style="width: 36px; height: 36px; background: #e0e7ff; color: #4338ca; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                <i class="ri-umbrella-line" style="font-size: 1.2rem;"></i>
            </div>
            <span style="font-size: 0.75rem; font-weight: 600; color: #475569;">Leaves</span>
        </button>

        <button onclick="window.location.href='tasks.php'" style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 0.5rem; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; cursor: pointer;">
            <div style="width: 36px; height: 36px; background: #eff6ff; color: #1e40af; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                <i class="ri-history-line" style="font-size: 1.2rem;"></i>
            </div>
            <span style="font-size: 0.75rem; font-weight: 600; color: #475569;">History</span>
        </button>
    </div>

    <!-- My OT History -->
    <div style="margin-top: 2.5rem; margin-bottom: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="font-size: 1.1rem; font-weight: 800; color: #1e293b; margin: 0;">My Overtime (OT)</h3>
            <span style="font-size: 0.75rem; color: #64748b;">Recent logs</span>
        </div>
        
        <?php if (empty($ot_history)): ?>
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 16px; padding: 2rem; text-align: center; border-style: dashed;">
                <p style="color: #94a3b8; font-size: 0.85rem; margin: 0;">No OT history found.</p>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <?php foreach ($ot_history as $h): 
                    $statusColor = $h['status'] == 'Approved' ? '#059669' : ($h['status'] == 'Pending' ? '#d97706' : '#e11d48');
                    $statusBg = $h['status'] == 'Approved' ? '#dcfce7' : ($h['status'] == 'Pending' ? '#fffbeb' : '#fff1f2');
                ?>
                    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 16px; padding: 1.25rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                        <div>
                            <div style="font-size: 0.95rem; font-weight: 700; color: #1e293b;"><?= date('d M, Y', strtotime($h['ot_date'])) ?></div>
                            <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.2rem;"><?= htmlspecialchars($h['description']) ?></div>
                            <div style="font-size: 0.75rem; font-weight: 700; color: #059669; margin-top: 0.4rem;">₹<?= number_format($h['amount'], 0) ?></div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 0.95rem; font-weight: 800; color: #1e293b;"><?= number_format($h['hours'], 1) ?> <span style="font-size: 0.75rem; font-weight: 400; color: #94a3b8;">Hrs</span></div>
                            <span style="display: inline-block; margin-top: 0.5rem; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; padding: 0.2rem 0.6rem; border-radius: 6px; background: <?= $statusBg ?>; color: <?= $statusColor ?>;">
                                <?= $h['status'] ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Add OT Modal (Simplified for Mobile) -->
<div id="add-ot-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; align-items: flex-end;">
    <div style="background: white; width: 100%; border-radius: 24px 24px 0 0; padding: 1.5rem; animation: slideUp 0.3s ease-out;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: #1e293b;">Log Overtime</h3>
            <button onclick="document.getElementById('add-ot-modal').style.display = 'none';" style="border: none; background: #f1f5f9; width: 32px; height: 32px; border-radius: 50%; color: #64748b;">&times;</button>
        </div>
        <form id="add-ot-form" method="POST">
            <input type="hidden" name="action" value="add_ot">
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #64748b; margin-bottom: 0.5rem;">Date</label>
                <input type="date" name="ot_date" value="<?= date('Y-m-d') ?>" required style="width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 10px; outline: none; background: #f8fafc;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #64748b; margin-bottom: 0.5rem;">Hours Worked</label>
                <input type="number" name="hours" step="0.5" required placeholder="e.g. 2.0" style="width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 10px; outline: none;">
            </div>
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #64748b; margin-bottom: 0.5rem;">Description</label>
                <textarea name="description" required placeholder="What did you work on?" rows="3" style="width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 10px; outline: none; resize: none;"></textarea>
            </div>
            <button type="submit" style="width: 100%; background: #4338ca; color: white; border: none; padding: 1rem; border-radius: 12px; font-weight: 700; font-size: 1rem;">Submit Request</button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if (isset($_GET['ot_success'])): ?>
<script>
    Swal.fire({ icon: 'success', title: 'Submitted!', text: 'Your OT request has been sent for approval.', timer: 2000, showConfirmButton: false });
    // Clean URL to prevent repeat on refresh
    if (window.history.replaceState) {
        const url = new URL(window.location);
        url.searchParams.delete('ot_success');
        window.history.replaceState({}, '', url);
    }
</script>
<?php endif; ?>

<style>
    @keyframes slideUp {
        from { transform: translateY(100%); }
        to { transform: translateY(0); }
    }
    .btn-action:active { transform: scale(0.98); }
</style>

<script>
</script>

<?php include 'includes/bottom-nav.php'; ?>
