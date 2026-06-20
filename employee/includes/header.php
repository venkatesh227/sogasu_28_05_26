<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Sogasu Staff'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        :root {
            --primary: #db2777; /* Pink-600 */
            --primary-light: #fce7f3; /* Pink-100 */
            --surface: #ffffff;
            --background: #fdf2f8; /* Pink-50 */
            --text-main: #4a044e; /* Fuchsia-950 - warmer dark text */
            --text-muted: #86198f; /* Fuchsia-700 - softer muted text */
            --border: #fbcfe8; /* Pink-200 */
            --success: #059669;
            --warning: #d97706;
            --danger: #e11d48; /* Rose-600 */
            --radius-lg: 16px;
            --radius-md: 12px;
            --radius-sm: 8px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            background: var(--background);
            color: var(--text-main);
            min-height: 100vh;
            padding-bottom: 80px; /* Space for bottom nav */
        }

        /* Top Header */
        .app-header {
            background: var(--surface);
            padding: 1rem 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 50;
            box-shadow: var(--shadow-sm);
        }

        .header-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .header-actions {
            display: flex;
            gap: 1rem;
        }

        .icon-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            cursor: pointer;
        }

        .notification-dot {
            position: absolute;
            top: -2px;
            right: -2px;
            width: 8px;
            height: 8px;
            background: var(--danger);
            border-radius: 50%;
            border: 1px solid var(--surface);
        }
        
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger);
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
            border: 2px solid var(--surface);
        }

        /* Common Components */
        .container {
            padding: 1.25rem;
        }

        .card {
            background: var(--surface);
            border-radius: var(--radius-md);
            padding: 1rem;
            box-shadow: var(--shadow-sm);
            margin-bottom: 1rem;
            border: 1px solid var(--border);
        }

        .section-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: var(--text-main);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge.pending { background: #fff1f2; color: #be123c; border: 1px solid #fda4af; }
        .badge.progress { background: #fdf2f8; color: #db2777; border: 1px solid #f9a8d4; }
        .badge.completed { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }

        <?php echo isset($extraHead) ? $extraHead : ''; ?>
    </style>
</head>
<body>
<?php
// Fetch logged-in employee details for the sidebar drawer header
$hdr_emp_name = "Employee";
$hdr_emp_role = "Staff";
$is_punched_in = false;
if (isset($_SESSION['user_id'])) {
    try {
        require_once '../includes/db.php';
        $hdr_stmt = $pdo->prepare("SELECT id, first_name, last_name, job_role FROM employees WHERE user_id = ? AND is_deleted = 0");
        $hdr_stmt->execute([$_SESSION['user_id']]);
        $hdr_emp = $hdr_stmt->fetch();
        if ($hdr_emp) {
            $hdr_emp_name = $hdr_emp['first_name'] . ' ' . ($hdr_emp['last_name'] ?? '');
            $hdr_emp_role = $hdr_emp['job_role'] ?? 'Staff';
            
            // Check punch-in status
            $log_stmt = $pdo->prepare("SELECT log_type FROM attendance_logs WHERE employee_id = ? AND log_date = ? ORDER BY id DESC LIMIT 1");
            $log_stmt->execute([$hdr_emp['id'], date('Y-m-d')]);
            $last_log = $log_stmt->fetchColumn();
            if ($last_log === 'In') {
                $is_punched_in = true;
            }
        }
    } catch (Exception $e) {
        // Fallback gracefully
    }
}
$current_page = basename($_SERVER['PHP_SELF']);
$permissions = [];

$stmt = $pdo->prepare("
    SELECT permission_key
    FROM role_permissions
    WHERE role_name = ?
");

$stmt->execute([$hdr_emp_role]);

$permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

function hasPermission($perm)
{
    global $permissions;

    return in_array($perm, $permissions);
}
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const isPunchedIn = <?= $is_punched_in ? 'true' : 'false' ?>;
    const currentPage = "<?= $current_page ?>";
    
    if (!isPunchedIn && currentPage !== 'attendance.php' && currentPage !== 'login.php' && currentPage !== 'logout.php') {
        // Intercept clicks on buttons
        document.addEventListener('click', function(e) {
            let btn = e.target.closest('button');
            if (btn) {
                const onclickAttr = btn.getAttribute('onclick') || '';
                // Allow sidebar toggle, navigation buttons, and modal close buttons
                if (onclickAttr.includes('window.location.href') || 
                    onclickAttr.includes('toggleSidebarMenu()') || 
                    onclickAttr.includes(".style.display = 'none'") ||
                    onclickAttr.includes(".style.display='none'")) {
                    return; 
                }
                
                e.preventDefault();
                e.stopPropagation();
                window.location.href = 'attendance.php?alert=punchin_required';
            }
        }, true);
        
        // Intercept form submissions
        document.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            window.location.href = 'attendance.php?alert=punchin_required';
        }, true);
    }
});
</script>

    <header class="app-header">
        <div class="header-title" style="display: flex; align-items: center; gap: 0.5rem;">
            <!-- Hamburger menu button -->
            <button class="icon-btn" onclick="toggleSidebarMenu()" style="margin-right: 0.25rem; display: flex; align-items: center; justify-content: center;">
                <i class="ri-menu-2-line" style="font-size: 1.4rem; color: var(--text-main);"></i>
            </button>
            <img src="../images/logo.svg" alt="Sogasu" style="height: 32px; border-radius: 50%;">
            <?php echo isset($headerTitle) ? $headerTitle : 'Sogasu'; ?>
        </div>
        <div class="header-actions">
            <?php
            // Get notification count if user is logged in
            $notif_count = 0;
            if (isset($_SESSION['user_id'])) {
                try {
                    require_once '../includes/db.php';
                    $notif_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE employee_id = (SELECT id FROM employees WHERE user_id = ?) AND is_read = 0");
                    $notif_stmt->execute([$_SESSION['user_id']]);
                    $result = $notif_stmt->fetch();
                    $notif_count = $result['count'] ?? 0;
                } catch (Exception $e) {
                    $notif_count = 0;
                }
            }
            ?>
            <button class="icon-btn" onclick="window.location.href='notifications.php'">
                <i class="ri-notification-3-line"></i>
                <?php if ($notif_count > 0): ?>
                <span class="notification-badge"><?php echo min($notif_count, 9); ?><?php echo $notif_count > 9 ? '+' : ''; ?></span>
                <?php endif; ?>
            </button>
            <button class="icon-btn" onclick="window.location.href='profile.php'">
                 <img src="https://ui-avatars.com/api/?name=<?= urlencode($hdr_emp_name) ?>&background=eef2ff&color=4338ca" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover;">
            </button>
        </div>
