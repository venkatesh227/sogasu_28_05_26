<?php
// supervisor-dashboard.php
// This file is included in dashboard.php when role is Supervisor

// Fetch summary stats for Supervisor
$user_id = $_SESSION['user_id'] ?? 0;

$stmt = $pdo->prepare("SELECT id FROM employees WHERE user_id = ?");
$stmt->execute([$user_id]);

$employeeData = $stmt->fetch();

$employee_id = $employeeData['id'] ?? 0;
$stmt = $pdo->prepare("
    SELECT (
        (SELECT COUNT(*) 
         FROM orders 
         WHERE supervisor_id = ?
         AND order_status NOT IN ('completed', 'delivered', 'cancelled')
         AND is_deleted = 0)

        +

        (SELECT COUNT(*) 
         FROM customer_orders 
         WHERE supervisor_id = ?
         AND status NOT IN ('completed', 'delivered', 'cancelled')
         AND is_deleted = 0)
    ) as total_count
");

$stmt->execute([
    $employee_id,
    $employee_id
]);
$total_assigned = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT (
        (SELECT COUNT(*) 
         FROM orders 
         WHERE supervisor_id = ? 
         AND assigned_employee_id IS NULL 
         AND is_deleted = 0)

        +

        (SELECT COUNT(*) 
         FROM customer_orders 
         WHERE supervisor_id = ? 
         AND assigned_employee_id IS NULL 
         AND is_deleted = 0)
    ) as total_pending
");

$stmt->execute([$employee_id,$employee_id]);
$needs_delegation = $stmt->fetchColumn();

// Fetch orders assigned to this supervisor
$stmt = $pdo->prepare("

    SELECT 
        o.id,
        o.order_code,
        o.assigned_employee_id,
        o.order_status,
        o.total_amount,
        o.created_at,
        c.first_name as cust_first,
        c.last_name as cust_last,
        sc.name as garment,
        r.rack_name,
        e.first_name as emp_first
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.id
    LEFT JOIN sub_categories sc ON o.sub_category_id = sc.id
    LEFT JOIN racks r ON o.rack_id = r.id
    LEFT JOIN employees e ON o.assigned_employee_id = e.id
    WHERE o.supervisor_id = ? 
    AND o.is_deleted = 0

    UNION ALL

    SELECT 
        co.id,
        co.order_code,
        co.assigned_employee_id,
        co.status as order_status,
        co.total_amount,
        co.created_at,
        cu.first_name as cust_first,
        cu.last_name as cust_last,
        sc.name as garment,
        r.rack_name,
        e.first_name as emp_first
    FROM customer_orders co
    LEFT JOIN customers cu ON co.user_id = cu.user_id
    LEFT JOIN sub_categories sc ON co.sub_category_id = sc.id
    LEFT JOIN racks r ON co.rack_id = r.id
    LEFT JOIN employees e ON co.assigned_employee_id = e.id
    WHERE co.supervisor_id = ?
    AND co.is_deleted = 0

    ORDER BY id DESC
");
$stmt->execute([
    $employee_id,
    $employee_id
]);
$my_orders = $stmt->fetchAll();

// Fetch all employees for delegation (excluding self)
$stmt = $pdo->prepare("
    SELECT id, first_name, last_name
    FROM employees
    WHERE is_deleted = 0
    AND status = 1
    AND (
    supervisor_id = ?
    OR id = ?
)
    AND id != ?
");
$stmt->execute([
    $employee_id,
    $employee_id,
    $employee_id
]);
$all_employees = $stmt->fetchAll();

// Fetch all racks
$stmt = $pdo->query("SELECT * FROM racks");
$all_racks = $stmt->fetchAll();

// Fetch Employee Workload for Chart
$workloadStmt = $pdo->prepare("
    SELECT 
        e.first_name,

        (
            COUNT(DISTINCT o.id)
            +
            COUNT(DISTINCT co.id)
        ) as order_count

    FROM employees e

    LEFT JOIN orders o 
        ON e.id = o.assigned_employee_id
        AND o.order_status NOT IN ('completed', 'delivered', 'cancelled')
        AND o.is_deleted = 0

    LEFT JOIN customer_orders co
        ON e.id = co.assigned_employee_id
        AND co.status NOT IN ('completed', 'delivered', 'cancelled')
        AND co.is_deleted = 0

    WHERE e.status = 1
    AND e.is_deleted = 0
    AND e.job_role != 'Supervisor'

    AND (
        e.supervisor_id = ?
        OR e.id = ?
    )

    GROUP BY e.id
    ORDER BY order_count DESC
    LIMIT 6
");

$workloadStmt->execute([
    $employee_id,
    $employee_id
]);

$workloadData = $workloadStmt->fetchAll(PDO::FETCH_ASSOC);
$chartLabels = array_column($workloadData, 'first_name');
$chartCounts = array_column($workloadData, 'order_count');

// Fetch Detailed Employee Workload statistics
$empWorkloadStmt = $pdo->query("
    SELECT e.id, e.first_name, e.last_name, e.job_role,
           COUNT(o.id) as total_assigned,
           SUM(CASE WHEN o.order_status NOT IN ('completed', 'delivered', 'cancelled') AND o.order_status IS NOT NULL THEN 1 ELSE 0 END) as pending_tasks
    FROM employees e
    LEFT JOIN orders o ON e.id = o.assigned_employee_id AND o.is_deleted = 0
    WHERE e.status = 1 AND e.is_deleted = 0 AND e.job_role != 'Supervisor'
    GROUP BY e.id
    ORDER BY pending_tasks DESC
");
$employee_workloads = $empWorkloadStmt->fetchAll();

// Fetch Overall Order Status Counts for Supervisor
$statusCountsStmt = $pdo->prepare("
    SELECT 
        (
            (SELECT COUNT(*) 
             FROM orders
             WHERE supervisor_id = ?
             AND assigned_employee_id IS NULL
             AND is_deleted = 0)

            +

            (SELECT COUNT(*) 
             FROM customer_orders
             WHERE supervisor_id = ?
             AND assigned_employee_id IS NULL
             AND is_deleted = 0)
        ) as pending_delegation,

        (
            (SELECT COUNT(*) 
             FROM orders
             WHERE supervisor_id = ?
             AND order_status IN ('pending', 'processing', 'pattern_making', 'cutting', 'embroidery', 'stitching', 'finishing')
             AND assigned_employee_id IS NOT NULL
             AND is_deleted = 0)

            +

            (SELECT COUNT(*) 
             FROM customer_orders
             WHERE supervisor_id = ?
             AND status IN ('pending', 'processing', 'pattern_making', 'cutting', 'embroidery', 'stitching', 'finishing')
             AND assigned_employee_id IS NOT NULL
             AND is_deleted = 0)
        ) as in_progress,

        (
            (SELECT COUNT(*) 
             FROM orders
             WHERE supervisor_id = ?
             AND order_status IN ('ready', 'completed')
             AND is_deleted = 0)

            +

            (SELECT COUNT(*) 
             FROM customer_orders
             WHERE supervisor_id = ?
             AND status IN ('ready', 'completed')
             AND is_deleted = 0)
        ) as ready_completed,

        (
            (SELECT COUNT(*) 
             FROM orders
             WHERE supervisor_id = ?
             AND order_status = 'delivered'
             AND is_deleted = 0)

            +

            (SELECT COUNT(*) 
             FROM customer_orders
             WHERE supervisor_id = ?
             AND status = 'delivered'
             AND is_deleted = 0)
        ) as delivered
");
$statusCountsStmt->execute([
    $employee_id,
    $employee_id,
    $employee_id,
    $employee_id,
    $employee_id,
    $employee_id,
    $employee_id,
    $employee_id
]);
$status_counts = $statusCountsStmt->fetch();

// Fetch Reported Issues
$issueStmt = $pdo->query("
    SELECT oi.*, o.order_code, o.id as ord_id, e.first_name as emp_first, sc.name as garment
    FROM order_issues oi
    JOIN orders o ON oi.order_id = o.id
    JOIN employees e ON oi.employee_id = e.id
    LEFT JOIN sub_categories sc ON o.sub_category_id = sc.id
    WHERE oi.status = 'open'
    ORDER BY oi.created_at DESC
");
$open_issues = $issueStmt->fetchAll();
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_ot') {
    $date = $_POST['ot_date'];
    $hours = (float) $_POST['hours'];
    $desc = $_POST['description'];

    $stmt = $pdo->prepare("SELECT ot_percentage FROM ot_rate_settings 
                           WHERE ? BETWEEN from_date AND to_date 
                           ORDER BY (to_date - from_date) ASC, id DESC 
                           LIMIT 1");
    $stmt->execute([$date]);
    $rate = $stmt->fetchColumn();

    if (!$rate) {
        $rate = $pdo->query("SELECT setting_value FROM global_settings WHERE setting_key = 'global_ot_rate'")->fetchColumn() ?: 100;
    }

    $salaryStmt = $pdo->prepare("SELECT base_salary FROM employees WHERE id = ?");
    $salaryStmt->execute([$employee_id]);
    $salaryAmount = floatval($salaryStmt->fetchColumn() ?: 0);

    $amount = ($salaryAmount * $rate) / 100;

    $stmt = $pdo->prepare("INSERT INTO employee_overtime (employee_id, ot_date, hours, amount, description, status) VALUES (?, ?, ?, ?, ?, 'Pending')");

    if ($stmt->execute([$employee_id, $date, $hours, $amount, $desc])) {
        header("Location: dashboard.php?ot_success=1");
        exit;
    }
}
// Fetch Supervisor Earnings Stats
$currentMonth = date('Y-m');
$totalEarned = 0;

// Get Salary/Payments
$stmt = $pdo->prepare("SELECT SUM(amount) FROM employee_payments WHERE employee_id = ? AND status = 'Paid' AND payment_date LIKE ? AND payment_type != 'Advance Deduction'");
$stmt->execute([$employee_id, $currentMonth . '%']);
$totalEarned += $stmt->fetchColumn() ?: 0;

// Subtract Deductions
$stmt = $pdo->prepare("SELECT SUM(amount) FROM employee_payments WHERE employee_id = ? AND status = 'Paid' AND payment_date LIKE ? AND payment_type = 'Advance Deduction'");
$stmt->execute([$employee_id, $currentMonth . '%']);
$totalEarned -= $stmt->fetchColumn() ?: 0;

// Add Approved OT
$stmt = $pdo->prepare("SELECT SUM(amount) FROM employee_overtime WHERE employee_id = ? AND status = 'Approved' AND ot_date LIKE ?");
$stmt->execute([$employee_id, $currentMonth . '%']);
$totalEarned += $stmt->fetchColumn() ?: 0;

$pageTitle = "Supervisor Dashboard - Sogasu";
$headerTitle = "Supervisor Panel";
$activePage = "dashboard";
include 'includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://unpkg.com/html5-qrcode"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if (isset($_SESSION['delegate_success'])): ?>

    <script>
        document.addEventListener("DOMContentLoaded", function () {

            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: '<?= $_SESSION['delegate_success']; ?>',
                confirmButtonColor: '#db2777'
            });

        });
    </script>

    <?php unset($_SESSION['delegate_success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['rack_success'])): ?>

    <script>
        document.addEventListener("DOMContentLoaded", function () {

            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: '<?= $_SESSION['rack_success']; ?>',
                confirmButtonColor: '#db2777'
            });

        });
    </script>

    <?php unset($_SESSION['rack_success']); ?>
<?php endif; ?>

<?php
// Check for today's holiday
$h_stmt = $pdo->prepare("SELECT * FROM holidays WHERE holiday_date = CURRENT_DATE()");
$h_stmt->execute();
$today_holiday = $h_stmt->fetch();
?>
<div class="container" style="padding-bottom: 100px;">
    <?php if ($today_holiday): ?>
        <div
            style="background: linear-gradient(135deg, #fdf2f8, #fbcfe8); border: 1px solid #db2777; border-radius: 16px; padding: 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 1rem; box-shadow: 0 4px 12px rgba(219, 39, 119, 0.1);">
            <div
                style="width: 48px; height: 48px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #db2777; font-size: 1.5rem;">
                <i class="ri-calendar-check-line"></i>
            </div>
            <div>
                <div style="font-weight: 800; color: #9d174d; font-size: 1.1rem;">
                    <?= htmlspecialchars($today_holiday['name']) ?>
                </div>
                <div style="font-size: 0.85rem; color: #db2777; font-weight: 600;">Today is a <?= $today_holiday['type'] ?>!
                    Enjoy your day.</div>
            </div>
        </div>
    <?php endif; ?>
    <!-- Top Summary Card for Supervisor -->
    <div class="card"
        style="background: linear-gradient(135deg, #1e293b, #0f172a); border: none; padding: 1.5rem; color: white; border-radius: 20px; position: relative; overflow: hidden; margin-bottom: 1.5rem;">
        <div style="font-size: 0.8rem; opacity: 0.8; margin-bottom: 0.25rem;">Personal Earnings (<?= date('M') ?>)</div>
        <div style="font-size: 2rem; font-weight: 800; margin-bottom: 1rem;">₹ <?= number_format($totalEarned, 0) ?>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: flex-end;">
            <div>
                <div style="font-size: 0.75rem; opacity: 0.8; margin-bottom: 0.1rem;">My Role</div>
                <div style="font-size: 1.1rem; font-weight: 700;">Supervisor</div>
            </div>
            <button onclick="window.location.href='supervisor-earnings.php'"
                style="background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); color: white; padding: 0.4rem 0.8rem; border-radius: 8px; font-size: 0.75rem; font-weight: 600;">Full
                Report <i class="ri-arrow-right-s-line"></i></button>
        </div>

        <!-- Decoration -->
        <i class="ri-shield-user-line"
            style="position: absolute; right: -10px; top: -10px; font-size: 5rem; opacity: 0.1; transform: rotate(-15deg);"></i>
    </div>

    <!-- Quick Tools Section (Added for Supervisor HR) -->
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; margin-bottom: 1.5rem;">
        <button onclick="window.location.href='attendance.php'"
            style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 0.5rem; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; cursor: pointer;">
            <div
                style="width: 36px; height: 36px; background: #ecfdf5; color: #059669; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                <i class="ri-checkbox-circle-line" style="font-size: 1.2rem;"></i>
            </div>
            <span style="font-size: 0.75rem; font-weight: 600; color: #475569;">Attendance</span>
        </button>

        <button onclick="window.location.href='roster.php'"
            style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 0.5rem; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; cursor: pointer;">
            <div
                style="width: 36px; height: 36px; background: #fffbeb; color: #d97706; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                <i class="ri-calendar-event-line" style="font-size: 1.25rem;"></i>
            </div>
            <span style="font-size: 0.75rem; font-weight: 600; color: #475569;">Shift</span>
        </button>

        <button onclick="window.location.href='holidays.php'"
            style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 0.5rem; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; cursor: pointer;">
            <div
                style="width: 36px; height: 36px; background: #fdf2f8; color: #db2777; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                <i class="ri-flag-2-line" style="font-size: 1.25rem;"></i>
            </div>
            <span style="font-size: 0.75rem; font-weight: 600; color: #475569;">Holidays</span>
        </button>
        <button onclick="document.getElementById('add-ot-modal').style.display = 'flex';"
            style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 0.5rem; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; cursor: pointer;">
            <div
                style="width: 36px; height: 36px; background: #fdf2f8; color: #db2777; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                <i class="ri-timer-flash-line" style="font-size: 1.2rem;"></i>
            </div>
            <span style="font-size: 0.75rem; font-weight: 600; color: #475569;">Add OT</span>
        </button>

<button onclick="window.location.href='supervisor-earnings.php'"
            style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 0.5rem; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; cursor: pointer;">
            <div
                style="width: 36px; height: 36px; background: #f0fdf4; color: #166534; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                <i class="ri-wallet-3-line" style="font-size: 1.2rem;"></i>
            </div>
            <span style="font-size: 0.75rem; font-weight: 600; color: #475569;">Earnings</span>
        </button>

        <button onclick="window.location.href='leaves.php'"
            style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 0.5rem; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; cursor: pointer;">
            <div
                style="width: 36px; height: 36px; background: #e0e7ff; color: #4338ca; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                <i class="ri-umbrella-line" style="font-size: 1.2rem;"></i>
            </div>
            <span style="font-size: 0.75rem; font-weight: 600; color: #475569;">Leaves</span>
        </button>
        <button onclick="window.location.href='tasks.php'"
            style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 0.5rem; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; cursor: pointer;">
            <div
                style="width: 36px; height: 36px; background: #eff6ff; color: #1e40af; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                <i class="ri-history-line" style="font-size: 1.2rem;"></i>
            </div>
            <span style="font-size: 0.75rem; font-weight: 600; color: #475569;">History</span>
        </button>
    </div>
    <!-- Issues & Alerts -->
    <?php if (!empty($open_issues)): ?>
        <div class="section-title" style="color: #e11d48;">
            <span><i class="ri-error-warning-fill"></i> Alerts & Issues</span>
            <span
                style="background: #e11d48; color: white; font-size: 0.7rem; padding: 2px 8px; border-radius: 999px;"><?= count($open_issues) ?>
                New</span>
        </div>
        <div style="margin-bottom: 1.5rem;">
            <?php foreach ($open_issues as $issue): ?>
                <div class="card" style="border-left: 4px solid #e11d48; background: #fff1f2; margin-bottom: 0.75rem;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                        <div>
                            <div style="font-weight: 700; color: #9f1239; font-size: 0.95rem;">
                                <?= ucwords(str_replace('_', ' ', $issue['issue_type'])) ?>
                            </div>
                            <div style="font-size: 0.8rem; color: #be123c; margin-top: 2px;">
                                Order #<?= $issue['order_code'] ?> (<?= $issue['garment'] ?>) • By <?= $issue['emp_first'] ?>
                            </div>
                        </div>
                        <span
                            style="font-size: 0.7rem; color: #e11d48; font-weight: 600;"><?= date('h:i A', strtotime($issue['created_at'])) ?></span>
                    </div>
                    <div
                        style="font-size: 0.85rem; color: #4c0519; line-height: 1.4; background: white; padding: 0.75rem; border-radius: 8px; margin-bottom: 0.75rem; border: 1px solid #fecdd3;">
                        "<?= htmlspecialchars($issue['description']) ?>"
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <button onclick="window.location.href='task-detail.php?id=<?= $issue['ord_id'] ?>'"
                            style="flex: 1; background: #9f1239; color: white; border: none; padding: 0.5rem; border-radius: 6px; font-size: 0.8rem; font-weight: 600;">View
                            Order</button>
                        <button onclick="resolveIssue(<?= $issue['id'] ?>)"
                            style="flex: 1; background: white; color: #9f1239; border: 1px solid #9f1239; padding: 0.5rem; border-radius: 6px; font-size: 0.8rem; font-weight: 600;">Mark
                            Resolved</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Supervisor Stats -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
        <div class="card" style="background: #eff6ff; border-color: #bfdbfe; color: #1e40af; margin-bottom: 0;">
            <div style="font-size: 0.75rem; opacity: 0.8; margin-bottom: 0.25rem;">Total Orders</div>
            <div style="font-size: 1.5rem; font-weight: 700;"><?= $total_assigned ?></div>
        </div>
        <div class="card" style="background: #fff7ed; border-color: #fed7aa; color: #9a3412; margin-bottom: 0;">
            <div style="font-size: 0.75rem; opacity: 0.8; margin-bottom: 0.25rem;">To Delegate</div>
            <div style="font-size: 1.5rem; font-weight: 700;"><?= $needs_delegation ?></div>
        </div>
    </div>

    <!-- Employee Work Status Graph & Visual Analytics -->
    <div class="section-title">Visual Analytics</div>
    <div class="card" style="padding: 1rem;">
        <div
            style="display: flex; gap: 0.5rem; margin-bottom: 1rem; background: #f1f5f9; padding: 0.25rem; border-radius: 8px;">
            <button id="btnTab1" onclick="switchChartTab(1)"
                style="flex: 1; border: none; background: white; color: var(--text-main); font-weight: 700; font-size: 0.8rem; padding: 0.5rem; border-radius: 6px; cursor: pointer; box-shadow: var(--shadow-sm); transition: all 0.2s;">Employee
                Workload</button>
            <button id="btnTab2" onclick="switchChartTab(2)"
                style="flex: 1; border: none; background: transparent; color: #64748b; font-weight: 600; font-size: 0.8rem; padding: 0.5rem; border-radius: 6px; cursor: pointer; transition: all 0.2s;">Order
                Status Breakdown</button>
        </div>
        <div id="chartContainer1" style="display: block;">
            <canvas id="workStatusChart" height="200"></canvas>
        </div>
        <div id="chartContainer2" style="display: none;">
            <div style="max-width: 210px; margin: 0 auto; padding: 0.5rem 0;">
                <canvas id="orderStatusChart" height="210"></canvas>
            </div>
        </div>
    </div>

    <!-- Employee Detailed Workload & Pending Tasks Awareness -->
    <div class="section-title">
        <span>Employee Workload Details</span>
        <span style="font-size: 0.75rem; color: #64748b; font-weight: 500;">Real-time stats</span>
    </div>
    <style>
        .employee-wl-card:active {
            transform: scale(0.98);
            background: #f8fafc;
        }
    </style>
    <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1.5rem;">
        <?php foreach ($employee_workloads as $wl): ?>
            <?php
            $wl_total = $wl['total_assigned'];
            $wl_pending = $wl['pending_tasks'];
            $wl_completed = $wl_total - $wl_pending;
            $wl_pct = $wl_total > 0 ? round(($wl_completed / $wl_total) * 100) : 100;
            ?>
            <div class="card employee-wl-card" onclick="window.location.href='staff-tasks.php?id=<?= $wl['id'] ?>'"
                style="margin-bottom: 0; padding: 0.85rem; border-left: 4px solid <?= $wl_pending > 3 ? '#e11d48' : ($wl_pending > 1 ? '#d97706' : '#059669') ?>; cursor: pointer; transition: all 0.2s;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                    <div>
                        <div style="font-weight: 700; color: #1e293b; font-size: 0.9rem;">
                            <?= htmlspecialchars($wl['first_name'] . ' ' . $wl['last_name']) ?>
                        </div>
                        <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;">
                            <?= htmlspecialchars($wl['job_role']) ?>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <span class="badge"
                            style="background: <?= $wl_pending > 0 ? '#fffbeb' : '#ecfdf5' ?>; color: <?= $wl_pending > 0 ? '#b45309' : '#047857' ?>; font-size: 0.7rem; font-weight: 800;">
                            <?= $wl_pending ?> Pending Task<?= $wl_pending != 1 ? 's' : '' ?>
                        </span>
                    </div>
                </div>

                <!-- Progress bar -->
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-top: 0.5rem;">
                    <div
                        style="flex: 1; height: 6px; background: #e2e8f0; border-radius: 999px; overflow: hidden; display: flex;">
                        <div
                            style="width: <?= $wl_pct ?>%; height: 100%; background: var(--primary); border-radius: 999px;">
                        </div>
                    </div>
                    <span
                        style="font-size: 0.75rem; font-weight: 700; color: #475569; min-width: 32px; text-align: right;"><?= $wl_pct ?>%</span>
                </div>

                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.4rem; font-size: 0.75rem; color: #64748b;">
                    <span>Total Assigned: <strong><?= $wl_total ?></strong></span>
                    <span>Completed: <strong><?= $wl_completed ?></strong></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Rack Management Section -->
    <style>
        #racks-preview::-webkit-scrollbar {
            display: none !important;
            height: 0 !important;
            width: 0 !important;
        }

        #racks-preview {
            -ms-overflow-style: none !important;
            /* IE and Edge */
            scrollbar-width: none !important;
            /* Firefox */
        }
    </style>
    <div class="section-title">
        <span>Racks & Storage</span>
        <button onclick="toggleRacks()"
            style="border: none; background: transparent; color: var(--primary); font-size: 0.85rem; font-weight: 600;">View
            All</button>
    </div>
    <div id="racks-preview"
        style="display: flex; gap: 0.75rem; overflow-x: auto; padding-bottom: 0.5rem; margin-bottom: 1rem;">
        <?php foreach ($all_racks as $rack): ?>
            <div class="card"
                style="min-width: 120px; margin-bottom: 0; padding: 0.75rem; text-align: center; border-style: <?= $rack['status'] == 'Available' ? 'dashed' : 'solid' ?>;">
                <i class="ri-archive-line"
                    style="font-size: 1.25rem; color: <?= $rack['status'] == 'Available' ? '#10b981' : '#f59e0b' ?>;"></i>
                <div style="font-size: 0.85rem; font-weight: 600; margin-top: 0.25rem;"><?= $rack['rack_name'] ?></div>
                <div style="font-size: 0.7rem; color: #64748b;"><?= $rack['status'] ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Orders Needing Attention -->
    <div class="section-title">Assigned Orders</div>
    <?php if (empty($my_orders)): ?>
        <div class="card" style="text-align: center; padding: 2rem; color: #94a3b8;">
            No orders assigned to you yet.
        </div>
    <?php else: ?>
        <?php foreach ($my_orders as $order): ?>
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.75rem;">
                    <div>
                        <div style="font-weight: 700; font-size: 1rem;">#<?= $order['order_code'] ?> - <?= $order['garment'] ?>
                        </div>
                        <div style="font-size: 0.8rem; color: #64748b;"><?= $order['cust_first'] ?>         <?= $order['cust_last'] ?>
                        </div>
                    </div>
                    <span class="badge"
                        style="background: <?= $order['assigned_employee_id'] ? '#f0fdf4' : '#fff1f2' ?>; color: <?= $order['assigned_employee_id'] ? '#166534' : '#991b1b' ?>;">
                        <?= $order['assigned_employee_id'] ? 'Delegated' : 'Pending Delegation' ?>
                    </span>
                </div>

                <div
                    style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-bottom: 1rem; font-size: 0.85rem;">
                    <div style="display: flex; align-items: center; gap: 0.4rem; color: #475569;">
                        <i class="ri-user-settings-line"></i>
                        <?= $order['emp_first'] ? $order['emp_first'] : '<span style="color:#ef4444;">Unassigned</span>' ?>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.4rem; color: #475569;">
                        <i class="ri-archive-line"></i>
                        <?= $order['rack_name'] ? $order['rack_name'] : '<span style="color:#ef4444;">No Rack</span>' ?>
                    </div>
                </div>

                <div style="display: flex; gap: 0.5rem;">
                    <button onclick="openDelegateModal(<?= $order['id'] ?>, '<?= $order['order_code'] ?>')" class="btn-action"
                        style="flex: 1; background: var(--primary); color: white; border: none; padding: 0.6rem; border-radius: 8px; font-weight: 600; font-size: 0.85rem;">
                        Delegate
                    </button>
                    <button onclick="openRackModal(<?= $order['id'] ?>, '<?= $order['order_code'] ?>')" class="btn-action"
                        style="flex: 1; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; padding: 0.6rem; border-radius: 8px; font-weight: 600; font-size: 0.85rem;">
                        Assign Rack
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Delegation Modal -->
<div id="delegateModal"
    style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; padding:1rem;">
    <div class="card" style="width:100%; max-width:400px; margin:0;">
        <h3 style="margin-bottom:1rem;">Delegate Order <span id="delegateOrderCode"></span></h3>
        <form action="process-delegation.php" method="POST" novalidate onsubmit="return validateDelegateForm()">
            <input type="hidden" name="order_id" id="delegateOrderId">
            <div style="margin-bottom:1rem;">
                <label style="display:block; font-size:0.85rem; margin-bottom:0.4rem;">Select Employee</label>
                <select name="employee_id" id="employee_select" onchange="fetchWorkload(this.value)"
                    style="width:100%; padding:0.75rem; border-radius:8px; border:1px solid #e2e8f0;">
                    <option value="">Choose employee...</option>
                    <?php foreach ($all_employees as $e): ?>
                        <option value="<?= $e['id'] ?>"><?= $e['first_name'] ?>     <?= $e['last_name'] ?></option>
                    <?php endforeach; ?>
                </select>
                <div id="employeeError" style="color:red; font-size:0.8rem; margin-top:5px;"></div>
            </div>

            <!-- Workload Preview Section -->
            <div id="workloadPreview"
                style="display:none; background: #f8fafc; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid #e2e8f0; font-size: 0.85rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem;">
                    <span style="color: #64748b;">Active Tasks:</span>
                    <span id="previewPending" style="font-weight: 700;">-</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem;">
                    <span style="color: #64748b;">Next Deadline:</span>
                    <span id="previewDeadline" style="font-weight: 700;">-</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #64748b;">Status:</span>
                    <span id="previewStatus"
                        style="font-weight: 800; text-transform: uppercase; font-size: 0.7rem;">-</span>
                </div>
            </div>
            <div style="display:flex; gap:0.5rem;">
                <button type="submit"
                    style="flex:1; background:var(--primary); color:white; border:none; padding:0.75rem; border-radius:8px; font-weight:600;">Assign</button>
                <button type="button" onclick="closeModals()"
                    style="flex:1; background:#f1f5f9; border:none; padding:0.75rem; border-radius:8px;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Rack Assignment Modal -->
<div id="rackModal"
    style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; padding:1rem;">
    <div class="card" style="width:100%; max-width:400px; margin:0;">
        <h3 style="margin-bottom:1rem;">Assign Rack to <span id="rackOrderCode"></span></h3>
        <form action="process-rack-assign.php" method="POST" novalidate onsubmit="return validateRackForm()">
            <input type="hidden" name="order_id" id="rackOrderId">
            <div style="margin-bottom:1rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                    <label style="display:block; font-size:0.85rem;">Select Rack</label>
                    <button type="button" onclick="startScanner()"
                        style="border: none; background: #fdf2f8; color: #db2777; font-size: 0.75rem; font-weight: 600; padding: 4px 8px; border-radius: 4px; display: flex; align-items: center; gap: 4px;">
                        <i class="ri-qr-scan-2-line"></i> Scan Barcode
                    </button>
                </div>

                <!-- Scanner Container -->
                <div id="reader"
                    style="width: 100%; display: none; margin-bottom: 1rem; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0;">
                </div>

                <select id="rackSelect" name="rack_id"
                    style="width:100%; padding:0.75rem; border-radius:8px; border:1px solid #e2e8f0;">
                    <option value="">Choose rack...</option>
                    <?php foreach ($all_racks as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= $r['status'] == 'Occupied' ? 'disabled' : '' ?>>
                            <?= $r['rack_name'] ?> (<?= $r['status'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="rackError" style="color:red; font-size:0.8rem; margin-top:5px;"></div>
            </div>
            <div style="display:flex; gap:0.5rem;">
                <button type="submit"
                    style="flex:1; background:var(--primary); color:white; border:none; padding:0.75rem; border-radius:8px; font-weight:600;">Allocate</button>
                <button type="button" onclick="closeModals()"
                    style="flex:1; background:#f1f5f9; border:none; padding:0.75rem; border-radius:8px;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openDelegateModal(id, code) {
        document.getElementById('delegateOrderId').value = id;
        document.getElementById('delegateOrderCode').innerText = '#' + code;
        document.getElementById('delegateModal').style.display = 'flex';
        // Reset preview
        document.getElementById('employee_select').value = '';
        document.getElementById('workloadPreview').style.display = 'none';
    }

    function fetchWorkload(empId) {
        const preview = document.getElementById('workloadPreview');
        if (!empId) {
            preview.style.display = 'none';
            return;
        }

        fetch(`get-employee-workload.php?id=${empId}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('previewPending').innerText = data.pending_tasks;
                document.getElementById('previewDeadline').innerText = data.next_deadline;

                const statusEl = document.getElementById('previewStatus');
                statusEl.innerText = data.status;
                statusEl.style.color = data.color;

                preview.style.display = 'block';
            })
            .catch(err => console.error('Error fetching workload:', err));
    }

    function openRackModal(id, code) {
        document.getElementById('rackOrderId').value = id;
        document.getElementById('rackOrderCode').innerText = '#' + code;
        document.getElementById('rackModal').style.display = 'flex';
    }

    function closeModals() {
        document.getElementById('delegateModal').style.display = 'none';
        document.getElementById('rackModal').style.display = 'none';
        stopScanner();
    }

    // Barcode Scanner Logic
    let html5QrCode;

    function startScanner() {
        const reader = document.getElementById('reader');
        reader.style.display = 'block';

        html5QrCode = new Html5Qrcode("reader");
        const config = { fps: 10, qrbox: { width: 250, height: 150 } };

        html5QrCode.start({ facingMode: "environment" }, config, onScanSuccess);
    }

    function stopScanner() {
        if (html5QrCode && html5QrCode.isScanning) {
            html5QrCode.stop().then(() => {
                document.getElementById('reader').style.display = 'none';
            });
        }
    }

    function onScanSuccess(decodedText, decodedResult) {
        console.log(`Scan result: ${decodedText}`, decodedResult);

        // Expected format: RACK-X
        if (decodedText.startsWith("RACK-")) {
            const rackId = decodedText.split("-")[1];
            const select = document.getElementById('rackSelect');

            // Check if option exists and is not disabled
            let found = false;
            for (let i = 0; i < select.options.length; i++) {
                if (select.options[i].value == rackId) {
                    if (select.options[i].disabled) {
                        alert("This rack is already occupied!");
                    } else {
                        select.selectedIndex = i;
                        found = true;
                    }
                    break;
                }
            }

            if (found) {
                stopScanner();
                // Optional: Provide feedback
                Swal.fire({
                    icon: 'success',
                    title: 'Rack Detected',
                    text: 'Rack has been selected automatically.',
                    timer: 1500,
                    showConfirmButton: false
                });
            }
        }
    }

    // Chart.js - Tab Switching Logic
    function switchChartTab(tabIndex) {
        const tab1 = document.getElementById('chartContainer1');
        const tab2 = document.getElementById('chartContainer2');
        const btn1 = document.getElementById('btnTab1');
        const btn2 = document.getElementById('btnTab2');

        if (tabIndex === 1) {
            tab1.style.display = 'block';
            tab2.style.display = 'none';
            btn1.style.background = 'white';
            btn1.style.color = 'var(--text-main)';
            btn1.style.fontWeight = '700';
            btn1.style.boxShadow = 'var(--shadow-sm)';

            btn2.style.background = 'transparent';
            btn2.style.color = '#64748b';
            btn2.style.fontWeight = '600';
            btn2.style.boxShadow = 'none';
        } else {
            tab1.style.display = 'none';
            tab2.style.display = 'block';
            btn2.style.background = 'white';
            btn2.style.color = 'var(--text-main)';
            btn2.style.fontWeight = '700';
            btn2.style.boxShadow = 'var(--shadow-sm)';

            btn1.style.background = 'transparent';
            btn1.style.color = '#64748b';
            btn1.style.fontWeight = '600';
            btn1.style.boxShadow = 'none';
        }
    }

    // Chart.js - Employee Work Status
    const ctx = document.getElementById('workStatusChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                label: 'Orders in Progress',
                data: <?= json_encode($chartCounts) ?>,
                backgroundColor: '#db2777',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 },
                    grid: { display: false }
                },
                x: { grid: { display: false } }
            }
        }
    });

    // Chart.js - Overall Order Status Breakdown
    const ctx2 = document.getElementById('orderStatusChart').getContext('2d');
    new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: ['To Delegate', 'In Progress', 'Ready / Completed', 'Delivered'],
            datasets: [{
                data: [
                    <?= (int) ($status_counts['pending_delegation'] ?? 0) ?>,
                    <?= (int) ($status_counts['in_progress'] ?? 0) ?>,
                    <?= (int) ($status_counts['ready_completed'] ?? 0) ?>,
                    <?= (int) ($status_counts['delivered'] ?? 0) ?>
                ],
                backgroundColor: ['#e11d48', '#d97706', '#059669', '#64748b'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        font: { size: 10, weight: 'bold' },
                        color: '#4a044e'
                    }
                }
            },
            cutout: '60%'
        }
    });

    function toggleRacks() {
        window.location.href = 'racks-view.php';
    }

    function resolveIssue(issueId) {
        Swal.fire({
            title: 'Resolve Issue?',
            text: "Are you sure you want to mark this issue as resolved?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#059669',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, Resolve it'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('resolve-issue.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id=' + issueId
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire('Resolved!', 'The issue has been marked as resolved.', 'success')
                                .then(() => location.reload());
                        } else {
                            Swal.fire('Error', data.message || 'Failed to resolve issue', 'error');
                        }
                    });
            }
        });
    }
    function validateDelegateForm() {

        let employee = document.getElementById('employee_select').value;
        let error = document.getElementById('employeeError');

        if (employee == '') {

            error.innerHTML = "Employee field is required";
            return false;

        } else {

            error.innerHTML = "";
            return true;
        }
    }

    function validateRackForm() {

        let rack = document.getElementById('rackSelect').value;
        let error = document.getElementById('rackError');

        if (rack == '') {

            error.innerHTML = "Rack field is required";
            return false;

        } else {

            error.innerHTML = "";
            return true;
        }
    }
</script>
<!-- Add OT Modal -->
<div id="add-ot-modal"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; align-items: flex-end;">
    <div
        style="background: white; width: 100%; border-radius: 24px 24px 0 0; padding: 1.5rem; animation: slideUp 0.3s ease-out;">

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: #1e293b;">Log Overtime</h3>

            <button type="button" onclick="document.getElementById('add-ot-modal').style.display='none';"
                style="border: none; background: #f1f5f9; width: 32px; height: 32px; border-radius: 50%; color: #64748b;">
                &times;
            </button>
        </div>

        <form id="add-ot-form" method="POST" novalidate>

            <input type="hidden" name="action" value="add_ot">

            <div style="margin-bottom: 1rem;">
                <label>Date</label>
                <input type="date" name="ot_date" value="<?= date('Y-m-d') ?>" required
                    style="width:100%;padding:0.75rem;border:1px solid #e2e8f0;border-radius:10px;">
            </div>

            <div style="margin-bottom: 1rem;">
                <label>Hours Worked</label>
                <input type="number" name="hours" step="0.5" required placeholder="e.g. 2.0"
                    style="width:100%;padding:0.75rem;border:1px solid #e2e8f0;border-radius:10px;">
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label>Description</label>
                <textarea name="description" required rows="3" placeholder="What did you work on?"
                    style="width:100%;padding:0.75rem;border:1px solid #e2e8f0;border-radius:10px;"></textarea>
            </div>

            <button type="submit"
                style="width:100%;background:#4338ca;color:white;border:none;padding:1rem;border-radius:12px;font-weight:700;">
                Submit Request
            </button>

        </form>

    </div>
</div>
<?php include 'includes/bottom-nav.php'; ?>