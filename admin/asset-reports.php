<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// 1. Maintenance Expenses Report
$maintParams = [];
$maintQuery = "SELECT l.*, a.name as asset_name, a.asset_code 
               FROM asset_maintenance_logs l
               JOIN assets a ON l.asset_id = a.id
               WHERE 1=1";
if ($start_date) {
    $maintQuery .= " AND l.repair_date >= ?";
    $maintParams[] = $start_date;
}
if ($end_date) {
    $maintQuery .= " AND l.repair_date <= ?";
    $maintParams[] = $end_date;
}
$maintQuery .= " ORDER BY l.repair_date DESC";
$stmt = $pdo->prepare($maintQuery);
$stmt->execute($maintParams);
$maintenanceLogs = $stmt->fetchAll();

$totalMaintenanceCost = 0;
foreach($maintenanceLogs as $log) {
    $totalMaintenanceCost += floatval($log['cost']);
}

// 2. Upcoming Service Schedule
$serviceParams = [];
$serviceQuery = "SELECT id, name, asset_code, next_service_date, condition_status 
                 FROM assets 
                 WHERE next_service_date IS NOT NULL";
if ($start_date) {
    $serviceQuery .= " AND next_service_date >= ?";
    $serviceParams[] = $start_date;
}
if ($end_date) {
    $serviceQuery .= " AND next_service_date <= ?";
    $serviceParams[] = $end_date;
}
$serviceQuery .= " ORDER BY next_service_date ASC";
$stmt = $pdo->prepare($serviceQuery);
$stmt->execute($serviceParams);
$serviceSchedules = $stmt->fetchAll();