<?php include 'notifications_logic.php'; ?>
    </header>

    <!-- Sidebar Drawer Backdrop Overlay -->
    <div id="sidebarBackdrop" onclick="toggleSidebarMenu()" style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(4px); z-index: 1000; opacity: 0; pointer-events: none; transition: opacity 0.3s ease;"></div>

    <!-- Sidebar Drawer -->
    <div id="sidebarDrawer" style="position: fixed; top: 0; left: -280px; width: 280px; height: 100vh; background: #ffffff; box-shadow: 4px 0 24px rgba(0, 0, 0, 0.15); z-index: 1001; transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column; border-right: 1px solid var(--border);">
        
        <!-- Drawer Header -->
        <div style="padding: 1.75rem 1.25rem; background: linear-gradient(135deg, var(--primary), #9d174d); color: white; display: flex; flex-direction: column; gap: 0.75rem; border-bottom-right-radius: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($hdr_emp_name) ?>&background=ffffff&color=db2777&bold=true&size=128" style="width: 54px; height: 54px; border-radius: 50%; border: 3px solid rgba(255,255,255,0.4); object-fit: cover; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                <button onclick="toggleSidebarMenu()" style="background: rgba(255,255,255,0.2); border: none; color: white; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background 0.2s;">
                    <i class="ri-close-line" style="font-size: 1.1rem;"></i>
                </button>
            </div>
            <div>
                <div style="font-weight: 700; font-size: 1.1rem; letter-spacing: -0.025em;"><?= htmlspecialchars($hdr_emp_name) ?></div>
                <div style="display: inline-flex; align-items: center; gap: 0.25rem; margin-top: 0.25rem; font-size: 0.7rem; font-weight: 700; background: rgba(255, 255, 255, 0.2); padding: 0.2rem 0.5rem; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.05em;">
                    <i class="ri-shield-user-line" style="font-size: 0.75rem;"></i> <?= htmlspecialchars($hdr_emp_role) ?>
                </div>
            </div>
        </div>

        <!-- Drawer Links -->
        <div style="flex: 1; overflow-y: auto; padding: 1.25rem 0.75rem; display: flex; flex-direction: column; gap: 0.25rem;">
            
    <a href="dashboard.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.8rem 1rem; border-radius: 12px; text-decoration: none; color: #475569; font-weight: 600; font-size: 0.9rem; transition: all 0.2s; <?= (isset($activePage) && $activePage == 'dashboard') ? 'background: var(--primary-light); color: var(--primary);' : '' ?>">
                <i class="ri-home-4-line" style="font-size: 1.25rem;"></i>
                <span>Home / Dashboard</span>
            </a>
