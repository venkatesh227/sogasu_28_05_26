<?php
require_once __DIR__ . '/../../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if user is not logged in at all
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check role permission based on activePage
$page_permission_map = [
    'dashboard' => 'dashboard',

    // Masters
    'job-roles' => 'masters',
    'add-job-role' => 'masters',
    'delete-job-role' => 'masters',
    'branches' => 'masters',
    'add-branch' => 'masters',
    'delete-branch' => 'masters',
    'categories' => 'masters',
    'add-category' => 'masters',
    'delete-category' => 'masters',
    'sub-categories' => 'masters',
    'add-sub-category' => 'masters',
    'delete-sub-category' => 'masters',
    'measurement' => 'masters',
    'add-measurement-key' => 'masters',
    'delete-measurement-key' => 'masters',
    'services' => 'masters',
    'add-service' => 'masters',
    'delete-service' => 'masters',
    'racks' => 'masters',
    'add-rack' => 'masters',
    'delete-rack' => 'masters',
    'quick-notes' => 'masters',
    'add-quick-note' => 'masters',
    'delete-quick-note' => 'masters',
    'global-settings' => 'masters',
    'bulk-upload' => 'masters',
    'suppliers' => 'masters',
    'add-supplier' => 'masters',
    'delete-supplier' => 'masters',

    // Orders
    'add-order' => 'orders',
    'orders' => 'orders',
    'all-orders' => 'orders',
    'view-order' => 'orders',
    'edit-order' => 'orders',
    'print-job-card' => 'orders',
    'outsourcing' => 'orders',

    // Tasks & Employees (Operations)
    'employees-tasks' => 'employees_tasks',
    'tasks' => 'employees_tasks',

    // HR
    'employees' => 'hr',
    'add-employee' => 'hr',
    'delete-employee' => 'hr',
    'employee-devices' => 'hr',
    'view-employee' => 'hr',
    'hr_reports' => 'hr',
    'attendance' => 'hr',
    'shift-roster' => 'hr',
    'payroll' => 'hr',
    'pay-employee' => 'hr',
    'give-advance' => 'hr',
    'payments' => 'hr',
    'ot-requests' => 'hr',
    'add-ot' => 'hr',
    'leaves' => 'hr',
    'leave-types' => 'hr',
    'holidays' => 'hr',

    // Appointments
    'appointments' => 'appointments',
    'add-appointment' => 'appointments',

    // Inventory
    'inventory' => 'inventory',
    'add-inventory' => 'inventory',
    'delete-inventory' => 'inventory',
    'inventory-categories' => 'inventory',
    'procurement' => 'inventory',
    'sourcing' => 'inventory',
    'inventory-reports' => 'inventory',
    'add-inventory-invoice' => 'inventory',
    'purchase-orders' => 'inventory',
    'add-purchase-order' => 'inventory',
    'receive-po' => 'inventory',

    // Assets
    'assets' => 'assets',
    'asset-categories' => 'assets',
    'asset-reports' => 'assets',

    // Finance
    'billing' => 'finance',
    'payments' => 'finance',
    'expenses' => 'finance',

    // CRM
    'customers' => 'customers',
    'add-customer' => 'customers',
    'delete-customer' => 'customers',
    'view-customer' => 'customers',
    'customer-family' => 'customers',

    // Reports & Support
    'reports' => 'reports',
    'support' => 'support',

    // Role Permissions
    'permissions' => 'permissions'
];

$required_permission = $page_permission_map[$activePage] ?? null;