// 3. Asset Utilization
$stmt = $pdo->query("SELECT a.id, a.name, a.asset_code, c.name as category_name, 
                     a.stock_quantity, a.condition_status,
                     CONCAT(e.first_name, ' ', e.last_name) as assigned_to
                     FROM assets a
                     LEFT JOIN asset_categories c ON a.category_id = c.id
                     LEFT JOIN employees e ON a.assigned_employee_id = e.id
                     ORDER BY a.name ASC");
$utilizations = $stmt->fetchAll();

// 4. Condition Overview (Aggregated)
$stmt = $pdo->query("SELECT condition_status, SUM(stock_quantity) as total_qty 
                     FROM assets 
                     GROUP BY condition_status");
$conditions = $stmt->fetchAll();

$pageTitle = "Asset Reports - Sogasu";
$activePage = "asset-reports";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div>
        <!-- Header -->
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h2 style="font-size: 1.75rem; font-weight: 700; color: #1e293b; margin-bottom: 0.25rem;">Asset Reports</h2>
                <p style="color: #64748b; margin: 0;">Comprehensive analytics for maintenance, assignments, and asset health.</p>
            </div>
            <div style="display: flex; gap: 1rem;">
                <form method="GET" style="display: flex; gap: 1rem; align-items: flex-end; background: white; padding: 0.75rem; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div>
                        <label style="font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem; display: block;">From Date</label>
                        <input type="date" name="start_date" class="form-control" style="height: 36px;" value="<?= htmlspecialchars($start_date) ?>">
                    </div>
                    <div>
                        <label style="font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem; display: block;">To Date</label>
                        <input type="date" name="end_date" class="form-control" style="height: 36px;" value="<?= htmlspecialchars($end_date) ?>">
                    </div>
                    <button type="submit" class="btn btn-primary" style="height: 36px; padding: 0 1rem;">Filter</button>
                    <?php if($start_date || $end_date): ?>
                        <a href="asset-reports.php" class="btn" style="background: #f1f5f9; color: #64748b; height: 36px; line-height: 24px; text-decoration: none; border: 1px solid #cbd5e1;">Clear</a>
                    <?php endif; ?>
                </form>
                <button class="btn btn-secondary" style="height: auto;" onclick="window.print()"><i class="ri-printer-line"></i> Print</button>
            </div>
        </div>

        <!-- Custom Tabs Navigation -->
        <div class="custom-tabs" style="display: flex; gap: 2rem; border-bottom: 2px solid #e2e8f0; margin-bottom: 2rem; overflow-x: auto;">
            <button class="tab-btn active" onclick="switchTab('tabMaintenance')" style="background: none; border: none; padding: 0.75rem 0; font-size: 1rem; font-weight: 600; color: #4f46e5; border-bottom: 3px solid #4f46e5; cursor: pointer;">Maintenance Expenses</button>
            <button class="tab-btn" onclick="switchTab('tabService')" style="background: none; border: none; padding: 0.75rem 0; font-size: 1rem; font-weight: 600; color: #64748b; border-bottom: 3px solid transparent; cursor: pointer;">Service Schedule</button>
            <button class="tab-btn" onclick="switchTab('tabUtilization')" style="background: none; border: none; padding: 0.75rem 0; font-size: 1rem; font-weight: 600; color: #64748b; border-bottom: 3px solid transparent; cursor: pointer;">Asset Utilization</button>
            <button class="tab-btn" onclick="switchTab('tabCondition')" style="background: none; border: none; padding: 0.75rem 0; font-size: 1rem; font-weight: 600; color: #64748b; border-bottom: 3px solid transparent; cursor: pointer;">Condition Overview</button>
        </div>

        <div class="report-content" id="printableArea">
            
            <!-- Tab 1: Maintenance Expenses -->
            <div id="tabMaintenance" class="tab-pane active" style="display: block;">
                <div style="background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #f1f5f9;">
                        <h3 style="margin: 0; font-size: 1.25rem; color: #1e293b;">Maintenance Expense Log</h3>
                        <div style="background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 700; font-size: 1.1rem;">
                            Total: ₹<?= number_format($totalMaintenanceCost, 2) ?>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="report-table display" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Repair Date</th>
                                    <th>Asset Name</th>
                                    <th>Code</th>
                                    <th>Notes/Details</th>
                                    <th>Cost (₹)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($maintenanceLogs as $log): ?>
                                <tr>
                                    <td><?= date('d M Y', strtotime($log['repair_date'])) ?></td>
                                    <td style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($log['asset_name']) ?></td>
                                    <td style="font-family: monospace;"><?= htmlspecialchars($log['asset_code']) ?></td>
                                    <td style="color: #64748b;"><?= htmlspecialchars($log['notes'] ?: '-') ?></td>
                                    <td style="font-weight: 600; color: #dc2626;">₹<?= number_format($log['cost'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Service Schedule -->
            <div id="tabService" class="tab-pane" style="display: none;">
                <div style="background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                    <div style="margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #f1f5f9;">
                        <h3 style="margin: 0; font-size: 1.25rem; color: #1e293b;">Upcoming Service Schedule</h3>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="report-table display" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Next Service Date</th>
                                    <th>Asset Name</th>
                                    <th>Code</th>
                                    <th>Current Condition</th>
                                    <th>Status Alert</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($serviceSchedules as $schedule): 
                                    $days_left = (strtotime($schedule['next_service_date']) - time()) / (60 * 60 * 24);
                                ?>
                                <tr>
                                    <td style="font-weight: 600;"><?= date('d M Y', strtotime($schedule['next_service_date'])) ?></td>
                                    <td><?= htmlspecialchars($schedule['name']) ?></td>
                                    <td style="font-family: monospace;"><?= htmlspecialchars($schedule['asset_code']) ?></td>
                                    <td><?= htmlspecialchars($schedule['condition_status']) ?></td>
                                    <td>
                                        <?php if($days_left <= 0): ?>
                                            <span style="background: #fee2e2; color: #dc2626; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700;"><i class="ri-error-warning-line"></i> OVERDUE</span>
                                        <?php elseif($days_left <= 30): ?>
                                            <span style="background: #fef3c7; color: #d97706; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700;"><i class="ri-alert-line"></i> DUE SOON</span>
                                        <?php else: ?>
                                            <span style="background: #f1f5f9; color: #64748b; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">Upcoming</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Utilization -->
            <div id="tabUtilization" class="tab-pane" style="display: none;">
                <div style="background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                    <div style="margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #f1f5f9;">
                        <h3 style="margin: 0; font-size: 1.25rem; color: #1e293b;">Asset Utilization & Assignments</h3>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="report-table display" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Asset Name</th>
                                    <th>Category</th>
                                    <th>Qty</th>
                                    <th>Assigned Employee</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($utilizations as $item): ?>
                                <tr>
                                    <td style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($item['name']) ?></td>
                                    <td><?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?></td>
                                    <td><?= htmlspecialchars($item['stock_quantity']) ?></td>
                                    <td>
                                        <?php if($item['assigned_to']): ?>
                                            <span style="color: #4f46e5; font-weight: 600;"><i class="ri-user-3-line"></i> <?= htmlspecialchars($item['assigned_to']) ?></span>
                                        <?php else: ?>
                                            <span style="color: #94a3b8; font-style: italic;">Unassigned (Available)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($item['condition_status']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab 4: Condition Overview -->
            <div id="tabCondition" class="tab-pane" style="display: none;">
                <div style="background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                    <div style="margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #f1f5f9;">
                        <h3 style="margin: 0; font-size: 1.25rem; color: #1e293b;">Overall Condition Summary</h3>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table" style="width:100%; border-collapse: collapse;">
                            <thead style="background: #f8fafc;">
                                <tr>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #e2e8f0;">Condition Status</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #e2e8f0;">Total Assets</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($conditions as $cond): 
                                    $color = '#64748b';
                                    if($cond['condition_status'] === 'Good') $color = '#16a34a';
                                    if($cond['condition_status'] === 'Needs Repair') $color = '#f59e0b';
                                    if($cond['condition_status'] === 'Broken') $color = '#dc2626';
                                ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 1rem; font-weight: 700; color: <?= $color ?>;"><?= htmlspecialchars($cond['condition_status']) ?></td>
                                    <td style="padding: 1rem; font-size: 1.1rem; font-weight: 600;"><?= intval($cond['total_qty']) ?> Units</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div> <!-- /.report-content -->
    </div>
</main>

<?php include __DIR__ . '/includes/datatable.php'; ?>

<script>
    // Initialize DataTables for all report tables
    $(document).ready(function() {
        $('.report-table').DataTable({
            "pageLength": 25,
            "order": [], // Let PHP handle initial ordering
            "language": {
                "search": "",
                "searchPlaceholder": "Search report..."
            },
            "dom": '<"top"f>rt<"bottom"lip><"clear">'
        });
    });

    // Custom Tab Switching Logic
    function switchTab(tabId) {
        // Hide all panes
        const panes = document.querySelectorAll('.tab-pane');
        panes.forEach(pane => pane.style.display = 'none');
        
        // Show active pane
        document.getElementById(tabId).style.display = 'block';
        
        // Reset button styles
        const btns = document.querySelectorAll('.tab-btn');
        btns.forEach(btn => {
            btn.style.color = '#64748b';
            btn.style.borderColor = 'transparent';
        });
        
        // Highlight active button
        const activeBtn = event.currentTarget;
        activeBtn.style.color = '#4f46e5';
        activeBtn.style.borderColor = '#4f46e5';
    }
</script>

<style>
    .report-table th { padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem; border-bottom: 2px solid #e2e8f0; }
    .report-table td { padding: 1rem; border-bottom: 1px solid #f1f5f9; }
    .dataTables_filter input { padding: 0.5rem 1rem; border: 1px solid #cbd5e1; border-radius: 6px; outline: none; margin-bottom: 1rem; width: 300px; }
    .dataTables_filter input:focus { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
    
    @media print {
        .sidebar, .topbar, .custom-tabs, form, .dataTables_filter, .dataTables_length, .dataTables_info, .dataTables_paginate {
            display: none !important;
        }
        .main-content { margin-left: 0 !important; padding: 0 !important; }
        .report-content { box-shadow: none !important; }
        .tab-pane { display: block !important; margin-bottom: 2rem; page-break-inside: avoid; }
        body { background: white; }
    }
</style>

<?php include 'includes/footer.php'; ?>
