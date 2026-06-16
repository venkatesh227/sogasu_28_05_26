<?php
if (!isset($permissions)) {

    require_once '../includes/db.php';

    $permissions = [];

    if (isset($_SESSION['user_id'])) {

        $stmt = $pdo->prepare("
            SELECT job_role
            FROM employees
            WHERE user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $role_name = $stmt->fetchColumn();

        if ($role_name) {
            $stmt = $pdo->prepare("
                SELECT permission_key
                FROM role_permissions
                WHERE role_name = ?
            ");
            $stmt->execute([$role_name]);
            $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
    }
}
?>   
   <style>
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: var(--surface);
            display: flex;
            justify-content: space-around;
            padding: 0.75rem 0;
            box-shadow: 0 -4px 6px -1px rgba(0, 0, 0, 0.05);
            border-top: 1px solid var(--border);
            z-index: 100;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.25rem;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav-item i {
            font-size: 1.5rem;
        }

        .nav-item.active {
            color: var(--primary);
        }
    </style>

    <nav class="bottom-nav">
        <a href="dashboard.php" class="nav-item <?php echo ($activePage == 'dashboard') ? 'active' : ''; ?>">
            <i class="ri-home-4-line"></i>
            <span>Home</span>
        </a>
<?php if (in_array('hr_view', $permissions)): ?>
    <a href="attendance.php" class="nav-item <?php echo ($activePage == 'attendance') ? 'active' : ''; ?>">
    <i class="ri-checkbox-circle-line"></i>
    <span>Clock</span>
</a>
<?php endif; ?>
<?php if (in_array('leave_applications_view', $permissions)): ?>
<a href="leaves.php" class="nav-item <?php echo ($activePage == 'leaves') ? 'active' : ''; ?>">
    <i class="ri-umbrella-line"></i>
    <span>Leaves</span>
</a>
<?php endif; ?>
<?php if (in_array('employees_tasks_view', $permissions)): ?>
<a href="tasks.php" class="nav-item <?php echo ($activePage == 'tasks') ? 'active' : ''; ?>">
    <i class="ri-task-line"></i>
    <span>Tasks</span>
</a>
<?php endif; ?>
<?php if (in_array('profile_view', $permissions)): ?>
<a href="profile.php" class="nav-item <?php echo ($activePage == 'profile') ? 'active' : ''; ?>">
    <i class="ri-user-3-line"></i>
    <span>Profile</span>
</a>
<?php endif; ?>
    </nav>

</body>
</html>