<?php if(hasPermission('employees_tasks_view')): ?>
                <a href="tasks.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.8rem 1rem; border-radius: 12px; text-decoration: none; color: #475569; font-weight: 600; font-size: 0.9rem; transition: all 0.2s; <?= (isset($activePage) && $activePage == 'tasks') ? 'background: var(--primary-light); color: var(--primary);' : '' ?>">
                <i class="ri-task-line" style="font-size: 1.25rem;"></i>
                <span>Assigned Tasks</span>
            </a>
<?php endif; ?>
<?php if(hasPermission('assets_view')): ?>
                <a href="my-assets.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.8rem 1rem; border-radius: 12px; text-decoration: none; color: #475569; font-weight: 600; font-size: 0.9rem; transition: all 0.2s; <?= (isset($activePage) && $activePage == 'my-assets') ? 'background: var(--primary-light); color: var(--primary);' : '' ?>">
                <i class="ri-macbook-line" style="font-size: 1.25rem;"></i>
                <span>My Assets</span>
            </a>
<?php endif; ?>

<?php if($hdr_emp_role !== 'Supervisor' || hasPermission('orders_view')): ?>
            <?php if ($hdr_emp_role === 'Supervisor'): ?>
            <a href="staff-list.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.8rem 1rem; border-radius: 12px; text-decoration: none; color: #475569; font-weight: 600; font-size: 0.9rem; transition: all 0.2s; <?= (isset($activePage) && $activePage == 'staff_tasks') ? 'background: var(--primary-light); color: var(--primary);' : '' ?>">
                <i class="ri-group-line" style="font-size: 1.25rem;"></i>
                <span>Staff Workloads</span>
            </a>
            <?php endif; ?>
                        <?php endif; ?>

<?php if($hdr_emp_role === 'Supervisor' && hasPermission('inventory_view')): ?>
                <a href="racks-view.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.8rem 1rem; border-radius: 12px; text-decoration: none; color: #475569; font-weight: 600; font-size: 0.9rem; transition: all 0.2s; <?= (isset($activePage) && $activePage == 'racks') ? 'background: var(--primary-light); color: var(--primary);' : '' ?>">
                <i class="ri-archive-line" style="font-size: 1.25rem;"></i>
                <span>Racks & Storage</span>
            </a>
            <?php endif; ?>
