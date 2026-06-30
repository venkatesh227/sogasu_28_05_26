<?php
session_start();
include '../includes/db.php';

// Fetch dynamic invoice details via AJAX
if (isset($_GET['action']) && $_GET['action'] === 'get_invoice_details' && isset($_GET['invoice_no'])) {
    header('Content-Type: application/json');
    $inv_no = trim($_GET['invoice_no']);
    
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE invoice_no = ? AND is_deleted = 0 ORDER BY id ASC");
    $stmt->execute([$inv_no]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'Invoice not found.']);
        exit;
    }
    
    $header = [
        'invoice_no' => $items[0]['invoice_no'],
        'invoice_date' => $items[0]['invoice_date'],
        'invoice_file' => $items[0]['invoice_file'],
        'supplier_name' => $items[0]['supplier_name'],
        'supplier_contact' => $items[0]['supplier_contact']
    ];
    
    echo json_encode([
        'success' => true,
        'header' => $header,
        'items' => $items
    ]);
    exit;
}

// Fetch dynamic metrics card breakdown details via AJAX
if (isset($_GET['action']) && $_GET['action'] === 'get_metrics_breakdown' && isset($_GET['metric'])) {
    header('Content-Type: application/json');
    $metric = trim($_GET['metric']);
    
    if ($metric === 'total_value') {
        $stmt = $pdo->query("SELECT id, item_name, sku, category, quantity, unit, cost FROM inventory WHERE is_deleted = 0 ORDER BY (cost * quantity) DESC");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'success' => true,
            'metric' => 'total_value',
            'items' => $items
        ]);
        exit;
    } elseif ($metric === 'low_stock') {
        $stmt = $pdo->query("SELECT id, item_name, sku, category, quantity, unit, cost FROM inventory WHERE is_deleted = 0 AND quantity <= low_stock_alert ORDER BY quantity ASC");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'success' => true,
            'metric' => 'low_stock',
            'items' => $items
        ]);
        exit;
    } elseif ($metric === 'categories') {
        $stmt = $pdo->query("SELECT c.name, c.code, COUNT(i.id) AS item_count 
                             FROM inventory_categories c 
                             LEFT JOIN inventory i ON c.code = i.category AND i.is_deleted = 0 
                             WHERE c.is_deleted = 0 AND c.status = 1 
                             GROUP BY c.code, c.name 
                             ORDER BY item_count DESC");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'success' => true,
            'metric' => 'categories',
            'items' => $items
        ]);
        exit;
    } elseif ($metric === 'total_items') {
        $stmt = $pdo->query("SELECT id, item_name, sku, category, quantity, unit, cost, low_stock_alert FROM inventory WHERE is_deleted = 0 ORDER BY item_name ASC");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'success' => true,
            'metric' => 'total_items',
            'items' => $items
        ]);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid metric type.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['status'])) {

    $stmt = $pdo->prepare("UPDATE inventory SET status = ? WHERE id = ?");

    echo json_encode([
        'success' => $stmt->execute([
            $_POST['status'],
            $_POST['id']
        ])
    ]);

    exit;
}
$stmt = $pdo->query("SELECT * FROM inventory WHERE is_deleted = 0 ORDER BY id DESC");
$items = $stmt->fetchAll();

