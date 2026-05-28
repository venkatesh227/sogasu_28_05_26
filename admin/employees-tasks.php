<?php
ob_start();
session_start();
include '../includes/db.php';

// Fetch Employees with active task aggregation and deadline metrics
$stmt = $pdo->query("
    SELECT 
        e.*,
        COALESCE(t.total_active, 0) as total_active,
        COALESCE(t.total_overdue, 0) as total_overdue,
        t.busy_upto
    FROM employees e
    LEFT JOIN (
        SELECT 
            assigned_employee_id,
            COUNT(*) as total_active,
            SUM(CASE WHEN due_date < CURDATE() THEN 1 ELSE 0 END) as total_overdue,
            MAX(due_date) as busy_upto
        FROM orders
        WHERE is_deleted = 0 AND order_status NOT IN ('completed', 'delivered', 'cancelled')
        GROUP BY assigned_employee_id
    ) t ON e.id = t.assigned_employee_id
    WHERE e.is_deleted = 0
    ORDER BY e.id DESC
");
$employees = $stmt->fetchAll();

// Calculate Theme-Specific Task Metrics
$total_staff = count($employees);
$active_workloads = 0;
$free_staff = 0;
foreach ($employees as $row) {
    if ($row['total_active'] > 0) {
        $active_workloads++;
    } else {
        $free_staff++;
    }
}

$pageTitle = "Employee Tasks & Performance - Sogasu";
$activePage = "employees-tasks";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div>
        
        <!-- Standard Header -->
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Employee Tasks & Performance</h2>
                <p style="color: #64748b; margin-top: 0.25rem;">Monitor staff workloads, pending assignments, and real-time delivery metrics.</p>
            </div>
        </div>

        <!-- Compact Theme-Specific Stats Cards -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; margin-top: 1.5rem; margin-bottom: 1.5rem;">
            <div class="table-container" style="padding: 1.25rem; margin-top: 0; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Total Staff</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: #4f46e5; margin-top: 0.5rem;"><?= $total_staff ?></div>
                </div>
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(79, 70, 229, 0.1); color: #4f46e5; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    <i class="ri-team-line"></i>
                </div>
            </div>

            <div class="table-container" style="padding: 1.25rem; margin-top: 0; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Active Workloads</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: #e11d48; margin-top: 0.5rem;"><?= $active_workloads ?></div>
                </div>
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(225, 29, 72, 0.1); color: #e11d48; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    <i class="ri-pulse-line"></i>
                </div>
            </div>

            <div class="table-container" style="padding: 1.25rem; margin-top: 0; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Available Staff</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: #10b981; margin-top: 0.5rem;"><?= $free_staff ?></div>
                </div>
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(16, 185, 129, 0.1); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    <i class="ri-user-follow-line"></i>
                </div>
            </div>
        </div>

        <!-- Employee Table Container -->
        <div class="table-container" style="padding: 1.5rem;">
            <div style="padding-bottom: 1.25rem; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0;">Staff Workload Directory</h3>
            </div>

            <div style="overflow-x: auto;">
                <table id="employeeTasksTable" class="table">
                    <thead>
                        <tr>
                            <th>Employee Details</th>
                            <th>Role & Branch</th>
                            <th>Total Tasks Working On</th>
                            <th>Status of Tasks</th>
                            <th>Busy Upto</th>
                            <th style="text-align: right; padding-right: 2rem;">Workload Monitor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $row): 
                            // Calculate task status pill badge properties
                            if ($row['total_active'] == 0) {
                                $statusText = "Available";
                                $statusStyle = "background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0;";
                            } else {
                                if ($row['total_overdue'] > 0) {
                                    $statusText = "Overdue Tasks";
                                    $statusStyle = "background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca;";
                                } else if ($row['total_active'] >= 5) {
                                    $statusText = "Very Busy";
                                    $statusStyle = "background: #fff1f2; color: #9f1239; border: 1px solid #ffe4e6;";
                                } else if ($row['total_active'] >= 3) {
                                    $statusText = "Busy";
                                    $statusStyle = "background: #fffbeb; color: #b45309; border: 1px solid #fde68a;";
                                } else {
                                    $statusText = "Moderate";
                                    $statusStyle = "background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;";
                                }
                            }
                        ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div style="width: 36px; height: 36px; border-radius: 50%; font-size: 0.8rem; background: #e2e8f0; color: #475569; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                            <?= strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 700; color: #1e293b;"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></div>
                                            <div style="font-size: 0.75rem; color: #64748b;">ID: EMP-<?= str_pad($row['id'], 3, '0', STR_PAD_LEFT) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($row['job_role']) ?></div>
                                    <div style="font-size: 0.75rem; color: #64748b;"><?= htmlspecialchars($row['branch']) ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: #1e293b; display: inline-flex; align-items: center;">
                                        <span><?= $row['total_active'] ?> Active</span>
                                        <?php if ($row['total_overdue'] > 0): ?>
                                            <span style="font-size: 0.65rem; background: #fee2e2; color: #ef4444; padding: 2px 6px; border-radius: 4px; font-weight: 800; text-transform: uppercase; margin-left: 6px; border: 1px solid #fecaca;"><?= $row['total_overdue'] ?> Overdue</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="display: inline-block; padding: 0.25rem 0.6rem; border-radius: 6px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; <?= $statusStyle ?>">
                                        <?= $statusText ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($row['busy_upto']): ?>
                                        <div style="font-weight: 600; color: #1e293b;"><i class="ri-calendar-line" style="color: #64748b; margin-right: 0.25rem; font-size: 0.9rem; vertical-align: middle;"></i> <?= date('d M, Y', strtotime($row['busy_upto'])) ?></div>
                                    <?php else: ?>
                                        <div style="font-style: italic; color: #94a3b8;"><i class="ri-calendar-line" style="color: #cbd5e1; margin-right: 0.25rem; font-size: 0.9rem; vertical-align: middle;"></i> N/A - Free</div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; padding-right: 2rem;">
                                    <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                        <a href="view-employee.php?id=<?= $row['id'] ?>#tasks-section" class="btn-icon-p" title="Tasks & Performance" style="color: var(--success);"><i class="ri-list-check"></i></a>
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
        border-color: var(--success-light);
        color: var(--success);
    }
</style>

<?php include __DIR__ . '/includes/datatable.php'; ?>
<script>
    $(document).ready(function() {
        initializeDataTable('employeeTasksTable', 'Staff Workloads', 5);
    });
</script>

<?php include 'includes/footer.php'; ?>