<?php if(hasPermission('hr_view')): ?>
                <a href="attendance.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.8rem 1rem; border-radius: 12px; text-decoration: none; color: #475569; font-weight: 600; font-size: 0.9rem; transition: all 0.2s; <?= (isset($activePage) && $activePage == 'attendance') ? 'background: var(--primary-light); color: var(--primary);' : '' ?>">
                <i class="ri-checkbox-circle-line" style="font-size: 1.25rem;"></i>
                <span>Attendance (Punch)</span>
            </a>
            <?php endif; ?>
<?php if(hasPermission('appointments_view')): ?>
                    <a href="roster.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.8rem 1rem; border-radius: 12px; text-decoration: none; color: #475569; font-weight: 600; font-size: 0.9rem; transition: all 0.2s; <?= (isset($activePage) && $activePage == 'roster') ? 'background: var(--primary-light); color: var(--primary);' : '' ?>">
                <i class="ri-calendar-event-line" style="font-size: 1.25rem;"></i>
                <span>Shift Roster</span>
            </a>
            <?php endif; ?>
<?php if(hasPermission('leave_applications_view')): ?>
                <a href="leaves.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.8rem 1rem; border-radius: 12px; text-decoration: none; color: #475569; font-weight: 600; font-size: 0.9rem; transition: all 0.2s; <?= (isset($activePage) && $activePage == 'leaves') ? 'background: var(--primary-light); color: var(--primary);' : '' ?>">
                <i class="ri-umbrella-line" style="font-size: 1.25rem;"></i>
                <span>Leave Applications</span>
            </a>
            <?php endif; ?>
<?php if(hasPermission('holidays_calendar_view')): ?>
                <a href="holidays.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.8rem 1rem; border-radius: 12px; text-decoration: none; color: #475569; font-weight: 600; font-size: 0.9rem; transition: all 0.2s; <?= (isset($activePage) && $activePage == 'holidays') ? 'background: var(--primary-light); color: var(--primary);' : '' ?>">
                <i class="ri-flag-2-line" style="font-size: 1.25rem;"></i>
                <span>Holidays Calendar</span>
            </a>
            <?php endif; ?>

<?php if(hasPermission('earnings_ot_view')): ?>
                    <a href="earnings.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.8rem 1rem; border-radius: 12px; text-decoration: none; color: #475569; font-weight: 600; font-size: 0.9rem; transition: all 0.2s; <?= (isset($activePage) && $activePage == 'earnings') ? 'background: var(--primary-light); color: var(--primary);' : '' ?>">
                <i class="ri-wallet-3-line" style="font-size: 1.25rem;"></i>
                <span>My Earnings & OT</span>
            </a>
                        <?php endif; ?>

<?php if(hasPermission('profile_view')): ?>
                <a href="profile.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.8rem 1rem; border-radius: 12px; text-decoration: none; color: #475569; font-weight: 600; font-size: 0.9rem; transition: all 0.2s; <?= (isset($activePage) && $activePage == 'profile') ? 'background: var(--primary-light); color: var(--primary);' : '' ?>">
                <i class="ri-user-3-line" style="font-size: 1.25rem;"></i>
                <span>My Profile</span>
            </a>
                        <?php endif; ?>

        </div>

        <!-- Drawer Footer -->
        <div style="padding: 1rem; border-top: 1px solid #f1f5f9; background: #f8fafc;">
            <a href="../includes/logout.php" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.75rem; background: #fff1f2; color: #e11d48; border: 1px solid #fecdd3; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 0.9rem; transition: all 0.2s;">
                <i class="ri-logout-box-line"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <script>
    function toggleSidebarMenu() {
        const drawer = document.getElementById('sidebarDrawer');
        const backdrop = document.getElementById('sidebarBackdrop');
        
        if (drawer.style.left === '0px') {
            drawer.style.left = '-280px';
            backdrop.style.opacity = '0';
            setTimeout(() => {
                backdrop.style.pointerEvents = 'none';
            }, 300);
        } else {
            drawer.style.left = '0px';
            backdrop.style.pointerEvents = 'auto';
            backdrop.style.opacity = '1';
        }
    }
    </script>
