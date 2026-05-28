<?php
session_start();
include '../includes/db.php';

// Only super_admin can access the permissions management module itself
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: dashboard.php");
    exit();
}

$pageTitle = "Role Permissions - Sogasu";
$activePage = "permissions";

// Handle Form Submission to save permissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_permissions'])) {
    $role_name = trim($_POST['role_name'] ?? '');
    $selected_perms = $_POST['permissions'] ?? [];

    if ($role_name && $role_name !== 'super_admin') {
        $pdo->beginTransaction();
        try {
            // Delete old permissions
            $delStmt = $pdo->prepare("DELETE FROM role_permissions WHERE role_name = ?");
            $delStmt->execute([$role_name]);

            // Insert new permissions
            if (!empty($selected_perms)) {
                $insStmt = $pdo->prepare("INSERT INTO role_permissions (role_name, permission_key) VALUES (?, ?)");
                foreach ($selected_perms as $perm) {
                    $insStmt->execute([$role_name, $perm]);
                }
            }

            $pdo->commit();
            $_SESSION['success'] = "permissions_saved";
            header("Location: permissions.php?role=" . urlencode($role_name));
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "Failed to save permissions: " . $e->getMessage();
        }
    }
}

// Fetch all available roles from job_roles table
$roles_stmt = $pdo->query("SELECT role_name FROM job_roles WHERE is_deleted = 0 AND status = 'active' ORDER BY role_name ASC");
$all_roles = $roles_stmt->fetchAll(PDO::FETCH_COLUMN);

// Ensure standard admin roles are included
$std_roles = ['Manager', 'Accountant', 'Supervisor'];
foreach ($std_roles as $sr) {
    if (!in_array($sr, $all_roles)) {
        $all_roles[] = $sr;
    }
}
sort($all_roles);

// Get currently selected role (default to the first available role)
$selected_role = $_GET['role'] ?? ($all_roles[0] ?? '');