$inventoryStats = $pdo->query("SELECT
    COALESCE(SUM(cost * quantity), 0) AS total_value,
    SUM(CASE WHEN quantity <= low_stock_alert THEN 1 ELSE 0 END) AS low_stock_count,
    (SELECT COUNT(*) FROM inventory_categories WHERE is_deleted = 0 AND status = 1) AS active_categories,
    COUNT(*) AS total_items
FROM inventory WHERE is_deleted = 0")->fetch();

$catMapStmt = $pdo->query("SELECT name, code FROM inventory_categories WHERE is_deleted = 0");
$categoryMap = [];
while ($c = $catMapStmt->fetch()) {
    $categoryMap[$c['code']] = $c['name'];
}

$categories_stmt = $pdo->query("SELECT * FROM inventory_categories WHERE is_deleted = 0 AND status = 1 ORDER BY name ASC");
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Inventory - Sogasu";
$activePage = "inventory";

include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div>
        
        <!-- Standard Header -->
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Inventory Management</h2>
                <p style="color: #64748b; margin-top: 0.25rem;">Track available stock received via Purchase Orders and issued through Procurement.</p>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <a href="add-purchase-order.php" class="btn" 
                    style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; color: #475569; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s;"
                    onmouseover="this.style.background='white'; this.style.borderColor='#4f46e5'; this.style.color='#4f46e5'" 
                    onmouseout="this.style.background='#f8fafc'; this.style.borderColor='#cbd5e1'; this.style.color='#475569'">
                    <i class="ri-file-list-3-line" style="color: #4f46e5;"></i> Raise Purchase Order
                </a>
                <a href="add-inventory.php" class="btn btn-primary" style="background: #4f46e5; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; color: white; display: inline-flex; align-items: center; gap: 0.5rem;">
                    <i class="ri-settings-3-line"></i> Stock Adjustment / Item Master
                </a>
            </div>
        </div>

        <!-- Stats Cards Grid -->
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.25rem;">
            <!-- Total Stock Value -->
            <div class="table-container metric-card" id="totalValueCard" style="padding: 1.25rem; margin-top: 0; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Total Stock Value</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: #3b82f6; margin-top: 0.5rem;">₹ <?= number_format($inventoryStats['total_value'], 0) ?></div>
                </div>
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    <i class="ri-money-rupee-circle-line"></i>
                </div>
            </div>

            <!-- Low Stock Items -->
            <div class="table-container metric-card" id="lowStockCard" style="padding: 1.25rem; margin-top: 0; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Low Stock Items</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: #ef4444; margin-top: 0.5rem;"><?= intval($inventoryStats['low_stock_count']) ?></div>
                </div>
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(239, 68, 68, 0.1); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    <i class="ri-alarm-warning-line"></i>
                </div>
            </div>

            <!-- Categories -->
            <div class="table-container metric-card" id="categoriesCard" style="padding: 1.25rem; margin-top: 0; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Categories</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: #8b5cf6; margin-top: 0.5rem;"><?= intval($inventoryStats['active_categories']) ?></div>
                </div>
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(139, 92, 246, 0.1); color: #8b5cf6; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    <i class="ri-function-line"></i>
                </div>
            </div>

            <!-- Total Stock Items -->
            <div class="table-container metric-card" id="totalItemsCard" style="padding: 1.25rem; margin-top: 0; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Total Stock Items</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: #10b981; margin-top: 0.5rem;"><?= intval($inventoryStats['total_items']) ?></div>
                </div>
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(16, 185, 129, 0.1); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    <i class="ri-archive-line"></i>
                </div>
            </div>
        </div>

        <!-- Inventory Table Container -->
        <div class="table-container" style="padding: 1.5rem;">
            <div style="padding-bottom: 1.25rem; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0;">Warehouse Stock</h3>
                <div style="display: flex; gap: 0.75rem; align-items: center;">
                    <!-- Category Filter -->
                    <select id="categoryFilter" style="padding: 6px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.82rem; font-weight: 600; color: #475569; outline: none; background: #fff; cursor: pointer;">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table id="inventoryTable" class="table">
                    <thead>
                        <tr>
                            <th>Item Details</th>
                            <th>Category</th>
                            <th>Stock Level</th>
                            <th>Unit Value</th>
                            <th>Last Activity</th>
                            <th style="text-align: right;">Operations</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $row): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: #1e293b;"><?= htmlspecialchars($row['item_name']) ?></div>
                                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 500;">SKU: <?= htmlspecialchars($row['sku']) ?></div>
                                    <?php if (!empty($row['invoice_no'])): ?>
                                        <div style="margin-top: 4px; display: inline-flex; align-items: center; gap: 4px; font-size: 0.7rem; background: rgba(79, 70, 229, 0.08); color: #4f46e5; padding: 2px 6px; border-radius: 4px; font-weight: 600;">
                                            <i class="ri-file-text-line" style="font-size: 0.8rem; color: #4f46e5;"></i>
                                            Invoice: <?= htmlspecialchars($row['invoice_no']) ?>
                                            <?php if (!empty($row['invoice_file'])): ?>
                                                <a href="../uploads/invoices/<?= htmlspecialchars($row['invoice_file']) ?>" target="_blank" title="View Document" style="color: #4f46e5; text-decoration: none; margin-left: 2px; display: inline-flex; align-items: center;">
                                                    <i class="ri-external-link-line" style="font-size: 0.75rem;"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-weight: 600; color: #475569; background: #f1f5f9; padding: 4px 10px; border-radius: 8px; font-size: 0.8rem;">
                                        <?= htmlspecialchars($categoryMap[$row['category']] ?? ucfirst($row['category'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: <?= $row['quantity'] <= $row['low_stock_alert'] ? '#ef4444' : '#1e293b' ?>;">
                                        <?= htmlspecialchars($row['quantity']) ?> <?= htmlspecialchars($row['unit']) ?>
                                    </div>
                                    <?php if($row['quantity'] <= $row['low_stock_alert']): ?>
                                        <span style="display: inline-block; font-size: 0.65rem; background: rgba(239, 68, 68, 0.1); color: #ef4444; font-weight: 800; padding: 2px 6px; border-radius: 4px; margin-top: 2px; text-transform: uppercase;">Low Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: #4f46e5;">₹ <?= number_format($row['cost'], 2) ?></div>
                                </td>
                                <td>
                                    <div style="font-size: 0.82rem; color: #64748b; font-weight: 500;"><?= date('d M Y, H:i', strtotime($row['updated_at'] ?? $row['created_at'])) ?></div>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                        <?php if (!empty($row['invoice_no'])): ?>
                                            <button class="btn-icon-p" onclick="openInvoiceModal('<?= htmlspecialchars($row['invoice_no'], ENT_QUOTES) ?>')" title="View Invoice Details" style="color: #10b981;">
                                                <i class="ri-eye-line"></i>
                                            </button>
                                        <?php endif; ?>
                                        <a href="add-inventory.php?id=<?= $row['id'] ?>" class="btn-icon-p" title="Edit Item" style="color: #4f46e5;"><i class="ri-edit-line"></i></a>
                                        <button class="btn-icon-p" onclick="confirmDelete(<?= $row['id'] ?>)" title="Remove Item" style="color: #ef4444;"><i class="ri-delete-bin-line"></i></button>
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
        border-color: var(--primary-light);
        color: var(--primary);
    }
    .metric-card {
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid transparent !important;
    }
    .metric-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.02) !important;
        border-color: #cbd5e1 !important;
    }
    .metric-card:active {
        transform: translateY(-1px);
    }
    .metric-card.active-filter {
        border-color: #ef4444 !important;
        background: rgba(239, 68, 68, 0.02) !important;
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15) !important;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const globalCategoryMap = <?= json_encode($categoryMap) ?>;

    function openMetricsModal(metricType) {
        const modal = document.getElementById('metricsBreakdownModal');
        const content = modal.querySelector('div:nth-child(2)');
        
        // Reset modal layout
        document.getElementById('metricsModalSubtitle').textContent = 'Loading Breakdown...';
        document.getElementById('metricsModalTitle').textContent = 'Loading...';
        document.getElementById('metricsModalTableHead').innerHTML = '';
        document.getElementById('metricsModalTableBody').innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 2rem; color: #64748b;"><i class="ri-loader-4-line ri-spin" style="font-size: 2rem;"></i></td></tr>';
        document.getElementById('metricsModalCount').textContent = 'Showing 0 items';
        document.getElementById('metricsModalFooterText').textContent = 'Calculated dynamically from real-time records.';
        const footerTotalBox = document.getElementById('metricsModalFooterTotalBox');
        footerTotalBox.style.display = 'none';
        document.getElementById('metricsModalFooterTotalVal').style.color = '#4f46e5'; // reset to indigo
        document.getElementById('metricsModalSearch').value = '';
        
        modal.style.display = 'flex';
        
        fetch('inventory.php?action=get_metrics_breakdown&metric=' + encodeURIComponent(metricType))
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const items = data.items || [];
                    const count = items.length;
                    
                    let title = '';
                    let subtitle = '';
                    let headHtml = '';
                    let bodyHtml = '';
                    
                    if (metricType === 'total_value') {
                        title = 'Stock Valuation Breakdown';
                        subtitle = 'Total Stock Value';
                        headHtml = `
                            <tr style="border-bottom: 2px solid #e2e8f0;">
                                <th style="text-align: left; padding: 0.75rem 0.5rem; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Item Description</th>
                                <th style="text-align: left; padding: 0.75rem 0.5rem; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Category</th>
                                <th style="text-align: right; padding: 0.75rem 0.5rem; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Quantity</th>
                                <th style="text-align: right; padding: 0.75rem 0.5rem; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Unit Price</th>
                                <th style="text-align: right; padding: 0.75rem 0.5rem; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Stock Value</th>
                            </tr>
                        `;
                        
                        let grandVal = 0;
                        items.forEach(item => {
                            const qty = parseFloat(item.quantity) || 0;
                            const cost = parseFloat(item.cost) || 0;
                            const totalVal = qty * cost;
                            grandVal += totalVal;
                            
                            const catName = globalCategoryMap[item.category] || item.category || 'N/A';
                            
                            bodyHtml += `
                                <tr class="modal-searchable-row" style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 0.75rem 0.5rem;">
                                        <div style="font-weight: 700; color: #1e293b;">${escapeHtml(item.item_name)}</div>
                                        <div style="font-size: 0.75rem; color: #64748b; font-weight: 500;">SKU: ${escapeHtml(item.sku || 'N/A')}</div>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem;">
                                        <span style="font-weight: 600; color: #475569; background: #f1f5f9; padding: 4px 10px; border-radius: 8px; font-size: 0.8rem;">${escapeHtml(catName)}</span>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; text-align: right; font-weight: 700; color: #1e293b;">
                                        ${qty} ${escapeHtml(item.unit || '')}
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; text-align: right; font-weight: 700; color: #4f46e5;">
                                        ₹ ${cost.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; text-align: right; font-weight: 700; color: #0f172a;">
                                        ₹ ${totalVal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </td>
                                </tr>
                            `;
                        });
                        
                        footerTotalBox.style.display = 'block';
                        document.getElementById('metricsModalFooterTotalLabel').textContent = 'Grand Total Value';
                        document.getElementById('metricsModalFooterTotalVal').textContent = '₹ ' + grandVal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        document.getElementById('metricsModalFooterText').textContent = `Total stock valuation across ${count} items.`;
                    } else if (metricType === 'low_stock') {
                        title = 'Low Stock Warnings';
                        subtitle = 'Inventory Alerts';
                        headHtml = `
                            <tr style="border-bottom: 2px solid #e2e8f0;">
                                <th style="text-align: left; padding: 0.75rem 0.5rem; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Item Description</th>
                                <th style="text-align: left; padding: 0.75rem 0.5rem; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Category</th>
                                <th style="text-align: right; padding: 0.75rem 0.5rem; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Stock Level</th>
                                <th style="text-align: right; padding: 0.75rem 0.5rem; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Unit Price</th>
                                <th style="text-align: right; padding: 0.75rem 0.5rem; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Actions</th>
                            </tr>
                        `;
                        
                        items.forEach(item => {
                            const qty = parseFloat(item.quantity) || 0;
                            const cost = parseFloat(item.cost) || 0;
                            const catName = globalCategoryMap[item.category] || item.category || 'N/A';
                            
                            bodyHtml += `
                                <tr class="modal-searchable-row" style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 0.75rem 0.5rem;">
                                        <div style="font-weight: 700; color: #1e293b;">${escapeHtml(item.item_name)}</div>
                                        <div style="font-size: 0.75rem; color: #64748b; font-weight: 500;">SKU: ${escapeHtml(item.sku || 'N/A')}</div>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem;">
                                        <span style="font-weight: 600; color: #475569; background: #f1f5f9; padding: 4px 10px; border-radius: 8px; font-size: 0.8rem;">${escapeHtml(catName)}</span>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; text-align: right;">
                                        <div style="font-weight: 700; color: #ef4444;">${qty} ${escapeHtml(item.unit || '')}</div>
                                        <span style="display: inline-block; font-size: 0.65rem; background: rgba(239, 68, 68, 0.1); color: #ef4444; font-weight: 800; padding: 2px 6px; border-radius: 4px; margin-top: 2px; text-transform: uppercase;">Low Stock</span>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; text-align: right; font-weight: 700; color: #4f46e5;">
                                        ₹ ${cost.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; text-align: right;">
                                        <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                            <a href="add-inventory.php?id=${item.id}" class="btn-icon-p" title="Edit Item" style="color: #4f46e5; width: 30px; height: 30px; font-size: 0.95rem; border-radius: 8px;"><i class="ri-edit-line"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        });
                        
                        footerTotalBox.style.display = 'block';
                        document.getElementById('metricsModalFooterTotalLabel').textContent = 'Critical Items';
                        document.getElementById('metricsModalFooterTotalVal').textContent = count;
                        document.getElementById('metricsModalFooterTotalVal').style.color = '#ef4444';
                        document.getElementById('metricsModalFooterText').textContent = 'Requires restocking immediately.';
                    } else if (metricType === 'categories') {
                        title = 'Category Distribution';
                        subtitle = 'Warehouse Categories';
                        headHtml = `
                            <tr style="border-bottom: 2px solid #e2e8f0;">
                                <th style="text-align: left; padding: 0.75rem 0.5rem; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Category Name</th>
                                <th style="text-align: left; padding: 0.75rem 0.5rem; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Code Identifier</th>
                                <th style="text-align: right; padding: 0.75rem 0.5rem; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Active Products</th>
                                <th style="text-align: right; padding: 0.75rem 0.5rem; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Actions</th>
                            </tr>
                        `;
                        
                        items.forEach(item => {
                            bodyHtml += `
                                <tr class="modal-searchable-row" style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 0.75rem 0.5rem; font-weight: 700; color: #1e293b;">
                                        ${escapeHtml(item.name)}
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem;">
                                        <code style="font-size: 0.8rem; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; color: #475569; font-weight: 600;">${escapeHtml(item.code)}</code>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; text-align: right; font-weight: 700; color: #8b5cf6;">
                                        ${item.item_count} items
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; text-align: right;">
                                        <a href="inventory-categories.php" class="btn" style="background: rgba(139, 92, 246, 0.08); color: #8b5cf6; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-decoration: none; display: inline-block; transition: all 0.2s;" onmouseover="this.style.background='rgba(139, 92, 246, 0.15)'" onmouseout="this.style.background='rgba(139, 92, 246, 0.08)'">Manage</a>
                                    </td>
                                </tr>
                            `;
                        });
                        
                        footerTotalBox.style.display = 'block';
                        document.getElementById('metricsModalFooterTotalLabel').textContent = 'Total Categories';
                        document.getElementById('metricsModalFooterTotalVal').textContent = count;
                        document.getElementById('metricsModalFooterTotalVal').style.color = '#8b5cf6';
                        document.getElementById('metricsModalFooterText').textContent = 'Active product classification schemes.';
                    } else if (metricType === 'total_items') {
                        title = 'Warehouse Product Directory';
                        subtitle = 'Total Stock Items';
                        headHtml = `
                            <tr style="border-bottom: 2px solid #e2e8f0;">
                                <th style="text-align: left; padding: 0.75rem 0.5rem; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Item Description</th>
                                <th style="text-align: left; padding: 0.75rem 0.5rem; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Category</th>
                                <th style="text-align: right; padding: 0.75rem 0.5rem; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Stock Level</th>
                                <th style="text-align: right; padding: 0.75rem 0.5rem; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Unit Price</th>
                                <th style="text-align: right; padding: 0.75rem 0.5rem; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Actions</th>
                            </tr>
                        `;
                        
                        items.forEach(item => {
                            const qty = parseFloat(item.quantity) || 0;
                            const cost = parseFloat(item.cost) || 0;
                            const catName = globalCategoryMap[item.category] || item.category || 'N/A';
                            const isLow = qty <= (parseFloat(item.low_stock_alert) || 0);
                            
                            bodyHtml += `
                                <tr class="modal-searchable-row" style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 0.75rem 0.5rem;">
                                        <div style="font-weight: 700; color: #1e293b;">${escapeHtml(item.item_name)}</div>
                                        <div style="font-size: 0.75rem; color: #64748b; font-weight: 500;">SKU: ${escapeHtml(item.sku || 'N/A')}</div>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem;">
                                        <span style="font-weight: 600; color: #475569; background: #f1f5f9; padding: 4px 10px; border-radius: 8px; font-size: 0.8rem;">${escapeHtml(catName)}</span>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; text-align: right;">
                                        <div style="font-weight: 700; color: ${isLow ? '#ef4444' : '#1e293b'};">${qty} ${escapeHtml(item.unit || '')}</div>
                                        ${isLow ? `<span style="display: inline-block; font-size: 0.65rem; background: rgba(239, 68, 68, 0.1); color: #ef4444; font-weight: 800; padding: 2px 6px; border-radius: 4px; margin-top: 2px; text-transform: uppercase;">Low Stock</span>` : ''}
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; text-align: right; font-weight: 700; color: #4f46e5;">
                                        ₹ ${cost.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; text-align: right;">
                                        <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                            <a href="add-inventory.php?id=${item.id}" class="btn-icon-p" title="Edit Item" style="color: #4f46e5; width: 30px; height: 30px; font-size: 0.95rem; border-radius: 8px;"><i class="ri-edit-line"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        });
                        
                        footerTotalBox.style.display = 'block';
                        document.getElementById('metricsModalFooterTotalLabel').textContent = 'Total Catalog Items';
                        document.getElementById('metricsModalFooterTotalVal').textContent = count;
                        document.getElementById('metricsModalFooterTotalVal').style.color = '#10b981';
                        document.getElementById('metricsModalFooterText').textContent = 'Full catalog database list.';
                    }
                    
                    document.getElementById('metricsModalTitle').textContent = title;
                    document.getElementById('metricsModalSubtitle').textContent = subtitle;
                    document.getElementById('metricsModalTableHead').innerHTML = headHtml;
                    document.getElementById('metricsModalTableBody').innerHTML = bodyHtml || '<tr><td colspan="5" style="text-align: center; padding: 2rem; color: #64748b;">No items found.</td></tr>';
                    
                    document.getElementById('metricsModalCount').textContent = `Showing ${count} of ${count} items`;
                    
                    setTimeout(() => {
                        content.style.transform = 'scale(1)';
                        content.style.opacity = '1';
                    }, 50);
                } else {
                    closeMetricsModal();
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Failed to fetch metrics breakdown.' });
                }
            })
            .catch(err => {
                closeMetricsModal();
                console.error(err);
                Swal.fire({ icon: 'error', title: 'Network Error', text: 'Failed to communicate with server.' });
            });
    }

    function closeMetricsModal() {
        const modal = document.getElementById('metricsBreakdownModal');
        const content = modal.querySelector('div:nth-child(2)');
        
        content.style.transform = 'scale(0.95)';
        content.style.opacity = '0';
        
        setTimeout(() => {
            modal.style.display = 'none';
        }, 200);
    }

    function openInvoiceModal(invoiceNo) {
        const modal = document.getElementById('invoiceModal');
        const content = modal.querySelector('div:nth-child(2)');
        
        // Reset modal layout
        document.getElementById('modalInvoiceNo').textContent = 'Loading...';
        document.getElementById('modalInvoiceDate').textContent = '';
        document.getElementById('modalSupplierName').textContent = '';
        document.getElementById('modalSupplierContact').textContent = '';
        document.getElementById('modalDocBar').style.display = 'none';
        document.getElementById('modalInvoiceItems').innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 2rem; color: #64748b;"><i class="ri-loader-4-line ri-spin" style="font-size: 2rem;"></i></td></tr>';
        document.getElementById('modalItemCount').textContent = '0';
        document.getElementById('modalGrandTotal').textContent = '₹ 0.00';
        
        modal.style.display = 'flex';
        
        fetch('inventory.php?action=get_invoice_details&invoice_no=' + encodeURIComponent(invoiceNo))
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('modalInvoiceNo').textContent = 'Invoice #' + data.header.invoice_no;
                    
                    let formattedDate = '-';
                    if (data.header.invoice_date) {
                        const d = new Date(data.header.invoice_date);
                        if (!isNaN(d.getTime())) {
                            const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
                            formattedDate = d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
                        } else {
                            formattedDate = data.header.invoice_date;
                        }
                    }
                    document.getElementById('modalInvoiceDate').textContent = 'Date: ' + formattedDate;
                    
                    document.getElementById('modalSupplierName').textContent = data.header.supplier_name || 'N/A';
                    document.getElementById('modalSupplierContact').textContent = data.header.supplier_contact || '';
                    
                    const docBar = document.getElementById('modalDocBar');
                    const docLink = document.getElementById('modalDocLink');
                    if (data.header.invoice_file) {
                        docBar.style.display = 'flex';
                        docLink.href = '../uploads/invoices/' + data.header.invoice_file;
                    } else {
                        docBar.style.display = 'none';
                    }
                    
                    const categoryMap = <?= json_encode($categoryMap) ?>;
                    const tbody = document.getElementById('modalInvoiceItems');
                    tbody.innerHTML = '';
                    
                    let grandTotal = 0;
                    let itemCount = 0;
                    
                    data.items.forEach(item => {
                        const qty = parseFloat(item.quantity) || 0;
                        const cost = parseFloat(item.cost) || 0;
                        const rowTotal = qty * cost;
                        grandTotal += rowTotal;
                        itemCount++;
                        
                        const catName = categoryMap[item.category] || item.category || 'N/A';
                        
                        const tr = document.createElement('tr');
                        tr.style.borderBottom = '1px solid #f1f5f9';
                        
                        const tdName = document.createElement('td');
                        tdName.style.padding = '0.75rem 0.5rem';
                        tdName.innerHTML = `
                            <div style="font-weight: 700; color: #1e293b;">${escapeHtml(item.item_name)}</div>
                            <div style="font-size: 0.75rem; color: #64748b; font-weight: 500;">SKU: ${escapeHtml(item.sku || 'N/A')}</div>
                        `;
                        
                        const tdCat = document.createElement('td');
                        tdCat.style.padding = '0.75rem 0.5rem';
                        tdCat.innerHTML = `<span style="font-weight: 600; color: #475569; background: #f1f5f9; padding: 4px 10px; border-radius: 8px; font-size: 0.8rem;">${escapeHtml(catName)}</span>`;
                        
                        const tdQty = document.createElement('td');
                        tdQty.style.padding = '0.75rem 0.5rem';
                        tdQty.style.textAlign = 'right';
                        tdQty.style.fontWeight = '700';
                        tdQty.style.color = '#1e293b';
                        tdQty.textContent = qty + ' ' + (item.unit || '');
                        
                        const tdCost = document.createElement('td');
                        tdCost.style.padding = '0.75rem 0.5rem';
                        tdCost.style.textAlign = 'right';
                        tdCost.style.fontWeight = '700';
                        tdCost.style.color = '#4f46e5';
                        tdCost.textContent = '₹ ' + cost.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        
                        const tdTotal = document.createElement('td');
                        tdTotal.style.padding = '0.75rem 0.5rem';
                        tdTotal.style.textAlign = 'right';
                        tdTotal.style.fontWeight = '700';
                        tdTotal.style.color = '#0f172a';
                        tdTotal.textContent = '₹ ' + rowTotal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        
                        tr.appendChild(tdName);
                        tr.appendChild(tdCat);
                        tr.appendChild(tdQty);
                        tr.appendChild(tdCost);
                        tr.appendChild(tdTotal);
                        
                        tbody.appendChild(tr);
                    });
                    
                    document.getElementById('modalItemCount').textContent = itemCount;
                    document.getElementById('modalGrandTotal').textContent = '₹ ' + grandTotal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    
                    setTimeout(() => {
                        content.style.transform = 'scale(1)';
                        content.style.opacity = '1';
                    }, 50);
                } else {
                    closeInvoiceModal();
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Failed to fetch invoice details.' });
                }
            })
            .catch(err => {
                closeInvoiceModal();
                console.error(err);
                Swal.fire({ icon: 'error', title: 'Network Error', text: 'Failed to communicate with server.' });
            });
    }

    function closeInvoiceModal() {
        const modal = document.getElementById('invoiceModal');
        const content = modal.querySelector('div:nth-child(2)');
        
        content.style.transform = 'scale(0.95)';
        content.style.opacity = '0';
        
        setTimeout(() => {
            modal.style.display = 'none';
        }, 200);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, "&amp;")
                  .replace(/</g, "&lt;")
                  .replace(/>/g, "&gt;")
                  .replace(/"/g, "&quot;")
                  .replace(/'/g, "&#039;");
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "This item will be permanently removed.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: 'var(--danger)',
            cancelButtonColor: 'var(--text-muted)',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "delete-inventory.php?id=" + id;
            }
        });
    }
