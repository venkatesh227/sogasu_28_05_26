<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

// Verify current user is a Supervisor
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id, job_role FROM employees WHERE user_id = ? AND is_deleted = 0");
$stmt->execute([$user_id]);
$current_emp = $stmt->fetch();
$current_role = $current_emp['job_role'] ?? '';

if ($current_role !== 'Supervisor') {
    echo "Access denied. Only supervisors can access this screen.";
    exit();
}

$staff_id = $_GET['id'] ?? null;
if (!$staff_id) {
    header("Location: dashboard.php");
    exit();
}

// Fetch staff details
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND is_deleted = 0");
$stmt->execute([$staff_id]);
$staff = $stmt->fetch();

if (!$staff) {
    echo "Staff member not found.";
    exit();
}

// Fetch all orders assigned to this employee
$stmt = $pdo->prepare("
    SELECT o.*, c.first_name as cust_first, c.last_name as cust_last, sc.name as garment, r.rack_name
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.id
    LEFT JOIN sub_categories sc ON o.sub_category_id = sc.id
    LEFT JOIN racks r ON o.rack_id = r.id
    WHERE o.assigned_employee_id = ? AND o.is_deleted = 0
    ORDER BY o.due_date ASC, o.id DESC
");
$stmt->execute([$staff_id]);
$all_tasks = $stmt->fetchAll();

// Split into Active and Completed
$active_tasks = array_filter($all_tasks, function($t) { return !in_array($t['order_status'], ['completed', 'delivered', 'cancelled']); });
$completed_tasks = array_filter($all_tasks, function($t) { return in_array($t['order_status'], ['completed', 'delivered', 'cancelled']); });

$pageTitle = "Staff Task List - Sogasu";
$headerTitle = "Staff Workspace";
$activePage = "dashboard";
include 'includes/header.php';
?>

<div style="background: var(--surface); padding: 1.25rem; border-bottom: 1px solid var(--border); position: sticky; top: 60px; z-index: 40;">
    <div style="display: flex; align-items: center; gap: 1rem;">
        <img src="https://ui-avatars.com/api/?name=<?= urlencode($staff['first_name']) ?>&background=eef2ff&color=4338ca&bold=true" style="width: 52px; height: 52px; border-radius: 50%; border: 2px solid var(--border); object-fit: cover;">
        <div>
            <h2 style="font-size: 1.2rem; font-weight: 800; color: var(--text-main); letter-spacing: -0.02em;">
                <?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?>
            </h2>
            <div style="display: inline-flex; align-items: center; gap: 0.25rem; margin-top: 0.15rem; font-size: 0.7rem; font-weight: 700; background: var(--primary-light); color: var(--primary); padding: 0.15rem 0.45rem; border-radius: 999px; text-transform: uppercase;">
                <?= htmlspecialchars($staff['job_role'] ?: 'Staff') ?>
            </div>
        </div>
    </div>
</div>

<div class="container" style="padding-bottom: 100px;">
    
    <!-- Stats Cards Grid -->
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; margin-bottom: 1.5rem;">
        <div class="card" style="margin-bottom: 0; padding: 0.75rem; text-align: center; background: #eff6ff; border-color: #bfdbfe;">
            <div style="font-size: 0.7rem; color: #1e40af; font-weight: 600; margin-bottom: 0.25rem;">Assigned</div>
            <div style="font-size: 1.25rem; font-weight: 800; color: #1e40af;"><?= count($all_tasks) ?></div>
        </div>
        <div class="card" style="margin-bottom: 0; padding: 0.75rem; text-align: center; background: #fff7ed; border-color: #fed7aa;">
            <div style="font-size: 0.7rem; color: #9a3412; font-weight: 600; margin-bottom: 0.25rem;">Pending</div>
            <div style="font-size: 1.25rem; font-weight: 800; color: #9a3412;"><?= count($active_tasks) ?></div>
        </div>
        <div class="card" style="margin-bottom: 0; padding: 0.75rem; text-align: center; background: #ecfdf5; border-color: #a7f3d0;">
            <div style="font-size: 0.7rem; color: #065f46; font-weight: 600; margin-bottom: 0.25rem;">Completed</div>
            <div style="font-size: 1.25rem; font-weight: 800; color: #065f46;"><?= count($completed_tasks) ?></div>
        </div>
    </div>

    <!-- Toggle Segments -->
    <div style="display: flex; gap: 0.5rem; margin-bottom: 1.25rem; background: #f1f5f9; padding: 0.25rem; border-radius: 8px;">
        <button id="btnTab1" onclick="switchTaskTab(1)" style="flex: 1; border: none; background: white; color: var(--text-main); font-weight: 700; font-size: 0.8rem; padding: 0.5rem; border-radius: 6px; cursor: pointer; box-shadow: var(--shadow-sm); transition: all 0.2s;">
            Active Tasks (<?= count($active_tasks) ?>)
        </button>
        <button id="btnTab2" onclick="switchTaskTab(2)" style="flex: 1; border: none; background: transparent; color: #64748b; font-weight: 600; font-size: 0.8rem; padding: 0.5rem; border-radius: 6px; cursor: pointer; transition: all 0.2s;">
            Completed (<?= count($completed_tasks) ?>)
        </button>
    </div>

    <!-- Active Tasks Tab -->
    <div id="tabContainer1" style="display: block;">
        <?php if (empty($active_tasks)): ?>
            <div class="card" style="text-align: center; padding: 2.5rem 1.5rem; color: #94a3b8; border-style: dashed;">
                <i class="ri-checkbox-circle-line" style="font-size: 2.5rem; color: #10b981; display: block; margin-bottom: 0.75rem;"></i>
                <div style="font-weight: 700; color: #1e293b; margin-bottom: 0.25rem;">All Caught Up!</div>
                <p style="font-size: 0.8rem; color: #64748b;">No active tasks assigned to this employee.</p>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <?php foreach ($active_tasks as $task): ?>
                    <div class="card" onclick="window.location.href='task-detail.php?id=<?= $task['id'] ?>'" style="padding: 1rem; cursor: pointer; transition: transform 0.15s active;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                            <div>
                                <div style="font-weight: 700; font-size: 0.95rem; color: #1e293b;">
                                    <?= htmlspecialchars($task['garment']) ?>
                                </div>
                                <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px;">
                                    #<?= $task['order_code'] ?> • <?= htmlspecialchars($task['cust_first'] . ' ' . $task['cust_last']) ?>
                                </div>
                            </div>
                            <span class="badge <?= strtolower($task['order_status']) ?>" style="font-size: 0.65rem; padding: 0.2rem 0.5rem;">
                                <?= ucfirst($task['order_status']) ?>
                            </span>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid #f1f5f9; font-size: 0.75rem; color: #64748b;">
                            <span style="display: flex; align-items: center; gap: 0.25rem; font-weight: 600; color: <?= (strtotime($task['due_date']) < time()) ? '#ef4444' : '#64748b' ?>;">
                                <i class="ri-time-line"></i> Due: <?= date('d M', strtotime($task['due_date'])) ?>
                            </span>
                            <span style="display: flex; align-items: center; gap: 0.25rem;">
                                <i class="ri-archive-line"></i> Rack: <?= $task['rack_name'] ? htmlspecialchars($task['rack_name']) : '<span style="color:#ef4444;">None</span>' ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Completed Tasks Tab -->
    <div id="tabContainer2" style="display: none;">
        <?php if (empty($completed_tasks)): ?>
            <div class="card" style="text-align: center; padding: 2.5rem 1.5rem; color: #94a3b8; border-style: dashed;">
                <i class="ri-history-line" style="font-size: 2.5rem; color: #94a3b8; display: block; margin-bottom: 0.75rem;"></i>
                <div style="font-weight: 700; color: #1e293b; margin-bottom: 0.25rem;">No History</div>
                <p style="font-size: 0.8rem; color: #64748b;">No completed orders recorded yet.</p>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <?php foreach ($completed_tasks as $task): ?>
                    <div class="card" onclick="window.location.href='task-detail.php?id=<?= $task['id'] ?>'" style="padding: 1rem; cursor: pointer; opacity: 0.85;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                            <div>
                                <div style="font-weight: 700; font-size: 0.95rem; color: #1e293b; text-decoration: line-through;">
                                    <?= htmlspecialchars($task['garment']) ?>
                                </div>
                                <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px;">
                                    #<?= $task['order_code'] ?> • <?= htmlspecialchars($task['cust_first'] . ' ' . $task['cust_last']) ?>
                                </div>
                            </div>
                            <span class="badge completed" style="font-size: 0.65rem; padding: 0.2rem 0.5rem; background: #e2e8f0; color: #475569; border-color: #cbd5e1;">
                                Delivered
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Fixed Back Floating Button -->
<a href="dashboard.php" style="position: fixed; bottom: 90px; left: 1.25rem; width: 48px; height: 48px; background: var(--text-main); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 6px rgba(0,0,0,0.2); text-decoration: none; z-index: 100;">
    <i class="ri-arrow-left-line" style="font-size: 1.5rem;"></i>
</a>

<script>
function switchTaskTab(tabIndex) {
    const tab1 = document.getElementById('tabContainer1');
    const tab2 = document.getElementById('tabContainer2');
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
</script>

<?php include 'includes/bottom-nav.php'; ?>
