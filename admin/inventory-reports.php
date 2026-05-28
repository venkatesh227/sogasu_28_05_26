<?php
session_start();
include '../includes/db.php';

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

$dateFilterInv = "";
$dateFilterIssuance = "";
$params = [];
$paramsIssuance = [];

if ($start_date && $end_date) {
    $dateFilterInv = " AND DATE(i.updated_at) BETWEEN ? AND ?";
    $dateFilterIssuance = " AND s.issue_date BETWEEN ? AND ?";
    $params = [$start_date, $end_date];
    $paramsIssuance = [$start_date, $end_date];
} elseif ($start_date) {
    $dateFilterInv = " AND DATE(i.updated_at) >= ?";
    $dateFilterIssuance = " AND s.issue_date >= ?";
    $params = [$start_date];
    $paramsIssuance = [$start_date];
} elseif ($end_date) {
    $dateFilterInv = " AND DATE(i.updated_at) <= ?";
    $dateFilterIssuance = " AND s.issue_date <= ?";
    $params = [$end_date];
    $paramsIssuance = [$end_date];
}

// Fetch inventory data
$stmt = $pdo->prepare("SELECT i.*, c.name as category_name 
                     FROM inventory i 
                     LEFT JOIN inventory_categories c ON i.category = c.code 
                     WHERE i.is_deleted = 0 $dateFilterInv
                     ORDER BY i.item_name ASC");
$stmt->execute($params);
$all_inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Low Stock Data
$stmt_low = $pdo->prepare("SELECT i.*, c.name as category_name 
                         FROM inventory i 
                         LEFT JOIN inventory_categories c ON i.category = c.code 
                         WHERE i.is_deleted = 0 AND i.quantity <= IFNULL(i.low_stock_alert, 10) $dateFilterInv
                         ORDER BY i.quantity ASC");
$stmt_low->execute($params);
$low_stock = $stmt_low->fetchAll(PDO::FETCH_ASSOC);

// Stock Issuance Log
// Assuming employees table has first_name, last_name
$issuance_logs = [];
try {
    $stmt_issuance = $pdo->prepare("
        SELECT s.*, p.material_name, e.first_name, e.last_name 
        FROM stock_issuance s
        LEFT JOIN procurement p ON s.procurement_id = p.id
        LEFT JOIN employees e ON s.employee_id = e.id
        WHERE 1=1 $dateFilterIssuance
        ORDER BY s.issue_date DESC, s.id DESC
    ");
    $stmt_issuance->execute($paramsIssuance);
    $issuance_logs = $stmt_issuance->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist or schema differs, handle gracefully
}

// Valuation Data (Category wise summary)
$valuation_summary = [];
$total_inventory_value = 0;
foreach ($all_inventory as $item) {
    $cat = $item['category_name'] ? $item['category_name'] : 'Uncategorized';
    $val = $item['quantity'] * $item['cost'];
    $total_inventory_value += $val;
    if (!isset($valuation_summary[$cat])) {
        $valuation_summary[$cat] = 0;
    }
    $valuation_summary[$cat] += $val;
}

$pageTitle = "Inventory Reports - Sogasu";
$activePage = "inventory-reports";

include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div class="reports-container" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Inventory & Stock Reports</h2>
                <p style="color: #64748b; margin-top: 0.25rem;">Detailed analysis and tracking of your stock.</p>
            </div>
            <div style="display: flex; gap: 1rem; align-items: center;">
                <form method="GET" action="" style="display: flex; gap: 0.5rem; align-items: center; background: white; padding: 0.5rem; border-radius: 8px; border: 1px solid #e2e8f0; margin: 0;">
                    <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" style="border: 1px solid #cbd5e1; border-radius: 4px; padding: 4px 8px; font-size: 0.85rem; outline: none; color: #475569;" title="Start Date">
                    <span style="color: #64748b; font-size: 0.85rem;">to</span>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" style="border: 1px solid #cbd5e1; border-radius: 4px; padding: 4px 8px; font-size: 0.85rem; outline: none; color: #475569;" title="End Date">
                    <button type="submit" style="background: #10b981; color: white; border: none; padding: 5px 12px; border-radius: 4px; font-size: 0.85rem; font-weight: 600; cursor: pointer;">Filter</button>
                    <?php if($start_date || $end_date): ?>
                        <a href="inventory-reports.php" style="background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; padding: 5px 12px; border-radius: 4px; font-size: 0.85rem; font-weight: 600; cursor: pointer; text-decoration: none;">Clear</a>
                    <?php endif; ?>
                </form>
                <!-- Add Export Button If Needed In Future -->
                <button onclick="window.print()" class="btn btn-primary" style="background: #4f46e5; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 600; color: white; display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; margin: 0;">
                    <i class="ri-printer-line"></i> Print Report
                </button>
            </div>
        </div>

        <!-- Metric Cards -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; margin-bottom: 1.5rem;">
            <div class="table-container metric-card" style="padding: 1.25rem; margin-top: 0; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Total Stock Value</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: #3b82f6; margin-top: 0.5rem;">₹ <?= number_format($total_inventory_value, 2) ?></div>
                </div>
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    <i class="ri-money-rupee-circle-line"></i>
                </div>
            </div>
            <div class="table-container metric-card" style="padding: 1.25rem; margin-top: 0; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Low Stock Items</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: #ef4444; margin-top: 0.5rem;"><?= count($low_stock) ?></div>
                </div>
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(239, 68, 68, 0.1); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    <i class="ri-alarm-warning-line"></i>
                </div>
            </div>
            <div class="table-container metric-card" style="padding: 1.25rem; margin-top: 0; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Total Items Tracked</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: #10b981; margin-top: 0.5rem;"><?= count($all_inventory) ?></div>
                </div>
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(16, 185, 129, 0.1); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    <i class="ri-archive-line"></i>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs-wrapper" style="margin-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; gap: 1rem;">
            <div class="tab active" data-target="current-stock" style="padding: 0.75rem 1rem; font-weight: 600; cursor: pointer; color: #4f46e5; border-bottom: 2px solid #4f46e5;">Current Stock</div>
            <div class="tab" data-target="low-stock" style="padding: 0.75rem 1rem; font-weight: 600; cursor: pointer; color: #64748b; border-bottom: 2px solid transparent;">Low Stock Alerts</div>
            <div class="tab" data-target="valuation" style="padding: 0.75rem 1rem; font-weight: 600; cursor: pointer; color: #64748b; border-bottom: 2px solid transparent;">Valuation Summary</div>
            <div class="tab" data-target="issuance" style="padding: 0.75rem 1rem; font-weight: 600; cursor: pointer; color: #64748b; border-bottom: 2px solid transparent;">Issuance Log</div>
        </div>

        <!-- Tab Contents -->
        
        <!-- Current Stock -->
        <div id="current-stock" class="tab-content active" style="display: block;">
            <div class="table-container">
                <table class="table datatable">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Unit Cost</th>
                            <th>Total Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($all_inventory as $item): ?>
                            <tr>
                                <td style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($item['item_name']) ?></td>
                                <td><span style="background: #f1f5f9; padding: 3px 8px; border-radius: 6px; font-size: 0.8rem;"><?= htmlspecialchars($item['category_name'] ?: ($item['category'] ?: 'Uncategorized')) ?></span></td>
                                <td style="font-weight: 700; color: <?= $item['quantity'] <= ($item['low_stock_alert']??10) ? '#ef4444' : '#1e293b' ?>;">
                                    <?= floatval($item['quantity']) ?> <?= htmlspecialchars($item['unit'] ?? '') ?>
                                </td>
                                <td>₹ <?= number_format($item['cost'], 2) ?></td>
                                <td style="font-weight: 700; color: #4f46e5;">₹ <?= number_format($item['quantity'] * $item['cost'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Low Stock -->
        <div id="low-stock" class="tab-content" style="display: none;">
            <div class="table-container">
                <table class="table datatable">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Current Stock</th>
                            <th>Alert Threshold</th>
                            <th>Supplier Info</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($low_stock as $item): ?>
                            <tr>
                                <td style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($item['item_name']) ?></td>
                                <td><span style="background: #f1f5f9; padding: 3px 8px; border-radius: 6px; font-size: 0.8rem;"><?= htmlspecialchars($item['category_name'] ?: 'Uncategorized') ?></span></td>
                                <td style="font-weight: 700; color: #ef4444;"><?= floatval($item['quantity']) ?> <?= htmlspecialchars($item['unit'] ?? '') ?></td>
                                <td><?= floatval($item['low_stock_alert'] ?? 10) ?> <?= htmlspecialchars($item['unit'] ?? '') ?></td>
                                <td>
                                    <?php if($item['supplier_name']): ?>
                                        <div style="font-weight: 600;"><?= htmlspecialchars($item['supplier_name']) ?></div>
                                        <div style="font-size: 0.8rem; color: #64748b;"><?= htmlspecialchars($item['supplier_contact']) ?></div>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-style: italic;">No supplier info</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Valuation Summary -->
        <div id="valuation" class="tab-content" style="display: none;">
            <div class="table-container" style="max-width: 600px;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th style="text-align: right;">Total Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($valuation_summary as $cat => $val): ?>
                            <tr>
                                <td style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($cat) ?></td>
                                <td style="text-align: right; font-weight: 700; color: #4f46e5;">₹ <?= number_format($val, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f8fafc;">
                            <td style="font-weight: 800; color: #1e293b;">Grand Total</td>
                            <td style="text-align: right; font-weight: 800; color: #4f46e5;">₹ <?= number_format($total_inventory_value, 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Issuance Log -->
        <div id="issuance" class="tab-content" style="display: none;">
            <div class="table-container">
                <table class="table datatable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Material / Item</th>
                            <th>Issued To</th>
                            <th>Order ID</th>
                            <th>Quantity Issued</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($issuance_logs as $log): ?>
                            <tr>
                                <td><?= date('d M Y', strtotime($log['issue_date'])) ?></td>
                                <td style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($log['material_name'] ?: 'Unknown Material') ?></td>
                                <td><?= htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) ?></td>
                                <td><?= $log['order_id'] ? '#'.htmlspecialchars($log['order_id']) : '-' ?></td>
                                <td style="font-weight: 700;"><?= floatval($log['quantity_issued']) ?></td>
                                <td style="font-size: 0.85rem; color: #64748b;"><?= htmlspecialchars($log['notes']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(empty($issuance_logs)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: #64748b; padding: 2rem;">No issuance records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

<style>
    @media print {
        .sidebar, .topbar-wrapper, .tabs-wrapper, .btn {
            display: none !important;
        }
        .main-content {
            margin-left: 0 !important;
            padding: 0 !important;
        }
        .tab-content {
            display: block !important;
            margin-bottom: 2rem;
            page-break-inside: avoid;
        }
        .metric-card {
            border: 1px solid #e2e8f0 !important;
        }
        body { background: white !important; }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Initialize DataTables
        $('.datatable').DataTable({
            pageLength: 25,
            language: { search: "", searchPlaceholder: "Search records..." }
        });

        // Tab Switching
        const tabs = document.querySelectorAll('.tab');
        const contents = document.querySelectorAll('.tab-content');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                // Remove active class from all
                tabs.forEach(t => {
                    t.classList.remove('active');
                    t.style.color = '#64748b';
                    t.style.borderBottomColor = 'transparent';
                });
                contents.forEach(c => c.style.display = 'none');

                // Add active class to clicked
                tab.classList.add('active');
                tab.style.color = '#4f46e5';
                tab.style.borderBottomColor = '#4f46e5';
                
                const targetId = tab.getAttribute('data-target');
                document.getElementById(targetId).style.display = 'block';
            });
        });
    });
</script>

</body>
</html>