</script>

<!-- Premium Invoice Details Modal -->
<div id="invoiceModal" style="display: none; position: fixed; inset: 0; z-index: 9999; align-items: center; justify-content: center; padding: 1.5rem;">
    <!-- Backdrop with blur -->
    <div onclick="closeInvoiceModal()" style="position: absolute; inset: 0; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(8px); transition: all 0.3s ease;"></div>
    
    <!-- Modal content card -->
    <div style="position: relative; background: #ffffff; width: 100%; max-width: 800px; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); overflow: hidden; transform: scale(0.95); opacity: 0; transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1); display: flex; flex-direction: column; max-height: 90vh;">
        <!-- Header -->
        <div style="padding: 1.5rem; border-bottom: 1px solid #f1f5f9; background: #f8fafc; display: flex; justify-content: space-between; align-items: flex-start; position: relative;">
            <div>
                <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #4f46e5; letter-spacing: 0.05em;">Invoice Summary</span>
                <h3 id="modalInvoiceNo" style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 4px 0 0 0;">Invoice</h3>
                <p id="modalInvoiceDate" style="font-size: 0.875rem; color: #64748b; margin: 4px 0 0 0;">Date: </p>
            </div>
            
            <div id="modalSupplierBox" style="text-align: right; margin-right: 2.5rem;">
                <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Supplier Info</div>
                <div id="modalSupplierName" style="font-weight: 700; color: #0f172a; font-size: 0.95rem; margin-top: 4px;">-</div>
                <div id="modalSupplierContact" style="font-size: 0.875rem; color: #64748b; margin-top: 2px;"></div>
            </div>
            
            <!-- Close Button -->
            <button onclick="closeInvoiceModal()" style="position: absolute; right: 1.5rem; top: 1.5rem; width: 36px; height: 36px; border-radius: 50%; background: #ffffff; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; color: #64748b; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.color='#0f172a'; this.style.borderColor='#cbd5e1'; this.style.transform='rotate(90deg)'" onmouseout="this.style.color='#64748b'; this.style.borderColor='#e2e8f0'; this.style.transform='rotate(0deg)'">
                <i class="ri-close-line" style="font-size: 1.25rem;"></i>
            </button>
        </div>
        
        <!-- Document Link Bar -->
        <div id="modalDocBar" style="display: none; padding: 0.75rem 1.5rem; background: rgba(79, 70, 229, 0.05); border-bottom: 1px solid rgba(79, 70, 229, 0.1); align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 8px; color: #4f46e5; font-size: 0.85rem; font-weight: 600;">
                <i class="ri-file-pdf-line" style="font-size: 1.1rem;"></i>
                Original invoice document is attached.
            </div>
            <a id="modalDocLink" href="#" target="_blank" style="background: #4f46e5; color: #ffffff; font-size: 0.75rem; font-weight: 700; padding: 6px 14px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s;" onmouseover="this.style.background='#4338ca'" onmouseout="this.style.background='#4f46e5'">
                <i class="ri-download-line"></i> View Document
            </a>
        </div>
        
        <!-- Body Table (Scrollable) -->
        <div style="flex: 1; overflow-y: auto; padding: 1.5rem;">
            <table class="table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #e2e8f0;">
                        <th style="text-align: left; padding: 0.75rem 0.5rem; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Item Description</th>
                        <th style="text-align: left; padding: 0.75rem 0.5rem; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Category</th>
                        <th style="text-align: right; padding: 0.75rem 0.5rem; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Quantity</th>
                        <th style="text-align: right; padding: 0.75rem 0.5rem; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Unit Price</th>
                        <th style="text-align: right; padding: 0.75rem 0.5rem; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Row Total</th>
                    </tr>
                </thead>
                <tbody id="modalInvoiceItems">
                    <!-- Dynamic Rows -->
                </tbody>
            </table>
        </div>
        
        <!-- Footer -->
        <div style="padding: 1.5rem; border-top: 1px solid #f1f5f9; background: #f8fafc; display: flex; justify-content: space-between; align-items: center;">
            <div style="color: #64748b; font-size: 0.85rem; font-weight: 500;">
                Calculated dynamically from <span id="modalItemCount" style="font-weight: 700; color: #0f172a;">0</span> registered items.
            </div>
            <div style="text-align: right;">
                <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Grand Total</div>
                <div id="modalGrandTotal" style="font-size: 1.75rem; font-weight: 800; color: #4f46e5; margin-top: 2px;">₹ 0.00</div>
            </div>
        </div>
    </div>