if ($required_permission && !has_permission($required_permission)) {
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied - Sogasu</title>
        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&display=swap" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
        <link rel="stylesheet" href="../css/global.css">
        <link rel="stylesheet" href="../css/admin.css?v=24">
        <style>
            body {
                font-family: 'Roboto', sans-serif;
                background-color: #f8fafc;
            }

            .denied-card {
                background: white;
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                padding: 3rem 2rem;
                max-width: 500px;
                margin: 8rem auto;
                text-align: center;
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            }
        </style>
    </head>

    <body>
        <div class="denied-card">
            <div style="font-size: 4rem; color: #ef4444; margin-bottom: 1.5rem;"><i class="ri-lock-2-line"></i></div>
            <h2 style="font-size: 2rem; font-weight: 700; color: #1e293b; margin-bottom: 0.75rem;">Access Denied</h2>
            <p style="color: #64748b; font-size: 1.05rem; margin-bottom: 2rem; line-height: 1.5;">You do not have the
                required permissions to access this administrative module.</p>
            <a href="dashboard.php"
                style="background: #4f46e5; border: none; padding: 12px 28px; border-radius: 8px; font-weight: 600; text-decoration: none; color: white; display: inline-block;">Go
                to Dashboard</a>
        </div>
    </body>

    </html>
    <?php
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Admin Panel - Sogasu'; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/admin.css?v=24">
    <link rel="stylesheet" href="../css/premium-admin.css?v=1">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <!-- jQuery & DataTables -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php if (isset($extraHead))
        echo $extraHead; ?>
    <style>
        body {
            font-family: 'Roboto', sans-serif !important;
        }

        .mobile-toggle {
            display: none;
            font-size: 1.5rem;
            color: #334155;
            cursor: pointer;
            margin-right: 0.5rem;
        }

        @media (max-width: 992px) {
            .mobile-toggle {
                display: block;
            }
        }

        /* Sharp & Crispy DataTables Styles (Design Parity) */
        .dataTables_wrapper {
            padding: 0.5rem 0 !important;
            font-family: 'Roboto', sans-serif !important;
        }

        table.dataTable,
        table.compact-table {
            border-collapse: collapse !important;
            width: 100% !important;
            margin-bottom: 1rem !important;
            background: #fff !important;
        }

        table.dataTable thead th {
            background: #fff !important;
            color: #64748b !important;
            font-size: 0.7rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            padding: 12px 15px !important;
            border-bottom: 1px solid #e2e8f0 !important;
            border-top: none !important;
        }

        table.dataTable tbody tr {
            border-bottom: 1px solid #f1f5f9 !important;
            transition: background 0.2s !important;
        }

        table.dataTable tbody tr:hover {
            background: #f8fafc !important;
        }

        table.dataTable tbody td {
            padding: 8px 15px !important;
            font-size: 0.82rem !important;
            color: #475569 !important;
            vertical-align: middle !important;
            border: none !important;
        }

        /* Bold second column (usually Name/Role) */
        table.dataTable tbody td:nth-child(2),
        table.dataTable tbody td.font-bold {
            font-weight: 700 !important;
            color: #1e293b !important;
        }

        /* DataTables Controls Layout */
        .dt-top {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            padding: 0.75rem 0 !important;
            gap: 1rem !important;
        }

        .dataTables_filter input {
            border: 1px solid #e2e8f0 !important;
            border-radius: 6px !important;
            padding: 5px 12px !important;
            font-size: 0.8rem !important;
            outline: none !important;
            width: 200px !important;
        }

        .dt-buttons .dt-button {
            background: #fff !important;
            border: 1px solid #e2e8f0 !important;
            color: #475569 !important;
            font-size: 0.75rem !important;
            font-weight: 600 !important;
            padding: 5px 12px !important;
            border-radius: 6px !important;
            box-shadow: none !important;
            margin-right: 5px !important;
        }

        .dt-buttons .dt-button:hover {
            background: #f8fafc !important;
            border-color: #cbd5e1 !important;
        }

        /* Pagination & Info */
        .dataTables_info {
            font-size: 0.75rem !important;
            color: #64748b !important;
            padding-top: 1rem !important;
        }

        .dataTables_paginate {
            padding-top: 1rem !important;
        }

        .dataTables_paginate .paginate_button {
            padding: 4px 10px !important;
            font-size: 0.75rem !important;
            border-radius: 4px !important;
            border: 1px solid #e2e8f0 !important;
            background: #fff !important;
            margin-left: 4px !important;
        }

        .dataTables_paginate .paginate_button.current {
            background: #f1f5f9 !important;
            border-color: #cbd5e1 !important;
            color: #1e293b !important;
            font-weight: 700 !important;
        }

        /* Fix Bootstrap 5 pagination without Bootstrap CSS */
        .pagination {
            display: flex;
            padding-left: 0;
            list-style: none;
            justify-content: flex-end;
            margin: 0;
        }

        .pagination li {
            margin: 0 2px;
        }

        .pagination .page-link {
            position: relative;
            display: block;
            padding: 4px 10px;
            font-size: 0.75rem;
            color: #475569;
            text-decoration: none;
            background-color: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .pagination .page-item.active .page-link {
            z-index: 3;
            color: #1e293b;
            font-weight: 700;
            background-color: #f1f5f9;
            border-color: #cbd5e1;
        }

        .pagination .page-link:hover {
            background-color: #f8fafc;
            color: #1e293b;
        }

        .pagination .page-item.disabled .page-link {
            color: #94a3b8;
            pointer-events: none;
            background-color: #fff;
            border-color: #e2e8f0;
        }

        /* Global Toggle Switch (Design Parity) */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 36px;
            height: 18px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            background: #e5e7eb;
            border-radius: 20px;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            transition: 0.3s;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 12px;
            width: 12px;
            left: 3px;
            top: 3px;
            background: white;
            border-radius: 50%;
            transition: 0.3s;
        }

        .toggle-switch input:checked+.toggle-slider {
            background: #22c55e !important;
        }

        .toggle-switch input:checked+.toggle-slider:before {
            transform: translateX(18px);
        }

        /* Action Icons Styling */
        .action-icon {
            font-size: 1.1rem;
            margin: 0 5px;
            cursor: pointer;
            transition: transform 0.1s;
        }

        .action-icon:hover {
            transform: scale(1.1);
        }

        .ri-pencil-line {
            color: #6366f1 !important;
        }

        .ri-delete-bin-line,
        .ri-delete-bin-fill {
            color: #ef4444 !important;
        }

        .table-box,
        .table-container {
            background: #fff !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 12px !important;
            padding: 1rem !important;
            margin-top: 0.5rem !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03) !important;
        }

        .sidebar-badge {
            background: #ef4444;
            color: white !important;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: auto;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            line-height: 1;
            display: inline-block;
        }
    </style>
