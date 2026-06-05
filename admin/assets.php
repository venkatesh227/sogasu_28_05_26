<?php
session_start();
include '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_asset') {
        $assetId = !empty($_POST['asset_id']) ? intval($_POST['asset_id']) : null;
        $name = trim($_POST['name'] ?? '');
        $assetCode = trim($_POST['asset_code'] ?? '');
        $categoryId = intval($_POST['category_id'] ?? 0);
$stockQuantity = 1;
        $unit = trim($_POST['unit'] ?? 'Piece');
        $conditionStatus = in_array($_POST['condition_status'] ?? '', ['Good', 'Needs Repair', 'Broken']) ? $_POST['condition_status'] : 'Good';
        $assignedEmployeeId = !empty($_POST['assigned_employee_id']) ? intval($_POST['assigned_employee_id']) : null;
        $lastMaintenance = !empty($_POST['last_maintenance']) ? $_POST['last_maintenance'] : null;
        $purchaseDate = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : null;
$amcStatus = $_POST['amc_status'] ?? 'No';
        $nextServiceDate = !empty($_POST['next_service_date']) ? $_POST['next_service_date'] : null;

        $errors = [];
        if ($name === '') {
            $errors[] = 'Asset name is required';
        }
        if ($assetCode === '') {
            $errors[] = 'Asset code is required';
        }
        if ($categoryId <= 0) {
            $errors[] = 'Asset category is required';
        }
        if ($stockQuantity < 0) {
            $errors[] = 'Quantity must be 0 or more';
        }

        if (empty($errors)) {
            if ($assetId) {
                $stmt = $pdo->prepare("UPDATE assets SET asset_code = ?, name = ?, category_id = ?, stock_quantity = ?, condition_status = ?, assigned_employee_id = ?, last_maintenance = ?, purchase_date = ?, amc_status = ?, next_service_date = ? WHERE id = ?");
                $success = $stmt->execute([
                    $assetCode,
                    $name,
                    $categoryId,
                    $stockQuantity,
                    $conditionStatus,
                    $assignedEmployeeId,
                    $lastMaintenance,
                    $purchaseDate,
                    $amcStatus,
                    $nextServiceDate,
                    $assetId
                ]);
                if ($success) {
                    $_SESSION['success'] = 'updated';
                }
            } else {
                $stmt = $pdo->prepare("INSERT INTO assets (asset_code, name, category_id, stock_quantity, condition_status, assigned_employee_id, last_maintenance, purchase_date, amc_status, next_service_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $success = $stmt->execute([
                    $assetCode,
                    $name,
                    $categoryId,
                    $stockQuantity,
                    $conditionStatus,
                    $assignedEmployeeId,
                    $lastMaintenance,
                    $purchaseDate,
                    $amcStatus,
                    $nextServiceDate
                ]);
                if ($success) {
                    $_SESSION['success'] = 'created';
                }
            }

            if (!empty($success)) {
                header('Location: assets.php');
                exit;
            }

            $formError = implode('. ', $errors);
        } else {
            $formError = implode('. ', $errors);
        }
    } elseif ($_POST['action'] === 'toggle_status') {
        $assetId = intval($_POST['id'] ?? 0);
        $newStatus = in_array($_POST['condition_status'] ?? '', ['Good', 'Broken']) ? $_POST['condition_status'] : 'Good';
        $stmt = $pdo->prepare("UPDATE assets SET condition_status = ? WHERE id = ?");
        $success = $stmt->execute([$newStatus, $assetId]);
        header('Content-Type: application/json');
        echo json_encode(['success' => (bool) $success]);
        exit;
    } elseif ($_POST['action'] === 'assign_asset') {
        $assetId = intval($_POST['asset_id'] ?? 0);
        $employeeId = !empty($_POST['employee_id']) ? intval($_POST['employee_id']) : null;
        $quantity = intval($_POST['quantity'] ?? 1);

        if ($assetId && $employeeId && $quantity > 0) {
            $stmt = $pdo->prepare("UPDATE assets SET assigned_employee_id = ? WHERE id = ?");
            $success = $stmt->execute([$employeeId, $assetId]);
            
            if ($success) {
                $_SESSION['success'] = 'assigned';
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => false]);
        exit;
    } elseif ($_POST['action'] === 'update_stock') {
        $assetId = intval($_POST['asset_id'] ?? 0);
        $newQty = intval($_POST['new_quantity'] ?? 0);

        if ($assetId && $newQty >= 0) {
            $stmt = $pdo->prepare("UPDATE assets SET stock_quantity = ? WHERE id = ?");
            $success = $stmt->execute([$newQty, $assetId]);
            
            if ($success) {
                $_SESSION['success'] = 'stock_updated';
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => false]);
        exit;
    } elseif ($_POST['action'] === 'save_maintenance') {
        $assetId = intval($_POST['asset_id'] ?? 0);
        $repairDate = !empty($_POST['repair_date']) ? $_POST['repair_date'] : date('Y-m-d');
        $nextServiceDate = !empty($_POST['next_service_date']) ? $_POST['next_service_date'] : null;
        $cost = floatval($_POST['cost'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        
        if ($assetId) {
            $stmt = $pdo->prepare("INSERT INTO asset_maintenance_logs (asset_id, repair_date, next_service_date, cost, notes) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$assetId, $repairDate, $nextServiceDate, $cost, $notes])) {
                $_SESSION['success'] = 'maintenance_logged';
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => false]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_maintenance') {
    $assetId = intval($_GET['asset_id'] ?? 0);
    $logs = [];
    $totalCost = 0;
    if ($assetId) {
        $stmt = $pdo->prepare("SELECT * FROM asset_maintenance_logs WHERE asset_id = ? ORDER BY repair_date DESC");
        $stmt->execute([$assetId]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($logs as $log) {
            $totalCost += floatval($log['cost']);
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['logs' => $logs, 'total_cost' => $totalCost]);
    exit;
}

if (isset($_GET['delete'])) {
    $deleteId = intval($_GET['delete']);
    if ($deleteId) {
        $stmt = $pdo->prepare("DELETE FROM assets WHERE id = ?");
        if ($stmt->execute([$deleteId])) {
            $_SESSION['success'] = 'deleted';
        }
    }
    header('Location: assets.php');
    exit;
}

$assetsStmt = $pdo->query("SELECT a.*, ac.name AS category_name, CONCAT(e.first_name, ' ', e.last_name) AS assigned_to FROM assets a LEFT JOIN asset_categories ac ON a.category_id = ac.id LEFT JOIN employees e ON a.assigned_employee_id = e.id ORDER BY a.id DESC");
$assets = $assetsStmt->fetchAll();
$categories = $pdo->query("SELECT id, name FROM asset_categories WHERE status = 1 ORDER BY name ASC")->fetchAll();
$employees = $pdo->query("SELECT id, first_name, last_name FROM employees WHERE is_deleted = 0 ORDER BY first_name ASC")->fetchAll();

$totalAssets = count($assets);
$assignedAssets = 0;
$goodAssets = 0;
foreach ($assets as $asset) {
    if (!empty($asset['assigned_employee_id'])) {
        $assignedAssets++;
    }
    if ($asset['condition_status'] === 'Good') {
        $goodAssets++;
    }
}
$stats = [
    'total_assets' => $totalAssets,
    'assigned' => $assignedAssets,
    'available' => $totalAssets - $assignedAssets,
    'good' => $goodAssets,
];

$pageTitle = "Assets - Sogasu";
$activePage = "assets";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="padding: 1rem;">
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Assets Management</h2>
                <p class="text-muted">Track and manage all business assets</p>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <a href="asset-reports.php" class="btn btn-secondary" style="background: white; color: #4f46e5; border: 1px solid #c7d2fe;">
                    <i class="ri-file-chart-line"></i> View Reports
                </a>
                <button class="btn btn-primary" onclick="openAssetModal()">
                    <i class="ri-add-line"></i> Add Asset
                </button>
            </div>
        </div>

        <div class="filter-bar" style="display: flex; gap: 1rem; margin-bottom: 1.5rem; background: white; padding: 1rem; border: 1px solid #e2e8f0; border-radius: 8px;">
            <div class="search-bar" style="flex: 1; position: relative;">
                <i class="ri-search-line" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                <input type="text" class="form-control" style="padding-left: 2.5rem;" placeholder="Search by Name or Code...">
            </div>
            <select class="form-select" style="width: 200px;">
                <option>Filter by Category</option>
                <option>Machines</option>
                <option>Materials</option>
            </select>
        </div>

        <div class="table-box" style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem;">
<table id="assetsTable" style="width:100%; border-collapse:collapse;">
                    <thead>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Asset Name</th>
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Category</th>
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Code</th>
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Unit</th>
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Assigned</th>
<th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Status</th>
<th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">AMC</th>
<th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Actions</th>         
           </tr>

                </thead>
                <tbody>
                    <?php if (!empty($assets)): ?>
                        <?php foreach ($assets as $asset): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 1rem; font-weight: 600;">
                                    <?= htmlspecialchars($asset['name']) ?>
                                    <?php 
                                        if(!empty($asset['next_service_date'])) {
                                            $days_left = (strtotime($asset['next_service_date']) - time()) / (60 * 60 * 24);
                                            if($days_left <= 0) {
                                                echo '<span title="Service Overdue" style="color: #dc2626; margin-left: 5px;"><i class="ri-error-warning-fill"></i></span>';
                                            } elseif($days_left <= 30) {
                                                echo '<span title="Service Due Soon" style="color: #f59e0b; margin-left: 5px;"><i class="ri-alert-fill"></i></span>';
                                            }
                                        }
                                    ?>
                                </td>
                                <td style="padding: 1rem;"><?= htmlspecialchars($asset['category_name'] ?? 'Uncategorized') ?></td>
                                <td style="padding: 1rem; font-family: monospace;"><?= htmlspecialchars($asset['asset_code']) ?></td>
                                <td style="padding: 1rem;">Piece</td>
                                <td style="padding: 1rem;"><?= htmlspecialchars($asset['assigned_to'] ?? 'Unassigned') ?></td>
                             <td style="padding: 1rem;">

    <span style="
        padding:6px 12px;
        border-radius:20px;
        font-size:12px;
        font-weight:600;
        background:
        <?= htmlspecialchars($asset['condition_status']) === 'Good'
            ? '#dcfce7'
            : '#fee2e2' ?>;

        color:
        <?= htmlspecialchars($asset['condition_status']) === 'Good'
            ? '#16a34a'
            : '#dc2626' ?>;
    ">

        <?= htmlspecialchars($asset['condition_status']) ?>

    </span>

<td style="padding: 1rem;">
    <?= htmlspecialchars($asset['amc_status'] ?? 'No') ?>
</td>
                                <td style="padding: 1rem;">
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button class="btn-icon" title="Edit" type="button" data-action="edit" data-id="<?= $asset['id'] ?>"><i class="ri-edit-line"></i></button>
                                        <button class="btn-icon" title="Manage Stock" type="button" data-action="stock" data-id="<?= $asset['id'] ?>" data-name="<?= htmlspecialchars($asset['name']) ?>" data-code="<?= htmlspecialchars($asset['asset_code']) ?>" data-qty="<?= intval($asset['stock_quantity']) ?>"><i class="ri-stack-line"></i></button>
                                        <button class="btn-icon" title="Assign" type="button" data-action="assign" data-id="<?= $asset['id'] ?>" data-name="<?= htmlspecialchars($asset['name']) ?>" data-code="<?= htmlspecialchars($asset['asset_code']) ?>" data-qty="<?= intval($asset['stock_quantity']) ?>"><i class="ri-user-add-line"></i></button>
<?php if(($asset['amc_status'] ?? 'No') == 'Yes'): ?>
<button class="btn-icon"
        title="AMC Maintenance Logs"
        type="button"
        data-action="maintenance"
        data-id="<?= $asset['id'] ?>"
        data-name="<?= htmlspecialchars($asset['name']) ?>"
        data-code="<?= htmlspecialchars($asset['asset_code']) ?>">
    <i class="ri-tools-line"></i>
</button>
<?php endif; ?>                                        <button class="btn-icon" title="Delete" type="button" style="color: #ef4444;" data-action="delete" data-id="<?= $asset['id'] ?>"><i class="ri-delete-bin-line"></i></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Add Asset Modal -->
<div id="assetModal" class="modal">
    <div class="modal-card" style="width: 520px;">
        <div class="modal-header" style="background: var(--primary); color: white; padding: 1rem; display: flex; justify-content: space-between; align-items: center;">
            <h3 id="assetModalTitle" style="margin: 0;">Add New Asset</h3>
            <i class="ri-close-line" style="cursor: pointer; font-size: 1.5rem;" onclick="closeModal('assetModal')"></i>
        </div>
        <div class="modal-body" style="padding: 1.5rem;">
            <?php if (!empty($formError)): ?>
                <div style="background:#fee2e2;color:#991b1b;padding:0.75rem;border-radius:8px;margin-bottom:1rem;">
                    <?= htmlspecialchars($formError) ?>
                </div>
            <?php endif; ?>
            <form id="assetForm" method="post" style="display: grid; gap: 1rem;">
                <input type="hidden" name="action" value="save_asset">
                <input type="hidden" name="asset_id" id="asset_id" value="">

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Asset Name</label>
                    <input type="text" name="name" id="assetName" class="form-control" placeholder="e.g. Steam Iron">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category_id" id="assetCategory" class="form-select">
                            <option value="">Select category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Asset Code</label>
                        <input type="text" name="asset_code" id="assetCode" class="form-control" placeholder="Auto/Manual">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">

                    <div class="form-group">
                        <label class="form-label">Condition</label>
                        <select name="condition_status" id="assetCondition" class="form-select">
                            <option value="Good">Good</option>
                            <option value="Needs Repair">Needs Repair</option>
                            <option value="Broken">Broken</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Purchase Date</label>
                        <input type="date" name="purchase_date" id="assetPurchaseDate" class="form-control">
                    </div>
                    <div class="form-group">
<div class="form-group">
    <label class="form-label">AMC Available?</label>

    <div style="display:flex;gap:10px;margin-top:8px;">

        <label>
            <input type="radio" name="amc_status" value="Yes">
            Yes
        </label>

        <label>
            <input type="radio" name="amc_status" value="No" checked>
            No
        </label>

    </div>
</div>                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Assigned To</label>
                        <select name="assigned_employee_id" id="assetAssigned" class="form-select">
                            <option value="">Unassigned</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?= $employee['id'] ?>"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Asset</button>
            </form>
        </div>
    </div>
</div>

<!-- Manage Stock Modal -->
<div id="stockModal" class="modal">
    <div class="modal-card" style="width: 450px;">
        <div class="modal-header" style="background: #4f46e5; color: white; padding: 1rem; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0;">Manage Stock</h3>
            <i class="ri-close-line" style="cursor: pointer; font-size: 1.5rem;" onclick="closeModal('stockModal')"></i>
        </div>
        <div class="modal-body" style="padding: 1.5rem;">
            <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #e2e8f0;">
                <h4 id="stockAssetName" style="margin: 0; font-size: 1rem;">Juki Machine</h4>
                <p id="stockAssetCode" style="margin: 0.25rem 0 0; font-size: 0.8rem; color: #64748b; font-family: monospace;">MC-001</p>
                <div style="margin-top: 0.75rem; font-size: 0.9rem; font-weight: 600;">Current Quantity: <span id="currentQty" style="color: #4f46e5;">5</span></div>
            </div>

            <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem;">
                <button class="btn" style="flex: 1; background: #dcfce7; color: #16a34a; border: none;" onclick="showStockForm('IN')"><i class="ri-add-line"></i> Add Stock (IN)</button>
                <button class="btn" style="flex: 1; background: #fee2e2; color: #dc2626; border: none;" onclick="showStockForm('OUT')"><i class="ri-subtract-line"></i> Remove Stock (OUT)</button>
            </div>

            <div id="stockInForm" style="display: none; background: #f0fdf4; padding: 1rem; border-radius: 8px;">
                <h4 style="margin: 0 0 1rem; font-size: 0.9rem; color: #16a34a;">Stock Entry (IN)</h4>
                <form id="stockForm" style="display: grid; gap: 0.75rem;">
                    <input type="hidden" id="stockAssetId" value="">
                    <div class="form-group" style="margin-bottom: 0.75rem;">
                        <label class="form-label">New Quantity</label>
                        <input type="number" id="newQtyIn" class="form-control" min="0" required>
                    </div>
                    <button type="button" class="btn btn-primary" style="width: 100%; background: #16a34a; border: none;" onclick="saveStock()">Save Stock</button>
                </form>
            </div>

            <div id="stockOutForm" style="display: none; background: #fef2f2; padding: 1rem; border-radius: 8px;">
                <h4 style="margin: 0 0 1rem; font-size: 0.9rem; color: #dc2626;">Stock Withdrawal (OUT)</h4>
                <form id="stockOutForm2" style="display: grid; gap: 0.75rem;">
                    <input type="hidden" id="stockAssetIdOut" value="">
                    <div class="form-group" style="margin-bottom: 0.75rem;">
                        <label class="form-label">New Quantity</label>
                        <input type="number" id="newQtyOut" class="form-control" min="0" required>
                    </div>
                    <button type="button" class="btn btn-primary" style="width: 100%; background: #dc2626; border: none;" onclick="saveStockOut()">Save Stock</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Assign Asset Modal -->
<div id="assignModal" class="modal">
    <div class="modal-card" style="width: 400px;">
        <div class="modal-header" style="background: #1e293b; color: white; padding: 1rem; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0;">Assign Asset</h3>
            <i class="ri-close-line" style="cursor: pointer; font-size: 1.5rem;" onclick="closeModal('assignModal')"></i>
        </div>
        <div class="modal-body" style="padding: 1.5rem;">
            <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #e2e8f0;">
                <h4 id="assignAssetName" style="margin: 0; font-size: 1rem;">Juki Machine</h4>
                <p id="assignAssetCode" style="margin: 0.25rem 0 0; font-size: 0.8rem; color: #64748b; font-family: monospace;">MC-001</p>
                <div style="margin-top: 0.75rem; font-size: 0.9rem;">Available Quantity: <span id="assignAvailableQty" style="font-weight: 600;">5</span></div>
            </div>
            
            <form id="assignForm" style="display: grid; gap: 1rem;">
                <input type="hidden" id="assignAssetId" value="">
                <div class="form-group">
                    <label class="form-label">Employee</label>
                    <select id="assignEmployeeId" class="form-select" required>
                        <option value="">Select Employee</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Quantity</label>
                    <input type="number" id="assignQuantity" class="form-control" value="1" min="1" required>
                </div>
                <button type="button" class="btn btn-primary" style="width: 100%; background: #1e293b;" onclick="saveAssignment()">Assign Asset</button>
            </form>
        </div>
    </div>
</div>

<!-- Maintenance Modal -->
<div id="maintenanceModal" class="modal">
    <div class="modal-card" style="width: 600px; max-height: 90vh; display: flex; flex-direction: column;">
        <div class="modal-header" style="background: #f59e0b; color: white; padding: 1rem; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0;"><i class="ri-tools-fill"></i>AMC  Maintenance Logs</h3>
            <i class="ri-close-line" style="cursor: pointer; font-size: 1.5rem;" onclick="closeModal('maintenanceModal')"></i>
        </div>
        <div class="modal-body" style="padding: 1.5rem; overflow-y: auto;">
            <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h4 id="maintAssetName" style="margin: 0; font-size: 1.1rem;">Machine</h4>
                    <p id="maintAssetCode" style="margin: 0.25rem 0 0; font-size: 0.8rem; color: #64748b; font-family: monospace;">MC-001</p>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 0.8rem; color: #64748b;">Total Maintenance Cost</div>
                    <div id="maintTotalCost" style="font-size: 1.25rem; font-weight: 700; color: #dc2626;">₹0.00</div>
                </div>
            </div>

            <form id="maintenanceForm" style="display: grid; gap: 1rem; margin-bottom: 2rem; background: #fffbeb; padding: 1rem; border: 1px dashed #fcd34d; border-radius: 8px;">
                <input type="hidden" id="maintAssetId" value="">
                <h5 style="margin: 0; color: #b45309;"> New Service</h5>
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">

    <div class="form-group">
        <label class="form-label">Last service date</label>
        <input type="date" id="maintDate" class="form-control" required>
    </div>

    <div class="form-group">
        <label class="form-label">Next Service Due</label>
        <input type="date" id="maintNextService" class="form-control">
    </div>

</div>

<div style="margin-top:1rem;">

    <div class="form-group">
        <label class="form-label">Cost (₹)</label>
        <input type="number"
               step="0.01"
               id="maintCost"
               class="form-control"
               value="0.00"
               min="0">
    </div>

</div>
                <div class="form-group">
                    <label class="form-label">Notes/Details</label>
                    <textarea id="maintNotes" class="form-control" rows="2" placeholder="e.g. Replaced motor belt, oil change..."></textarea>
                </div>
                <button type="button" class="btn btn-primary" style="background: #f59e0b; border: none;" onclick="saveMaintenance()">Save Log</button>
            </form>

            <h5 style="margin: 0 0 1rem;">Service History</h5>
            <div style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                    <thead style="background: #f1f5f9;">
                        <tr>
                            <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid #e2e8f0;">Last Service Date</th>
                            <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid #e2e8f0;">Next Service Date</th>
                            <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid #e2e8f0;">Details</th>
                            <th style="padding: 0.75rem; text-align: right; border-bottom: 1px solid #e2e8f0;">Cost</th>
                        </tr>
                    </thead>
                    <tbody id="maintenanceHistoryBody">
                        <tr><td colspan="3" style="text-align:center; padding: 1rem;">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    .badge { padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
    .badge-success { background: #dcfce7; color: #16a34a; }
    .badge-danger { background: #fee2e2; color: #dc2626; }
    .btn-icon { background: #f8fafc; border: 1px solid #e2e8f0; padding: 6px; border-radius: 6px; cursor: pointer; color: #64748b; }
    .btn-icon:hover { background: #eef2ff; color: #4f46e5; border-color: #c7d2fe; }
    .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; justify-content: center; align-items: center; }
    .modal-card { background: white; border-radius: 12px; overflow: hidden; }
</style>

<script>
    const assetsData = <?= json_encode($assets, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

    // Event delegation for action buttons
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;

        const action = btn.dataset.action;
        const id = parseInt(btn.dataset.id);

        if (action === 'edit') {
            editAsset(id);
        } else if (action === 'stock') {
            manageStock(id, btn.dataset.name, btn.dataset.code, btn.dataset.qty);
        } else if (action === 'assign') {
            assignAsset(id, btn.dataset.name, btn.dataset.code, btn.dataset.qty);
        } else if (action === 'maintenance') {
            manageMaintenance(id, btn.dataset.name, btn.dataset.code);
        } else if (action === 'delete') {
            deleteAsset(id);
        }
    });

    function editAsset(assetId) {
        const modal = document.getElementById('assetModal');
        const form = document.getElementById('assetForm');
        form.reset();
        document.getElementById('asset_id').value = '';
        document.getElementById('assetModalTitle').innerText = 'Edit Asset';

        const asset = assetsData.find(a => a.id == assetId);
        if (asset) {
            document.getElementById('asset_id').value = asset.id;
            document.getElementById('assetName').value = asset.name || '';
            document.getElementById('assetCode').value = asset.asset_code || '';
            document.getElementById('assetCategory').value = asset.category_id || '';
            document.getElementById('assetCondition').value = asset.condition_status || 'Good';
            document.getElementById('assetAssigned').value = asset.assigned_employee_id || '';
            document.getElementById('assetPurchaseDate').value = asset.purchase_date || '';
            const amcRadio = document.querySelector(
    `input[name="amc_status"][value="${asset.amc_status || 'No'}"]`
);

if (amcRadio) {
    amcRadio.checked = true;
}
        }

        modal.style.display = 'flex';
    }

    function deleteAsset(id) {
        Swal.fire({
            title: 'Delete Asset?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'assets.php?delete=' + id;
            }
        });
    }

    function manageStock(id, name, code, qty) {
        document.getElementById('stockAssetId').value = id;
        document.getElementById('stockAssetIdOut').value = id;
        document.getElementById('stockAssetName').innerText = name;
        document.getElementById('stockAssetCode').innerText = code;
        document.getElementById('currentQty').innerText = qty;
        document.getElementById('stockModal').style.display = 'flex';
        document.getElementById('stockInForm').style.display = 'none';
        document.getElementById('stockOutForm').style.display = 'none';
        document.getElementById('newQtyIn').value = '';
        document.getElementById('newQtyOut').value = '';
    }

    function assignAsset(id, name, code, qty) {
        document.getElementById('assignAssetId').value = id;
        document.getElementById('assignAssetName').innerText = name;
        document.getElementById('assignAssetCode').innerText = code;
        document.getElementById('assignAvailableQty').innerText = qty;
        document.getElementById('assignEmployeeId').value = '';
        document.getElementById('assignQuantity').value = '1';
        document.getElementById('assignModal').style.display = 'flex';
    }

    function showStockForm(type) {
        if (type === 'IN') {
            document.getElementById('stockInForm').style.display = 'block';
            document.getElementById('stockOutForm').style.display = 'none';
        } else {
            document.getElementById('stockOutForm').style.display = 'block';
            document.getElementById('stockInForm').style.display = 'none';
        }
    }

    function calcTotalCost() {
        const qty = Number(document.getElementById('qtyIn').value) || 0;
        const unit = Number(document.getElementById('unitCost').value) || 0;
        document.getElementById('totalCost').value = qty * unit;
    }

    function closeModal(id) { 
        document.getElementById(id).style.display = 'none'; 
    }

    function saveStock() {
        const assetId = document.getElementById('stockAssetId').value;
        const newQty = document.getElementById('newQtyIn').value;

        if (!assetId || !newQty || newQty < 0) {
            Swal.fire('Error', 'Please enter a valid quantity', 'error');
            return;
        }

        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=update_stock&asset_id=' + assetId + '&new_quantity=' + newQty
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire('Success', 'Stock updated successfully', 'success').then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('Error', 'Failed to update stock', 'error');
            }
        });
    }

    function saveStockOut() {
        const assetId = document.getElementById('stockAssetIdOut').value;
        const newQty = document.getElementById('newQtyOut').value;

        if (!assetId || !newQty || newQty < 0) {
            Swal.fire('Error', 'Please enter a valid quantity', 'error');
            return;
        }

        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=update_stock&asset_id=' + assetId + '&new_quantity=' + newQty
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire('Success', 'Stock updated successfully', 'success').then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('Error', 'Failed to update stock', 'error');
            }
        });
    }

    function saveAssignment() {
        const assetId = document.getElementById('assignAssetId').value;
        const employeeId = document.getElementById('assignEmployeeId').value;
        const quantity = document.getElementById('assignQuantity').value;

        if (!assetId || !employeeId || !quantity || quantity < 1) {
            Swal.fire('Error', 'Please complete all fields', 'error');
            return;
        }

        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=assign_asset&asset_id=' + assetId + '&employee_id=' + employeeId + '&quantity=' + quantity
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire('Success', 'Asset assigned successfully', 'success').then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('Error', 'Failed to assign asset', 'error');
            }
        });
    }