</div>

<!-- Premium Metrics Breakdown Modal -->
<div id="metricsBreakdownModal" style="display: none; position: fixed; inset: 0; z-index: 9999; align-items: center; justify-content: center; padding: 1.5rem;">
    <!-- Backdrop with blur -->
    <div onclick="closeMetricsModal()" style="position: absolute; inset: 0; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(8px); transition: all 0.3s ease;"></div>
    
    <!-- Modal content card -->
    <div style="position: relative; background: #ffffff; width: 100%; max-width: 800px; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); overflow: hidden; transform: scale(0.95); opacity: 0; transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1); display: flex; flex-direction: column; max-height: 90vh;">
        <!-- Header -->
        <div style="padding: 1.5rem; border-bottom: 1px solid #f1f5f9; background: #f8fafc; display: flex; justify-content: space-between; align-items: flex-start; position: relative;">
            <div>
                <span id="metricsModalSubtitle" style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #4f46e5; letter-spacing: 0.05em;">Breakdown</span>
                <h3 id="metricsModalTitle" style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 4px 0 0 0;">Metric Details</h3>
            </div>
            
            <!-- Close Button -->
            <button onclick="closeMetricsModal()" style="position: absolute; right: 1.5rem; top: 1.5rem; width: 36px; height: 36px; border-radius: 50%; background: #ffffff; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; color: #64748b; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.color='#0f172a'; this.style.borderColor='#cbd5e1'; this.style.transform='rotate(90deg)'" onmouseout="this.style.color='#64748b'; this.style.borderColor='#e2e8f0'; this.style.transform='rotate(0deg)'">
                <i class="ri-close-line" style="font-size: 1.25rem;"></i>
            </button>
        </div>
        
        <!-- Search and Filter Bar -->
        <div style="padding: 0.75rem 1.5rem; background: #ffffff; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
            <div style="position: relative; flex: 1; min-width: 240px;">
                <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #64748b;">
                    <i class="ri-search-line"></i>
                </span>
                <input type="text" id="metricsModalSearch" placeholder="Search these items..." style="width: 100%; padding: 8px 12px 8px 32px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.875rem; outline: none; transition: border-color 0.2s;" onfocus="this.style.borderColor='#4f46e5'" onblur="this.style.borderColor='#cbd5e1'">
            </div>
            <div style="font-size: 0.82rem; color: #64748b; font-weight: 600;" id="metricsModalCount">
                Showing 0 items
            </div>
        </div>
        
        <!-- Body Table (Scrollable) -->
        <div style="flex: 1; overflow-y: auto; padding: 1.5rem;">
            <table class="table" style="width: 100%; border-collapse: collapse;">
                <thead id="metricsModalTableHead">
                    <!-- Dynamic Headers -->
                </thead>
                <tbody id="metricsModalTableBody">
                    <!-- Dynamic Rows -->
                </tbody>
            </table>
        </div>
        
        <!-- Footer -->
        <div id="metricsModalFooter" style="padding: 1.5rem; border-top: 1px solid #f1f5f9; background: #f8fafc; display: flex; justify-content: space-between; align-items: center;">
            <div id="metricsModalFooterText" style="color: #64748b; font-size: 0.85rem; font-weight: 500;">
                Calculated dynamically from real-time records.
            </div>
            <div id="metricsModalFooterTotalBox" style="text-align: right; display: none;">
                <div id="metricsModalFooterTotalLabel" style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Grand Total</div>
                <div id="metricsModalFooterTotalVal" style="font-size: 1.75rem; font-weight: 800; color: #4f46e5; margin-top: 2px;">₹ 0.00</div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/datatable.php'; ?>