</head>

<body class="admin-body">

    <div id="sidebar-overlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar">
        <?php
        $mastersPages = ['job-roles', 'branches', 'categories', 'sub-categories', 'measurement', 'services', 'racks', 'quick-notes', 'global-settings', 'bulk-upload', 'suppliers', 'add-supplier'];
        $inventoryPages = ['inventory', 'inventory-categories', 'procurement', 'sourcing', 'inventory-reports', 'purchase-orders', 'add-purchase-order', 'receive-po'];
        $hrPages = ['employees', 'employee-devices', 'shift-roster', 'attendance', 'holidays', 'add-employee', 'hr_reports', 'leaves', 'payroll', 'leave-types', 'ot-requests'];
        $assetsPages = ['assets', 'asset-categories', 'asset-reports'];
        $financePages = ['billing', 'payments', 'expenses', 'expense-categories'];

        $pendingLeaveCount = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'Pending'")->fetchColumn();
        $pendingOTCount = $pdo->query("SELECT COUNT(*) FROM employee_overtime WHERE status = 'Pending'")->fetchColumn();
        $newOrdersCount = $pdo->query("SELECT COUNT(*) FROM orders WHERE is_viewed = 0 AND is_deleted = 0")->fetchColumn();
        $newAppointmentCount = 0; // Appointments table missing
        ?>
        <div class="logo-area" style="text-align: center; padding: 0.25rem 0; margin: 0; margin-bottom: 0.75rem;">
            <h1 style="color: white; font-size: 1.4rem; letter-spacing: 1px; text-transform: uppercase; margin: 0;">
                SOGASU</h1>
        </div>

        <nav class="nav-links">
            <?php if (has_permission('dashboard')): ?>
                <a href="dashboard.php" class="nav-item <?php echo ($activePage == 'dashboard') ? 'active' : ''; ?>">
                    <i class="ri-dashboard-fill"></i> Dashboard
                </a>
            <?php endif; ?>

            <!-- Masters Dropdown -->
            <?php if (has_permission('masters')): ?>
                <div class="nav-item nav-item-parent <?php echo in_array($activePage, $mastersPages) ? 'open' : ''; ?>"
                    onclick="toggleMenu('masters-menu')">
                    <div style="display: flex; align-items: center; gap: 0.75rem; flex: 1;">
                        <i class="ri-folder-settings-line"></i> Masters
                    </div>
                </div>
                <div id="masters-menu" class="sub-menu <?php echo in_array($activePage, $mastersPages) ? 'open' : ''; ?>">
                    <a href="job-roles.php"
                        class="sub-nav-item <?php echo ($activePage == 'job-roles') ? 'active' : ''; ?>">
                        <i class="ri-briefcase-4-line" style="font-size: 1rem; width: auto;"></i> Job Roles
                    </a>
                    <a href="branches.php" class="sub-nav-item <?php echo ($activePage == 'branches') ? 'active' : ''; ?>">
                        <i class="ri-building-line" style="font-size: 1rem; width: auto;"></i> Branches
                    </a>
                    <a href="categories.php"
                        class="sub-nav-item <?php echo ($activePage == 'categories') ? 'active' : ''; ?>">
                        <i class="ri-grid-line" style="font-size: 1rem; width: auto;"></i> Categories
                    </a>
                    <a href="sub-categories.php"
                        class="sub-nav-item <?php echo ($activePage == 'sub-categories') ? 'active' : ''; ?>">
                        <i class="ri-layout-grid-line" style="font-size: 1rem; width: auto;"></i> Sub Categories
                    </a>
                    <a href="measurement-reference.php"
                        class="sub-nav-item <?php echo ($activePage == 'measurement') ? 'active' : ''; ?>">
                        <i class="ri-ruler-2-line" style="font-size: 1rem; width: auto;"></i> Measurements
                    </a>
                    <a href="services-pricing.php"
                        class="sub-nav-item <?php echo ($activePage == 'services') ? 'active' : ''; ?>">
                        <i class="ri-price-tag-3-line"></i> Services
                    </a>
                    <a href="racks.php" class="sub-nav-item <?php echo ($activePage == 'racks') ? 'active' : ''; ?>">
                        <i class="ri-stack-line"></i> Racks
                    </a>
                    <a href="suppliers.php"
                        class="sub-nav-item <?php echo ($activePage == 'suppliers') ? 'active' : ''; ?>">
                        <i class="ri-truck-line" style="font-size: 1rem; width: auto;"></i> Suppliers
                    </a>

                    <a href="quick-notes.php"
                        class="sub-nav-item <?php echo ($activePage == 'quick-notes') ? 'active' : ''; ?>">
                        <i class="ri-sticky-note-line" style="font-size: 1rem; width: auto;"></i> Quick Notes
                    </a>
                    <a href="global-settings.php"
                        class="sub-nav-item <?php echo ($activePage == 'global-settings') ? 'active' : ''; ?>">
                        <i class="ri-settings-5-line" style="font-size: 1rem; width: auto;"></i> OT Settings
                    </a>
                    <a href="bulk-upload.php"
                        class="sub-nav-item <?php echo ($activePage == 'bulk-upload') ? 'active' : ''; ?>"
                        style="border-top: 1px solid rgba(255,255,255,0.1); margin-top: 5px; padding-top: 8px; color: #fcd34d;">
                        <i class="ri-upload-cloud-2-line" style="font-size: 1rem; width: auto;"></i> Bulk Upload
                    </a>
                </div>
            <?php endif; ?>

            <!-- Core Operations -->
            <?php if (has_permission('orders')): ?>
                <a href="add-order.php" class="nav-item <?php echo ($activePage == 'add-order') ? 'active' : ''; ?>">
                    <i class="ri-add-circle-line"></i> New Order
                </a>
                <a href="orders.php" class="nav-item <?php echo ($activePage == 'orders') ? 'active' : ''; ?>">
                    <i class="ri-shopping-bag-line"></i> Orders
                    <?php if ($newOrdersCount > 0): ?>
                        <span class="sidebar-badge"><?= $newOrdersCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="outsourcing.php" class="nav-item <?php echo ($activePage == 'outsourcing') ? 'active' : ''; ?>">
                    <i class="ri-external-link-line"></i> Outsourcing
                </a>
            <?php endif; ?>

            <?php if (has_permission('employees_tasks')): ?>
                <a href="employees-tasks.php"
                    class="nav-item <?php echo ($activePage == 'employees-tasks') ? 'active' : ''; ?>">
                    <i class="ri-team-line"></i> Employees
                </a>
                <a href="tasks.php" class="nav-item <?php echo ($activePage == 'tasks') ? 'active' : ''; ?>">
                    <i class="ri-list-unordered"></i> Tasks
                </a>
            <?php endif; ?>

            <!-- HR Module -->
            <?php if (has_permission('hr')): ?>
                <div class="nav-item nav-item-parent <?php echo in_array($activePage, $hrPages) ? 'open' : ''; ?>"
                    onclick="toggleMenu('hr-menu')">
                    <div style="display: flex; align-items: center; gap: 0.75rem; flex: 1;">
                        <i class="ri-team-line"></i> HR
                        <?php if (($pendingLeaveCount + $pendingOTCount) > 0): ?>
                            <span class="sidebar-badge"><?= ($pendingLeaveCount + $pendingOTCount) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div id="hr-menu" class="sub-menu <?php echo in_array($activePage, $hrPages) ? 'open' : ''; ?>">
                    <a href="employees.php"
                        class="sub-nav-item <?php echo ($activePage == 'employees') ? 'active' : ''; ?>">
                        <i class="ri-team-line"></i> List Employees
                    </a>
                    <a href="employee-devices.php"
                        class="sub-nav-item <?php echo ($activePage == 'employee-devices') ? 'active' : ''; ?>">
                        <i class="ri-smartphone-line"></i> Employee Devices
                    </a>
                    <a href="add-employee.php"
                        class="sub-nav-item <?php echo ($activePage == 'add-employee') ? 'active' : ''; ?>">
                        <i class="ri-user-add-line"></i> Add Employee
                    </a>
                    <a href="working-hours.php"
                        class="sub-nav-item <?php echo ($activePage == 'hr_reports') ? 'active' : ''; ?>">
                        <i class="ri-time-line"></i> Working Hours
                    </a>
                    <a href="attendance.php"
                        class="sub-nav-item <?php echo ($activePage == 'attendance') ? 'active' : ''; ?>">
                        <i class="ri-checkbox-circle-line"></i> Attendance
                    </a>
                    <a href="shift-roster.php"
                        class="sub-nav-item <?php echo ($activePage == 'shift-roster') ? 'active' : ''; ?>">
                        <i class="ri-calendar-todo-line"></i> Shift Roster
                    </a>
                    <a href="payroll.php" class="sub-nav-item <?php echo ($activePage == 'payroll') ? 'active' : ''; ?>">
                        <i class="ri-money-dollar-circle-line"></i> Payroll
                    </a>
                    <a href="ot-requests.php"
                        class="sub-nav-item <?php echo ($activePage == 'ot-requests') ? 'active' : ''; ?>">
                        <i class="ri-timer-flash-line"></i> OT Requests
                        <?php if ($pendingOTCount > 0): ?>
                            <span class="sidebar-badge"><?= $pendingOTCount ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="leaves.php" class="sub-nav-item <?php echo ($activePage == 'leaves') ? 'active' : ''; ?>">
                        <i class="ri-umbrella-line"></i> Leave Requests
                        <?php if ($pendingLeaveCount > 0): ?>
                            <span class="sidebar-badge"><?= $pendingLeaveCount ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="leave-types.php"
                        class="sub-nav-item <?php echo ($activePage == 'leave-types') ? 'active' : ''; ?>">
                        <i class="ri-settings-4-line"></i> Leave Settings
                    </a>
                    <a href="holidays.php" class="sub-nav-item <?php echo ($activePage == 'holidays') ? 'active' : ''; ?>">
                        <i class="ri-calendar-event-line"></i> Holidays
                    </a>
                </div>
            <?php endif; ?>

            <?php if (has_permission('appointments')): ?>
                <a href="appointments.php" class="nav-item <?php echo ($activePage == 'appointments') ? 'active' : ''; ?>">
                    <i class="ri-calendar-event-line"></i> Appointments
                    <?php if ($newAppointmentCount > 0): ?>
                        <span class="sidebar-badge"><?= $newAppointmentCount ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

            <!-- Inventory & Sourcing -->
            <?php if (has_permission('inventory')): ?>
                <div class="nav-item nav-item-parent <?php echo in_array($activePage, $inventoryPages) ? 'open' : ''; ?>"
                    onclick="toggleMenu('inventory-menu')">
                    <div style="display: flex; align-items: center; gap: 0.75rem; flex: 1;">
                        <i class="ri-archive-line"></i> Inventory & Stock
                    </div>
                </div>
                <div id="inventory-menu"
                    class="sub-menu <?php echo in_array($activePage, $inventoryPages) ? 'open' : ''; ?>">
                    <a href="inventory.php"
                        class="sub-nav-item <?php echo ($activePage == 'inventory') ? 'active' : ''; ?>">
                        <i class="ri-database-2-line"></i> Main Inventory
                    </a>
                    <a href="purchase-orders.php"
                        class="sub-nav-item <?php echo ($activePage == 'purchase-orders') ? 'active' : ''; ?>">
                        <i class="ri-file-list-3-line"></i> Purchase Orders
                    </a>
                    <a href="inventory-categories.php"
                        class="sub-nav-item <?php echo ($activePage == 'inventory-categories') ? 'active' : ''; ?>">
                        <i class="ri-price-tag-line"></i> Categories
                    </a>
                    <a href="procurement.php"
                        class="sub-nav-item <?php echo ($activePage == 'procurement') ? 'active' : ''; ?>">
                        <i class="ri-truck-line"></i> Procurement
                    </a>
                    <a href="inventory-reports.php"
                        class="sub-nav-item <?php echo ($activePage == 'inventory-reports') ? 'active' : ''; ?>">
                        <i class="ri-file-chart-line"></i> Inventory Reports
                    </a>
                </div>
            <?php endif; ?>

            <!-- Asset Management -->
            <?php if (has_permission('assets')): ?>
                <div class="nav-item nav-item-parent <?php echo in_array($activePage, $assetsPages) ? 'open' : ''; ?>"
                    onclick="toggleMenu('assets-menu')">
                    <div style="display: flex; align-items: center; gap: 0.75rem; flex: 1;">
                        <i class="ri-briefcase-line"></i> Asset Management
                    </div>
                </div>
                <div id="assets-menu" class="sub-menu <?php echo in_array($activePage, $assetsPages) ? 'open' : ''; ?>">
                    <a href="assets.php" class="sub-nav-item <?php echo ($activePage == 'assets') ? 'active' : ''; ?>">
                        <i class="ri-tools-line"></i> All Assets
                    </a>
                    <a href="asset-categories.php"
                        class="sub-nav-item <?php echo ($activePage == 'asset-categories') ? 'active' : ''; ?>">
                        <i class="ri-folder-open-line"></i> Asset Categories
                    </a>
                    <a href="asset-reports.php"
                        class="sub-nav-item <?php echo ($activePage == 'asset-reports') ? 'active' : ''; ?>">
                        <i class="ri-file-list-3-line"></i> Asset Reports
                    </a>
                </div>
            <?php endif; ?>

            <!-- Finance & Billing -->
            <?php if (has_permission('finance')): ?>
                <div class="nav-item nav-item-parent <?php echo in_array($activePage, $financePages) ? 'open' : ''; ?>"
                    onclick="toggleMenu('finance-menu')">
                    <div style="display: flex; align-items: center; gap: 0.75rem; flex: 1;">
                        <i class="ri-coins-line"></i> Finance & Billing
                    </div>
                </div>
                <div id="finance-menu" class="sub-menu <?php echo in_array($activePage, $financePages) ? 'open' : ''; ?>">
                    <a href="billing.php" class="sub-nav-item <?php echo ($activePage == 'billing') ? 'active' : ''; ?>">
                        <i class="ri-bill-line"></i> Billing & Invoices
                    </a>
                    <a href="payments.php" class="sub-nav-item <?php echo ($activePage == 'payments') ? 'active' : ''; ?>">
                        <i class="ri-secure-payment-line"></i> Transactions
                    </a>
                    <a href="expense-categories.php"
                        class="sub-nav-item <?php echo ($activePage == 'expense-categories') ? 'active' : ''; ?>">
                        <i class="ri-price-tag-3-line"></i> Expense Categories
                    </a>
                    <a href="expenses.php" class="sub-nav-item <?php echo ($activePage == 'expenses') ? 'active' : ''; ?>">
                        <i class="ri-creative-commons-nc-line"></i> Expenses
                    </a>
                </div>
            <?php endif; ?>

            <!-- CRM & Other -->
            <?php if (has_permission('customers')): ?>
                <a href="customers.php" class="nav-item <?php echo ($activePage == 'customers') ? 'active' : ''; ?>">
                    <i class="ri-user-star-line"></i> Customers
                </a>
            <?php endif; ?>

            <?php if (has_permission('reports')): ?>
                <a href="reports.php" class="nav-item <?php echo ($activePage == 'reports') ? 'active' : ''; ?>">
                    <i class="ri-bar-chart-fill"></i> Reports
                </a>
            <?php endif; ?>

            <?php if (has_permission('support')): ?>
                <a href="support.php" class="nav-item <?php echo ($activePage == 'support') ? 'active' : ''; ?>">
                    <i class="ri-customer-service-2-line"></i> Support System
                </a>
            <?php endif; ?>

            <!-- Role Permissions Management -->
            <?php if (has_permission('permissions')): ?>
                <a href="permissions.php" class="nav-item <?php echo ($activePage == 'permissions') ? 'active' : ''; ?>"
                    style="border-top: 1px solid rgba(255,255,255,0.1); margin-top: 5px; padding-top: 8px; color: #fcd34d;">
                    <i class="ri-shield-user-line" style="font-size: 1.1rem;"></i> Role Permissions
                </a>
            <?php endif; ?>

            <!-- Accountant Panel Link (for accountants or super admins) -->
            <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'super_admin' || ($_SESSION['active_role'] ?? '') === 'Accountant')): ?>
                <a href="accountant/dashboard.php" class="nav-item"
                    style="border-top: 1px solid #334155; margin-top: 1rem; background: #1e293b;">
                    <i class="ri-user-settings-fill"></i> Accountant Panel
                </a>
            <?php endif; ?>

            <a href="../includes/logout.php" class="nav-item logout">
                <i class="ri-logout-circle-r-line"></i> Logout
            </a>
        </nav>
    </aside>

    <!-- Wrapper for Main + Right -->
    <div class="layout-wrapper">

        <script>
            function toggleMenu(menuId) {
                const menu = document.getElementById(menuId);
                menu.classList.toggle("open");
            }
        </script>