// Fetch permissions for the selected role
$active_permissions = [];
if ($selected_role) {
    $stmt = $pdo->prepare("SELECT permission_key FROM role_permissions WHERE role_name = ?");
    $stmt->execute([$selected_role]);
    $active_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Complete list of permissions with descriptors and icons
$modules_config = [
    'dashboard' => [
        'title' => 'Dashboard Overview',
        'icon' => 'ri-dashboard-line',
        'color' => '#3b82f6',
        'description' => 'Access to the admin home dashboard, revenue intelligence charts, and recent activity log.'
    ],
    'masters' => [
        'title' => 'Masters Settings',
        'icon' => 'ri-folder-settings-line',
        'color' => '#8b5cf6',
        'description' => 'Manage job roles, branches, categories, measurements, services, racks, and global settings.'
    ],
    'orders' => [
        'title' => 'Order Management',
        'icon' => 'ri-shopping-bag-line',
        'color' => '#ec4899',
        'description' => 'Access to view, create, edit orders, assign employee tasks, and manage outsourcing records.'
    ],
    'employees_tasks' => [
        'title' => 'Workshop Operations',
        'icon' => 'ri-task-line',
        'color' => '#f59e0b',
        'description' => 'View active workshop tasks, assign tasks to workers, and update real-time progress.'
    ],
    'hr' => [
        'title' => 'HR & Payroll',
        'icon' => 'ri-team-line',
        'color' => '#10b981',
        'description' => 'Manage employee profiles, working hours, shifts, attendance, payroll logs, and leave requests.'
    ],
    'appointments' => [
        'title' => 'Appointments Calendar',
        'icon' => 'ri-calendar-event-line',
        'color' => '#06b6d4',
        'description' => 'Access to book, edit, and schedule customer boutique appointments.'
    ],
    'inventory' => [
        'title' => 'Inventory & Sourcing',
        'icon' => 'ri-archive-line',
        'color' => '#ef4444',
        'description' => 'Manage raw material inventory, category tags, procurement invoices, and material sourcing.'
    ],
    'assets' => [
        'title' => 'Asset Management',
        'icon' => 'ri-tools-line',
        'color' => '#14b8a6',
        'description' => 'Track company equipment, machinery assets, categories, and maintenance schedules.'
    ],
    'finance' => [
        'title' => 'Finance & Invoicing',
        'icon' => 'ri-coins-line',
        'color' => '#6366f1',
        'description' => 'View customer invoices, boutique transaction logs, and manage business expenses.'
    ],
    'customers' => [
        'title' => 'Customers & CRM',
        'icon' => 'ri-user-star-line',
        'color' => '#a855f7',
        'description' => 'Manage boutique client list, detailed contact profiles, and customer family profiles.'
    ],
    'reports' => [
        'title' => 'Analytics & Reports',
        'icon' => 'ri-bar-chart-fill',
        'color' => '#f43f5e',
        'description' => 'Generate and export full-scale boutique revenue reports, inventory graphs, and staff performance metrics.'
    ],
    'support' => [
        'title' => 'Support & Helpdesk',
        'icon' => 'ri-customer-service-2-line',
        'color' => '#0ea5e9',
        'description' => 'Manage customer support tickets, status updates, and system complaints.'
    ],
    'permissions' => [
        'title' => 'Permissions Management',
        'icon' => 'ri-shield-user-line',
        'color' => '#db2777',
        'description' => 'Full control over administrative role permissions and authorization settings. (Highly Restricted)'
    ]
];

include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div>
        <!-- Header -->
        <div style="margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.6rem; font-weight: 800; color: #1e293b; margin: 0;">Role Permissions</h2>
        </div>

        <?php if (isset($error_message)): ?>
            <div style="background: #fee2e2; border: 1px solid #fca5a5; color: #b91c1c; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 500; font-size: 0.9rem;">
                <i class="ri-error-warning-line" style="vertical-align: middle; font-size: 1.1rem; margin-right: 0.25rem;"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 2.5fr; gap: 1.5rem; align-items: start;">
            <!-- Left Side: Role List -->
            <div class="table-container" style="padding: 1.25rem; margin-top: 0;">
                <h3 style="font-size: 1.05rem; font-weight: 700; color: #1e293b; margin-bottom: 1.25rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">
                    Administrative Roles
                </h3>
                
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <!-- Hardcoded Super Admin item (Visual placeholder) -->
                    <div style="background: <?= ($selected_role === 'super_admin') ? '#4f46e50d' : 'transparent' ?>; 
                                border: 1px solid <?= ($selected_role === 'super_admin') ? '#4f46e533' : '#e2e8f0' ?>; 
                                border-radius: 8px; padding: 0.9rem; cursor: pointer; transition: all 0.2s;"
                         onclick="window.location.href='?role=super_admin'">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-weight: 700; color: #1e293b; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="ri-shield-flash-line" style="color: #db2777;"></i> Super Admin
                            </span>
                            <span style="font-size: 0.75rem; background: #db27771a; color: #db2777; padding: 2px 8px; border-radius: 20px; font-weight: 700;">Full Access</span>
                        </div>
                    </div>

                    <?php foreach ($all_roles as $role): 
                        if ($role === 'super_admin') continue;
                        
                        // Count active permissions for badge
                        $cStmt = $pdo->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_name = ?");
                        $cStmt->execute([$role]);
                        $permCount = $cStmt->fetchColumn();
                        
                        $is_active = ($selected_role === $role);
                    ?>
                        <div style="background: <?= $is_active ? '#4f46e50d' : '#ffffff' ?>; 
                                    border: 1px solid <?= $is_active ? '#4f46e54d' : '#e2e8f0' ?>; 
                                    border-radius: 8px; padding: 0.9rem; cursor: pointer; transition: all 0.2s;
                                    box-shadow: <?= $is_active ? 'none' : '0 1px 2px rgba(0,0,0,0.02)' ?>;"
                             onclick="window.location.href='?role=<?= urlencode($role) ?>'"
                             class="role-list-item">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-weight: 700; color: <?= $is_active ? '#4f46e5' : '#1e293b' ?>; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="ri-user-settings-line"></i> <?= htmlspecialchars($role) ?>
                                </span>
                                <span style="font-size: 0.75rem; background: <?= $is_active ? '#4f46e51a' : '#f1f5f9' ?>; color: <?= $is_active ? '#4f46e5' : '#64748b' ?>; padding: 2px 8px; border-radius: 20px; font-weight: 700;">
                                    <?= $permCount ?> Modules
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right Side: Permissions Dashboard -->
            <div class="table-container" style="padding: 1.5rem; margin-top: 0;">
                
                <?php if ($selected_role === 'super_admin'): ?>
                    <!-- Super Admin View (Read Only warning) -->
                    <div style="text-align: center; padding: 3rem 1.5rem;">
                        <div style="font-size: 4rem; color: #db2777; margin-bottom: 1.5rem;"><i class="ri-shield-flash-line"></i></div>
                        <h3 style="font-size: 1.4rem; font-weight: 800; color: #1e293b; margin-bottom: 0.5rem;">Super Admin Master Authorization</h3>
                        <p style="color: #64748b; font-size: 0.95rem; max-width: 500px; margin: 0 auto 2rem auto; line-height: 1.5;">
                            The <strong>Super Admin</strong> role is a hardcoded system administrator and possesses full, unrestricted access to all modules bypass-checked in code. Altering individual permissions is disabled for this role.
                        </p>
                        
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; max-width: 600px; margin: 0 auto; text-align: left;">
                            <?php foreach (array_slice($modules_config, 0, 6) as $key => $conf): ?>
                                <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 0.75rem; border-radius: 8px; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="ri-checkbox-circle-fill" style="color: #10b981; font-size: 1.1rem;"></i>
                                    <span style="font-size: 0.8rem; font-weight: 700; color: #334155;"><?= $conf['title'] ?></span>
                                </div>
                            <?php endforeach; ?>
                            <div style="background: #fdf2f8; border: 1px solid #fbcfe8; padding: 0.75rem; border-radius: 8px; display: flex; align-items: center; gap: 0.5rem; grid-column: span 3; justify-content: center;">
                                <i class="ri-checkbox-circle-fill" style="color: #db2777; font-size: 1.1rem;"></i>
                                <span style="font-size: 0.8rem; font-weight: 800; color: #db2777;">All other system modules fully active</span>
                            </div>
                        </div>
                    </div>
                <?php elseif ($selected_role): ?>
                    <!-- Configurable Role Form -->
                    <form method="POST">
                        <input type="hidden" name="role_name" value="<?= htmlspecialchars($selected_role) ?>">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 1rem;">
                            <div>
                                <h3 style="font-size: 1.25rem; font-weight: 800; color: #1e293b; margin: 0;">
                                    Configure Permissions for: <span style="color: #4f46e5;"><?= htmlspecialchars($selected_role) ?></span>
                                </h3>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <button type="button" class="btn" onclick="toggleAll(true)" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; font-size: 0.8rem; padding: 6px 12px; border-radius: 6px; font-weight: 600;">
                                    Select All
                                </button>
                                <button type="button" class="btn" onclick="toggleAll(false)" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; font-size: 0.8rem; padding: 6px 12px; border-radius: 6px; font-weight: 600;">
                                    Deselect All
                                </button>
                            </div>
                        </div>

                        <!-- Simplified Permissions Table -->
                        <div class="table-responsive" style="overflow-x: auto; margin-bottom: 2rem; border: 1px solid #e2e8f0; border-radius: 8px;">
                            <table class="simple-perms-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem; background: white;">
                                <thead>
                                    <tr style="border-bottom: 2px solid #e2e8f0; background: #f8fafc;">
                                        <th style="padding: 0.75rem 1rem; font-weight: 700; color: #475569; width: 45%;">Module</th>
                                        <th style="padding: 0.75rem 1rem; font-weight: 700; color: #475569; text-align: center; width: 11%;">Access</th>
                                        <th style="padding: 0.75rem 1rem; font-weight: 700; color: #475569; text-align: center; width: 11%;">View</th>
                                        <th style="padding: 0.75rem 1rem; font-weight: 700; color: #475569; text-align: center; width: 11%;">Add</th>
                                        <th style="padding: 0.75rem 1rem; font-weight: 700; color: #475569; text-align: center; width: 11%;">Edit</th>
                                        <th style="padding: 0.75rem 1rem; font-weight: 700; color: #475569; text-align: center; width: 11%;">Delete</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($modules_config as $key => $conf): 
                                        $main_checked = in_array($key, $active_permissions);
                                    ?>
                                        <tr class="perm-row" style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s;">
                                            <td style="padding: 0.75rem 1rem; display: flex; align-items: center; gap: 0.75rem;">
                                                <span style="color: white; background: <?= $conf['color'] ?>; font-size: 0.9rem; display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 6px;">
                                                    <i class="<?= $conf['icon'] ?>"></i>
                                                </span>
                                                <span style="font-size: 0.85rem; font-weight: 700; color: #334155;"><?= $conf['title'] ?></span>
                                            </td>
                                            <td style="padding: 0.75rem 1rem; text-align: center; vertical-align: middle;">
                                                <input type="checkbox" name="permissions[]" value="<?= $key ?>" <?= $main_checked ? 'checked' : '' ?> class="permission-chk simple-chk">
                                            </td>
                                            <td style="padding: 0.75rem 1rem; text-align: center; vertical-align: middle;">
                                                <input type="checkbox" name="permissions[]" value="<?= $key ?>_view" <?= in_array($key . '_view', $active_permissions) ? 'checked' : '' ?> class="action-chk simple-chk" <?= !$main_checked ? 'disabled' : '' ?>>
                                            </td>
                                            <td style="padding: 0.75rem 1rem; text-align: center; vertical-align: middle;">
                                                <input type="checkbox" name="permissions[]" value="<?= $key ?>_add" <?= in_array($key . '_add', $active_permissions) ? 'checked' : '' ?> class="action-chk simple-chk" <?= !$main_checked ? 'disabled' : '' ?>>
                                            </td>
                                            <td style="padding: 0.75rem 1rem; text-align: center; vertical-align: middle;">
                                                <input type="checkbox" name="permissions[]" value="<?= $key ?>_edit" <?= in_array($key . '_edit', $active_permissions) ? 'checked' : '' ?> class="action-chk simple-chk" <?= !$main_checked ? 'disabled' : '' ?>>
                                            </td>
                                            <td style="padding: 0.75rem 1rem; text-align: center; vertical-align: middle;">
                                                <input type="checkbox" name="permissions[]" value="<?= $key ?>_delete" <?= in_array($key . '_delete', $active_permissions) ? 'checked' : '' ?> class="action-chk simple-chk" <?= !$main_checked ? 'disabled' : '' ?>>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Submit Button -->
                        <div style="display: flex; justify-content: flex-end; padding-top: 1rem; border-top: 1px solid #f1f5f9;">
                            <button type="submit" name="save_permissions" class="btn btn-primary" style="background: #4f46e5; border: none; padding: 12px 32px; border-radius: 8px; font-weight: 700; cursor: pointer; color: white; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);">
                                <i class="ri-save-3-line"></i> Save Permissions Configuration
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div style="text-align: center; padding: 4rem 1.5rem; color: #94a3b8;">
                        <i class="ri-shield-line" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                        No roles found in system. Please configure job roles first.
                    </div>
                <?php endif; ?>
                
            </div>

        </div>
    </div>
</main>

<style>
    .role-list-item:hover {
        background: #f8fafc !important;
        border-color: #cbd5e1 !important;
    }
    
    .perm-row:hover {
        background-color: #f8fafc;
    }
    
    .simple-chk {
        width: 16px;
        height: 16px;
        accent-color: #4f46e5;
        cursor: pointer;
        transition: transform 0.15s;
    }
    
    .simple-chk:hover {
        transform: scale(1.15);
    }
    
    .simple-chk:disabled {
        opacity: 0.45;
        cursor: not-allowed;
        transform: none !important;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .form-label {
        font-size: 0.875rem;
        font-weight: 600;
        color: #334155;
    }

    .form-control,
    .form-select {
        padding: 0.75rem;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 0.95rem;
        width: 100%;
        outline: none;
        transition: border-color 0.2s;
        font-family: inherit;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: #4f46e5;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<?php if (!empty($_SESSION['success']) && $_SESSION['success'] === 'permissions_saved'): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Permissions Saved',
            text: 'Role authorization privileges updated in real-time.',
            confirmButtonColor: '#4f46e5',
            timer: 2000
        });
    </script>
    <?php unset($_SESSION['success']); endif; ?>

<script>
function toggleAll(checked) {
    $('.permission-chk').prop('checked', checked);
    $('.action-chk').prop('checked', checked).prop('disabled', !checked);
}

$(document).ready(function() {
    // Handle change of main access checkbox in a row
    $('.permission-chk').on('change', function() {
        const checked = $(this).is(':checked');
        const row = $(this).closest('.perm-row');
        const actions = row.find('.action-chk');
        
        actions.prop('disabled', !checked);
        if (checked) {
            // Default to checking at least 'View' if none is checked
            const anyChecked = actions.filter(':checked').length > 0;
            if (!anyChecked) {
                row.find('input[value$="_view"]').prop('checked', true);
            }
        } else {
            // Clear all sub-permissions when disabled
            actions.prop('checked', false);
        }
    });

    // Handle change of action check boxes
    $('.action-chk').on('change', function() {
        const checked = $(this).is(':checked');
        const row = $(this).closest('.perm-row');
        const mainToggle = row.find('.permission-chk');
        
        if (checked && !mainToggle.is(':checked')) {
            mainToggle.prop('checked', true).trigger('change');
        }
    });
});
</script>
</script>

<?php
include 'includes/footer.php';
?>