<script>
    $(document).ready(function() {
        initializeDataTable('inventoryTable', 'Warehouse Inventory Report');
        
        const table = $('#inventoryTable').DataTable();
        
        // Handle Category Filter
        $('#categoryFilter').on('change', function() {
            table.column(1).search(this.value).draw();
        });
        
        let showLowStockOnly = false;
        
        // Custom DataTables filter for Low Stock toggle
        $.fn.dataTable.ext.search.push(
            function(settings, data, dataIndex) {
                if (!showLowStockOnly) return true;
                const stockCell = data[2] || ""; // Column 2: Stock Level
                return stockCell.toLowerCase().includes("low stock");
            }
        );
        
        // Card clicks -> launch premium modals
        $('#totalValueCard').on('click', function() {
            openMetricsModal('total_value');
        });
        
        $('#lowStockCard').on('click', function() {
            openMetricsModal('low_stock');
        });
        
        $('#categoriesCard').on('click', function() {
            openMetricsModal('categories');
        });
        
        $('#totalItemsCard').on('click', function() {
            openMetricsModal('total_items');
        });

        // Interactive modal live search
        $('#metricsModalSearch').on('input', function() {
            const query = $(this).val().toLowerCase().trim();
            const $rows = $('.modal-searchable-row');
            let visibleCount = 0;
            
            $rows.each(function() {
                const text = $(this).text().toLowerCase();
                if (text.indexOf(query) !== -1) {
                    $(this).show();
                    visibleCount++;
                } else {
                    $(this).hide();
                }
            });
            
            $('#metricsModalCount').text(`Showing ${visibleCount} of ${$rows.length} items`);
        });
    });
</script>

<?php include 'includes/footer.php'; ?>