function openAssetModal() {
    const modal = document.getElementById('assetModal');
    const form = document.getElementById('assetForm');

    form.reset();

    document.querySelector(
        'input[name="amc_status"][value="No"]'
    ).checked = true;

    document.getElementById('asset_id').value = '';
    document.getElementById('assetModalTitle').innerText = 'Add New Asset';

    modal.style.display = 'flex';
}
    function manageMaintenance(id, name, code) {
        document.getElementById('maintAssetId').value = id;
        document.getElementById('maintAssetName').innerText = name;
        document.getElementById('maintAssetCode').innerText = code;
        document.getElementById('maintenanceForm').reset();
        document.getElementById('maintDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('maintenanceModal').style.display = 'flex';
        
        loadMaintenanceHistory(id);
    }

    function loadMaintenanceHistory(id) {
        const tbody = document.getElementById('maintenanceHistoryBody');
        tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 1rem;">Loading...</td></tr>';
        
        fetch(`assets.php?action=get_maintenance&asset_id=${id}`)
            .then(res => res.json())
            .then(data => {
                document.getElementById('maintTotalCost').innerText = '₹' + data.total_cost.toFixed(2);
                if(data.logs.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 1rem; color: #64748b;">No maintenance logs found.</td></tr>';
                } else {
                    let html = '';
                    data.logs.forEach(log => {
html += `
<tr style="border-bottom: 1px solid #f1f5f9;">
    <td style="padding: 0.75rem;">${log.repair_date || '-'}</td>
    <td style="padding: 0.75rem;">${log.next_service_date || '-'}</td>
    <td style="padding: 0.75rem;">${log.notes || '-'}</td>
    <td style="padding: 0.75rem; text-align: right; font-weight: 600;">
        ₹${parseFloat(log.cost).toFixed(2)}
    </td>
</tr>
`;
                    });
                    tbody.innerHTML = html;
                }
            });
    }

    function saveMaintenance() {
        const assetId = document.getElementById('maintAssetId').value;
        const date = document.getElementById('maintDate').value;
        const cost = document.getElementById('maintCost').value;
        const notes = document.getElementById('maintNotes').value;
        const nextService = document.getElementById('maintNextService').value;

        if (!assetId || !date) {
            Swal.fire('Error', 'Please enter a valid date', 'error');
            return;
        }

        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
body: `action=save_maintenance&asset_id=${assetId}&repair_date=${date}&next_service_date=${nextService}&cost=${cost}&notes=${encodeURIComponent(notes)}`        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('maintenanceForm').reset();
                document.getElementById('maintDate').value = new Date().toISOString().split('T')[0];
                loadMaintenanceHistory(assetId);
                // We don't necessarily need to reload the page, but let's show success
                Swal.fire({
                    icon: 'success',
                    title: 'Logged',
                    text: 'Maintenance log saved successfully',
                    timer: 1500,
                    showConfirmButton: false
                });
            } else {
                Swal.fire('Error', 'Failed to save maintenance log', 'error');
            }
        });
    }

    function toggleStatus(el, id) {
        const status = el.checked ? 'Good' : 'Broken';

        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'action=toggle_status&id=' + id + '&condition_status=' + encodeURIComponent(status)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: status === 'Good' ? 'Marked Good' : 'Marked Broken',
                    text: 'Asset status updated successfully',
                    timer: 1200,
                    showConfirmButton: false
                });
            } else {
                el.checked = !el.checked;
            }
        })
        .catch(() => { el.checked = !el.checked; });
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php include __DIR__ . '/includes/datatable.php'; ?>

<?php if (!empty($_SESSION['success'])): ?>
<?php
    $message = 'Asset saved successfully';
    if ($_SESSION['success'] === 'updated') {
        $message = 'Asset updated successfully';
    } elseif ($_SESSION['success'] === 'deleted') {
        $message = 'Asset deleted successfully';
    }
?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Success',
    text: '<?= $message ?>'
});
</script>
<?php unset($_SESSION['success']); ?>
<?php endif; ?>

<script>
initializeDataTable(
    'assetsTable',
    'Assets Management'
);
</script>
<?php include 'includes/footer.php'; ?>
