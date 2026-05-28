<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$po_id = intval($_GET['id'] ?? 0);

if ($po_id <= 0) {
    $_SESSION['error'] = "Invalid Purchase Order selection.";
    header("Location: purchase-orders.php");
    exit;
}

// Fetch PO and supplier details
$po_stmt = $pdo->prepare("
    SELECT po.*, s.supplier_name, s.phone_no AS supplier_contact 
    FROM purchase_orders po
    LEFT JOIN suppliers s ON s.id = po.supplier_id
    WHERE po.id = ? AND po.is_deleted = 0
");
$po_stmt->execute([$po_id]);
$po = $po_stmt->fetch();

if (!$po) {
    $_SESSION['error'] = "Purchase Order not found.";
    header("Location: purchase-orders.php");
    exit;
}

if ($po['status'] !== 'Pending') {
    $_SESSION['error'] = "Purchase Order " . htmlspecialchars($po['po_number']) . " has already been processed (Status: " . $po['status'] . ").";
    header("Location: purchase-orders.php");
    exit;
}

// Fetch PO items
$items_stmt = $pdo->prepare("SELECT * FROM purchase_order_items WHERE purchase_order_id = ? ORDER BY id ASC");
$items_stmt->execute([$po_id]);
$items = $items_stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_no = trim($_POST['invoice_no'] ?? '');
    $invoice_date = trim($_POST['invoice_date'] ?? '');
    
    // Received quantities and costs
    $received_quantities = $_POST['received_qty'] ?? [];
    $item_ids = $_POST['item_id'] ?? [];
    $item_costs = $_POST['item_cost'] ?? [];
    
    $errors = [];
    
    // Validations
    if ($invoice_no === '') {
        $errors[] = "Supplier Invoice Number is required.";
    }
    if ($invoice_date === '') {
        $errors[] = "Invoice Date is required.";
    }
    
    // File upload
    $invoice_file_name = null;
    if (!empty($_FILES['invoice_file']['name'])) {
        $file = $_FILES['invoice_file'];
        
        if ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = "Invoice document copy must be less than 5MB.";
        }
        
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
        if (!in_array($file['type'], $allowed_types)) {
            $errors[] = "Invoice document copy must be a PDF, JPG, JPEG, or PNG.";
        }
        
        if (empty($errors)) {
            $invoice_file_name = time() . "_" . basename($file['name']);
            $target_dir = "../uploads/invoices/";
            
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            if (!move_uploaded_file($file['tmp_name'], $target_dir . $invoice_file_name)) {
                $errors[] = "Failed to save the uploaded invoice document.";
            }
        }
    }
    
    // Items validations
    $items_count = count($item_ids);
    for ($i = 0; $i < $items_count; $i++) {
        $rec_qty = $received_quantities[$i] ?? '';
        $cost = $item_costs[$i] ?? '';
        $row_num = $i + 1;
        
        if ($rec_qty === '' || !is_numeric($rec_qty) || $rec_qty < 0) {
            $errors[] = "Row #{$row_num}: Received quantity must be a non-negative number.";
        }
        if ($cost === '' || !is_numeric($cost) || $cost < 0) {
            $errors[] = "Row #{$row_num}: Cost must be a positive price.";
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // 1. Update purchase_orders
            $update_po = $pdo->prepare("
                UPDATE purchase_orders 
                SET status = 'Received', invoice_no = ?, invoice_date = ?, invoice_file = ? 
                WHERE id = ?
            ");
            $update_po->execute([
                $invoice_no,
                $invoice_date,
                $invoice_file_name ?: $po['invoice_file'],
                $po_id
            ]);
            
            // 2. Loop PO items to update received quantities and inventory
            $update_po_item = $pdo->prepare("UPDATE purchase_order_items SET received_quantity = ?, cost = ? WHERE id = ?");
            
            // Prepared statements for inventory search & modification
            $find_inv_sku = $pdo->prepare("SELECT id, quantity FROM inventory WHERE sku = ? AND is_deleted = 0 LIMIT 1");
            $find_inv_name = $pdo->prepare("SELECT id, quantity FROM inventory WHERE item_name = ? AND category = ? AND is_deleted = 0 LIMIT 1");
            
            $increment_stock = $pdo->prepare("
                UPDATE inventory 
                SET quantity = quantity + ?, cost = ?, supplier_name = ?, supplier_contact = ?, invoice_no = ?, invoice_date = ?, invoice_file = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            
            $insert_stock = $pdo->prepare("
                INSERT INTO inventory 
                (item_name, sku, category, unit, quantity, cost, supplier_name, supplier_contact, invoice_no, invoice_date, invoice_file, status, is_deleted, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, NOW(), NOW())
            ");
            
            for ($i = 0; $i < $items_count; $i++) {
                $item_id = intval($item_ids[$i]);
                $rec_qty = floatval($received_quantities[$i]);
                $cost = floatval($item_costs[$i]);
                
                // Fetch direct PO item record to get name, sku, category, unit
                $poi_stmt = $pdo->prepare("SELECT * FROM purchase_order_items WHERE id = ?");
                $poi_stmt->execute([$item_id]);
                $poi = $poi_stmt->fetch();
                
                if (!$poi) continue;
                
                // Update PO item received quantity
                $update_po_item->execute([$rec_qty, $cost, $item_id]);
                
                // Skip updating inventory stock if nothing was received for this item
                if ($rec_qty <= 0) continue;
                
                $inv_id = null;
                
                // Try finding matching inventory item
                if (!empty($poi['sku'])) {
                    $find_inv_sku->execute([$poi['sku']]);
                    $inv_item = $find_inv_sku->fetch();
                    if ($inv_item) {
                        $inv_id = $inv_item['id'];
                    }
                }
                
                if (!$inv_id) {
                    $find_inv_name->execute([$poi['item_name'], $poi['category']]);
                    $inv_item = $find_inv_name->fetch();
                    if ($inv_item) {
                        $inv_id = $inv_item['id'];
                    }
                }
                
                if ($inv_id) {
                    // Update existing inventory item
                    $increment_stock->execute([
                        $rec_qty,
                        $cost,
                        $po['supplier_name'],
                        $po['supplier_contact'],
                        $invoice_no,
                        $invoice_date,
                        $invoice_file_name,
                        $inv_id
                    ]);
                } else {
                    // Insert new inventory item
                    $sku = $poi['sku'];
                    if (empty($sku)) {
                        // Generate SKU if empty
                        $cat_prefix = strtoupper(substr($poi['category'], 0, 3));
                        if ($poi['category'] === 'access') $cat_prefix = 'ACC';
                        if (empty($cat_prefix)) $cat_prefix = 'INV';
                        
                        $clean_name = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $poi['item_name']));
                        $name_prefix = substr($clean_name, 0, 3);
                        if (strlen($name_prefix) < 3) {
                            $name_prefix = str_pad($name_prefix, 3, 'X');
                        }
                        
                        $suffix = rand(1000, 9999);
                        $sku = $cat_prefix . '-' . $name_prefix . '-' . $suffix;
                        
                        $sku_chk = $pdo->prepare("SELECT COUNT(*) FROM inventory WHERE sku = ? AND is_deleted = 0");
                        while (true) {
                            $sku_chk->execute([$sku]);
                            if ($sku_chk->fetchColumn() == 0) {
                                break;
                            }
                            $suffix = rand(1000, 9999);
                            $sku = $cat_prefix . '-' . $name_prefix . '-' . $suffix;
                        }
                    }
                    
                    $insert_stock->execute([
                        $poi['item_name'],
                        $sku,
                        $poi['category'],
                        $poi['unit'],
                        $rec_qty,
                        $cost,
                        $po['supplier_name'],
                        $po['supplier_contact'],
                        $invoice_no,
                        $invoice_date,
                        $invoice_file_name
                    ]);
                }
            }
            
            $pdo->commit();
            
            $_SESSION['success'] = "Stock from Purchase Order " . htmlspecialchars($po['po_number']) . " has been successfully received!";
            header("Location: purchase-orders.php");
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Failed to process stock receipt: " . $e->getMessage();
        }
    }
}

