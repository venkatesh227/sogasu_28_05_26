<?php
session_start();
require_once '../includes/db.php';

// Check login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("                 
    SELECT job_role
    FROM employees
    WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);

$role_name = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT permission_key
    FROM role_permissions
    WHERE role_name = ?
");
$stmt->execute([$role_name]);

$permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('employees_tasks_view', $permissions)) {
    header("Location: dashboard.php");
    exit();
}

// ================= GET EMPLOYEE =================
$stmt = $pdo->prepare("SELECT id FROM employees WHERE user_id = ?");
$stmt->execute([$user_id]);
$emp = $stmt->fetch();
$employee_id = $emp['id'] ?? 0;

// ================= STATUS LIST =================
$all_status = [
    'all' => 'All',
    'pending' => 'Pending',
    'processing' => 'Processing',
    'pattern_making' => 'Pattern',
    'cutting' => 'Cutting',
    'embroidery' => 'Embroidery',
    'stitching' => 'Stitching',
    'finishing' => 'Finishing',
    'ready' => 'Ready',
    'completed' => 'Completed',
    'delivered' => 'Delivered'
];

$current_status = $_GET['status'] ?? 'all';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

// ================= FETCH TASKS =================             
$stmt = $pdo->prepare("

    SELECT 
        o.id,
        o.order_code,
        o.order_status,
        o.assigned_employee_id,
        o.supervisor_id,
        o.rack_id,
        o.due_date,
        o.created_at,
        sc.name AS garment,
        sc.image AS garment_img,
        c.first_name,
        c.last_name,
        e.first_name AS emp_first,
        e.last_name AS emp_last,
        e.job_role AS emp_role,
        o.material_image AS fabric_img

    FROM orders o

    LEFT JOIN sub_categories sc 
        ON o.sub_category_id = sc.id

    LEFT JOIN customers c 
        ON o.customer_id = c.id

    LEFT JOIN employees e 
        ON o.assigned_employee_id = e.id

    WHERE (
        o.assigned_employee_id = ? 
        OR o.supervisor_id = ?
    )
    AND o.is_deleted = 0

    UNION ALL

    SELECT 
        co.id,
        co.order_code,
        co.status as order_status,
        co.assigned_employee_id,
        co.supervisor_id,
        co.rack_id,
        co.appointment_date as due_date,
        co.created_at,
        sc.name AS garment,
        sc.image AS garment_img,
        cu.first_name,
        cu.last_name,
        e.first_name AS emp_first,
        e.last_name AS emp_last,
        e.job_role AS emp_role,
        co.material_image as fabric_img

    FROM customer_orders co

    LEFT JOIN sub_categories sc 
        ON co.sub_category_id = sc.id

    LEFT JOIN customers cu 
        ON co.user_id = cu.user_id

    LEFT JOIN employees e 
        ON co.assigned_employee_id = e.id

    WHERE (
        co.assigned_employee_id = ? 
        OR co.supervisor_id = ?
    )
    AND co.is_deleted = 0

    ORDER BY id DESC
");

$stmt->execute([
    $employee_id,
    $employee_id,
    $employee_id,
    $employee_id
]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================== COUNTS ==================
$status_counts = ['all' => count($tasks)];
foreach ($all_status as $key => $label) {
    if ($key != 'all')
        $status_counts[$key] = 0;
}
foreach ($tasks as $t) {
    if (isset($status_counts[$t['order_status']])) {
        $status_counts[$t['order_status']]++;
    }
}

// ================ FILTER TASKS ===============
$filtered_tasks = [];
foreach ($tasks as $task) {
    $task_date = date('Y-m-d', strtotime($task['due_date']));
    if (
        ($current_status == 'all' || $task['order_status'] == $current_status)
        &&
        (empty($from_date) || empty($to_date) || ($task_date >= $from_date && $task_date <= $to_date))
    ) {
        $filtered_tasks[] = $task;
    }
}

$pageTitle = "My Tasks - Sogasu Staff";
$headerTitle = "My Tasks";
$activePage = "tasks";
include 'includes/header.php';
?>

<style>
    .filter-tab {
        text-decoration: none;
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        white-space: nowrap;
        transition: all 0.2s;
        border: 1px solid var(--border);
        color: var(--text-muted);
        background: var(--surface);
    }

    .filter-tab.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        box-shadow: 0 2px 4px rgba(219, 39, 119, 0.2);
    }

    .hide-scrollbar::-webkit-scrollbar {
        display: none;
    }

    .hide-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
</style>

<div
    style="background: var(--surface); padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); position: sticky; top: 60px; z-index: 40; box-shadow: var(--shadow-sm);">


    <!-- Date Filters -->
    <form method="GET" style="display:flex; gap:0.5rem; align-items:flex-end; margin-bottom: 1rem;">
        <input type="hidden" name="status" value="<?= $current_status ?>">
        <div style="flex:1;">
            <label
                style="font-size:0.7rem; font-weight:600; color:var(--text-muted); display:block; margin-bottom:0.25rem;">From</label>
            <input type="date" name="from_date" value="<?= $from_date ?>"
                style="width:100%; padding:0.6rem; border:1px solid var(--border); border-radius:10px; font-size: 0.8rem; outline: none; color: var(--text-main); background: var(--background);">
        </div>
        <div style="flex:1;">
            <label
                style="font-size:0.7rem; font-weight:600; color:var(--text-muted); display:block; margin-bottom:0.25rem;">To</label>
            <input type="date" name="to_date" value="<?= $to_date ?>"
                style="width:100%; padding:0.6rem; border:1px solid var(--border); border-radius:10px; font-size: 0.8rem; outline: none; color: var(--text-main); background: var(--background);">
        </div>
        <button type="submit"
            style="background:var(--primary); color:white; border:none; padding:0.6rem; border-radius:10px; font-weight:600; display:flex; align-items:center; justify-content:center; width: 42px; height: 42px;">
            <i class="ri-search-line"></i>
        </button>
        <?php if ($from_date || $to_date): ?>
            <a href="tasks.php?status=<?= $current_status ?>"
                style="background:#f1f5f9; color:#64748b; border:1px solid #e2e8f0; padding:0.6rem; border-radius:10px; font-weight:600; display:flex; align-items:center; justify-content:center; width: 42px; height: 42px; text-decoration: none;">
                <i class="ri-close-line"></i>
            </a>
        <?php endif; ?>
    </form>

    <!-- Tabs -->
    <div class="hide-scrollbar" style="display: flex; gap: 0.5rem; overflow-x: auto; padding-bottom: 0.25rem;">
        <?php foreach ($all_status as $key => $label): ?>
            <?php if ($status_counts[$key] > 0 || $key == 'all'): ?>
                <a href="?status=<?= $key ?><?= $from_date ? '&from_date=' . $from_date : '' ?><?= $to_date ? '&to_date=' . $to_date : '' ?>"
                    class="filter-tab <?= ($current_status == $key) ? 'active' : '' ?>">
                    <?= $label ?> <span style="font-size:0.7rem; opacity:0.8;">(<?= $status_counts[$key] ?>)</span>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>

<div class="container" style="padding-bottom: 100px;">
    <?php if (empty($filtered_tasks)): ?>
        <div class="card" style="text-align: center; padding: 3rem 1.5rem; border-style: dashed; border-radius: 20px;">
            <div
                style="width: 60px; height: 60px; background: #fdf2f8; color: #db2777; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                <i class="ri-list-check-3" style="font-size: 2rem;"></i>
            </div>
            <div style="font-weight: 700; color: #1e293b; margin-bottom: 0.25rem;">No tasks found</div>
            <div style="font-size: 0.85rem; color: #64748b;">Try changing your filters or status.</div>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <?php foreach ($filtered_tasks as $job): ?>
                <div class="card" onclick="window.location.href='task-detail.php?id=<?= $job['id'] ?>'"
                    style="padding: 0; overflow: hidden; position: relative; border-radius: 16px; transition: transform 0.2s;">
                    <div style="display: flex;">

                        <!-- Image Preview -->
                        <div style="width: 100px; height: 125px; flex-shrink: 0;">
                            <?php

                            $imageSrc = '';

                            if (!empty($job['fabric_img'])) {

                                $imageName = $job['fabric_img'];

                                $customerPath = "../customer/uploads/" . $imageName;

                                $adminPath = "../" . $imageName;

                                if (file_exists($customerPath)) {

                                    $imageSrc = $customerPath;

                                } elseif (file_exists($adminPath)) {

                                    $imageSrc = $adminPath;

                                }

                            }

                            if (empty($imageSrc) && !empty($job['garment_img'])) {

                                $imageSrc = "../admin/uploads/" . $job['garment_img'];

                            }

                            if (!empty($imageSrc)):
                                ?>
                                <img src="<?= htmlspecialchars($imageSrc) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <div
                                    style="width: 100%; height: 100%; background: #fdf2f8; display: flex; align-items: center; justify-content: center; color: #fbcfe8;">
                                    <i class="ri-t-shirt-line" style="font-size: 2rem;"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Details -->
                        <div
                            style="padding: 1rem; flex-grow: 1; display: flex; flex-direction: column; justify-content: space-between;">
                            <div>
                                <div
                                    style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.25rem;">
                                    <div
                                        style="font-weight: 800; color: var(--text-main); font-size: 0.95rem; line-height: 1.2;">
                                        <?= htmlspecialchars($job['garment'] ?: 'Custom Garment') ?>
                                    </div>
                                    <span
                                        style="font-size: 0.65rem; font-weight: 800; color: var(--primary); background: var(--primary-light); padding: 2px 6px; border-radius: 6px; letter-spacing: 0.5px;">#<?= $job['order_code'] ?></span>
                                </div>
                                <div
                                    style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.25rem;">
                                    <i class="ri-user-line"></i>
                                    <?= htmlspecialchars($job['first_name'] . ' ' . $job['last_name']) ?>
                                </div>
                            </div>

                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div
                                    style="display: flex; align-items: center; gap: 0.25rem; font-size: 0.75rem; font-weight: 700; color: <?= (strtotime($job['due_date']) < time() && !in_array($job['order_status'], ['delivered', 'completed'])) ? 'var(--danger)' : '#64748b' ?>;">
                                    <i class="ri-calendar-event-line"></i>
                                    <?= date('d M', strtotime($job['due_date'])) ?>
                                </div>
                                <?php
                                $statusColor = '#94a3b8';
                                $statusBg = '#f1f5f9';
                                if (in_array($job['order_status'], ['pending'])) {
                                    $statusColor = 'var(--warning)';
                                    $statusBg = '#fffbeb';
                                } elseif (in_array($job['order_status'], ['processing', 'cutting', 'embroidery', 'stitching', 'finishing', 'pattern_making'])) {
                                    $statusColor = 'var(--primary)';
                                    $statusBg = 'var(--primary-light)';
                                } elseif (in_array($job['order_status'], ['ready', 'completed', 'delivered'])) {
                                    $statusColor = 'var(--success)';
                                    $statusBg = '#dcfce7';
                                }
                                ?>
                                <div
                                    style="font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: <?= $statusColor ?>; background: <?= $statusBg ?>; padding: 4px 8px; border-radius: 8px;">
                                    <?= str_replace('_', ' ', $job['order_status']) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/bottom-nav.php'; ?>