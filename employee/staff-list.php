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

// Fetch all staff members under supervision with their workloads
$stmt = $pdo->query("
    SELECT e.id, e.first_name, e.last_name, e.job_role,
           COUNT(o.id) as total_assigned,
           SUM(CASE WHEN o.order_status NOT IN ('delivered', 'cancelled') AND o.order_status IS NOT NULL THEN 1 ELSE 0 END) as pending_tasks
    FROM employees e
    LEFT JOIN orders o ON e.id = o.assigned_employee_id AND o.is_deleted = 0
    WHERE e.status = 1 AND e.is_deleted = 0 AND e.job_role != 'Supervisor'
    GROUP BY e.id
    ORDER BY pending_tasks DESC
");
$staff_list = $stmt->fetchAll();

$pageTitle = "Staff Directory - Sogasu";
$headerTitle = "Staff Workloads";
$activePage = "staff_tasks";
include 'includes/header.php';
?>

<div class="container" style="padding-top: 1rem; padding-bottom: 100px;">
    
    <div class="section-title">
        <span>Staff Directory</span>
        <span style="font-size: 0.75rem; color: #64748b; font-weight: 500;">Select a staff member to view tasks</span>
    </div>

    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
        <?php foreach ($staff_list as $member): ?>
            <?php 
            $pending = $member['pending_tasks'];
            ?>
            <div class="card" onclick="window.location.href='staff-tasks.php?id=<?= $member['id'] ?>'" style="margin-bottom: 0; padding: 1rem; display: flex; align-items: center; gap: 1rem; cursor: pointer; transition: all 0.2s;">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($member['first_name']) ?>&background=eef2ff&color=4338ca&bold=true" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover;">
                <div style="flex: 1;">
                    <div style="font-weight: 700; color: #1e293b; font-size: 0.95rem;">
                        <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?>
                    </div>
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 600; margin-top: 2px;">
                        <?= htmlspecialchars($member['job_role'] ?: 'Tailor') ?>
                    </div>
                </div>
                <div style="text-align: right; display: flex; flex-direction: column; align-items: flex-end; gap: 0.25rem;">
                    <span class="badge" style="background: <?= $pending > 0 ? '#fffbeb' : '#ecfdf5' ?>; color: <?= $pending > 0 ? '#b45309' : '#047857' ?>; font-size: 0.7rem; font-weight: 800; padding: 0.2rem 0.5rem; border-radius: 999px;">
                        <?= $pending ?> Pending
                    </span>
                    <span style="font-size: 0.7rem; color: #94a3b8;">Total: <?= $member['total_assigned'] ?></span>
                </div>
                <i class="ri-arrow-right-s-line" style="color: #cbd5e1; font-size: 1.25rem;"></i>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<!-- Fixed Back Floating Button -->
<a href="dashboard.php" style="position: fixed; bottom: 90px; left: 1.25rem; width: 48px; height: 48px; background: var(--text-main); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 6px rgba(0,0,0,0.2); text-decoration: none; z-index: 100;">
    <i class="ri-arrow-left-line" style="font-size: 1.5rem;"></i>
</a>

<?php include 'includes/bottom-nav.php'; ?>