$pageTitle = "Receive Purchase Order - Sogasu";
$activePage = "purchase-orders";

include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div>
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Receive Stock: <?= htmlspecialchars($po['po_number']) ?></h2>
                <p class="text-muted" style="margin: 0.25rem 0 0 0;">Record actual received quantities and link the supplier invoice</p>
            </div>
            <button class="btn" onclick="history.back()"
                style="background: white; border: 1px solid #e2e8f0; color: #64748b; font-weight: 600;">
                <i class="ri-arrow-left-line"></i> Back
            </button>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid #fca5a5; border-radius: 8px; padding: 1rem; color: #b91c1c; font-weight: 500; font-size: 0.9rem; margin-bottom: 1.5rem; line-height: 1.5;">
            <div style="font-weight: 700; margin-bottom: 0.5rem;"><i class="ri-error-warning-line"></i> Please fix the following errors:</div>
            <ul style="margin: 0; padding-left: 1.25rem;">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="receiveForm">
        
        <!-- Invoice & Supplier Info Card -->
        <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-top: 0; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="ri-file-text-line" style="color: #4f46e5;"></i> Supplier Invoice Details
            </h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem;">
                <div class="form-group">
                    <label class="form-label">Invoice Number <span style="color: red;">*</span></label>
                    <input type="text" name="invoice_no" class="form-control" required placeholder="e.g. INV-2026-9041" value="<?= isset($_POST['invoice_no']) ? htmlspecialchars($_POST['invoice_no']) : '' ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Invoice Date <span style="color: red;">*</span></label>
                    <input type="date" name="invoice_date" class="form-control" required value="<?= isset($_POST['invoice_date']) ? htmlspecialchars($_POST['invoice_date']) : date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Supplier Name</label>
                    <input type="text" class="form-control" readonly style="background: #f8fafc; font-weight: 700; color: #334155;" value="<?= htmlspecialchars($po['supplier_name']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Invoice Document Upload</label>
                    <div style="position: relative; display: flex; align-items: center; gap: 0.5rem;">
                        <input type="file" name="invoice_file" id="invoiceFile" style="display: none;" onchange="showFileName(this)">
                        <button type="button" class="btn" onclick="document.getElementById('invoiceFile').click()"
                            style="background: #f8fafc; border: 1px solid #cbd5e1; color: #475569; width: 100%; font-weight: 600; justify-content: center;">
                            <i class="ri-upload-2-line" style="color: #4f46e5;"></i> Upload PDF / Image
                        </button>
                    </div>
                    <div id="filePreview" style="font-size: 0.8rem; color: #64748b; margin-top: 0.4rem; font-weight: 500;">No file chosen</div>
                </div>
            </div>
        </div>

        <!-- Ordered Items Receiving List Card -->
        <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-top: 0; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="ri-archive-line" style="color: #4f46e5;"></i> Verify Delivered Materials
            </h3>

            <div style="overflow-x: auto; margin: 0 -1.5rem; padding: 0 1.5rem;">
                <table class="table" style="min-width: 900px; margin-bottom: 0;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e2e8f0;">
                            <th style="width: 30%;">Item Details</th>
                            <th style="width: 15%;">Category</th>
                            <th style="width: 15%; text-align: right;">Ordered Qty</th>
                            <th style="width: 18%; text-align: right;">Received Qty <span style="color: red;">*</span></th>
                            <th style="width: 18%; text-align: right;">Unit Price (₹) <span style="color: red;">*</span></th>
                            <th style="width: 4%;">Unit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $index => $item): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td>
                                    <div style="font-weight: 700; color: #1e293b;"><?= htmlspecialchars($item['item_name']) ?></div>
                                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 500;">SKU: <?= htmlspecialchars($item['sku'] ?: 'Auto-generated') ?></div>
                                    <input type="hidden" name="item_id[]" value="<?= $item['id'] ?>">
                                </td>
                                <td>
                                    <span style="font-weight: 600; color: #475569; background: #f1f5f9; padding: 4px 10px; border-radius: 8px; font-size: 0.8rem;">
                                        <?= htmlspecialchars(ucfirst($item['category'])) ?>
                                    </span>
                                </td>
                                <td style="text-align: right; font-weight: 700; color: #334155; padding-right: 1.5rem;">
                                    <?= htmlspecialchars($item['quantity']) ?>
                                </td>
                                <td>
                                    <input type="number" name="received_qty[]" class="form-control" step="0.01" min="0" required style="text-align: right;" value="<?= isset($_POST['received_qty'][$index]) ? htmlspecialchars($_POST['received_qty'][$index]) : htmlspecialchars($item['quantity']) ?>">
                                </td>
                                <td>
                                    <input type="number" name="item_cost[]" class="form-control" step="0.01" min="0" required style="text-align: right;" value="<?= isset($_POST['item_cost'][$index]) ? htmlspecialchars($_POST['item_cost'][$index]) : htmlspecialchars($item['cost']) ?>">
                                </td>
                                <td style="font-weight: 600; color: #64748b; text-align: center; vertical-align: middle;">
                                    <?= htmlspecialchars($item['unit']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Form Submission Actions -->
        <div style="background: white; border: 1px solid #e2e8f0; padding: 1.25rem 1.5rem; border-radius: 8px; display: flex; justify-content: flex-end; gap: 1rem; align-items: center;">
            <button type="button" class="btn" onclick="history.back()"
                style="background: #f8fafc; border: 1px solid #cbd5e1; color: #64748b; font-weight: 600; padding: 10px 24px;">
                Cancel
            </button>
            <button type="submit" class="btn btn-primary"
                style="background: #10b981; border: none; font-weight: 700; padding: 10px 32px; border-radius: 8px; color: white;">
                <i class="ri-checkbox-circle-line"></i> Verify & Receive Stock
            </button>
        </div>

    </form>
</main>

<style>
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .form-label {
        font-size: 0.875rem;
        font-weight: 600;
        color: #475569;
    }

    .form-control {
        padding: 0.65rem 0.75rem;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 0.92rem;
        width: 100%;
        outline: none;
        transition: border-color 0.2s;
        font-family: inherit;
    }

    .form-control:focus {
        border-color: #10b981;
        box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.1);
    }
    
    .table th {
        font-size: 0.82rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #475569;
        letter-spacing: 0.05em;
        background: #f8fafc;
        border-bottom: 2px solid #e2e8f0;
        padding: 12px 8px;
    }
    
    .table td {
        padding: 8px;
        vertical-align: middle;
    }
</style>

<script>
    function showFileName(input) {
        if (input.files.length > 0) {
            document.getElementById("filePreview").innerHTML = `<i class="ri-file-check-line" style="color:#10b981;"></i> ${input.files[0].name}`;
        } else {
            document.getElementById("filePreview").innerText = "No file chosen";
        }
    }
</script>

<?php include 'includes/footer.php'; ?>
