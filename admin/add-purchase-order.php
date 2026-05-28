<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch active suppliers
$suppliers_stmt = $pdo->query("
    SELECT id, supplier_name, firm_name, contact_person, phone_no 
    FROM suppliers 
    WHERE status = 'active' AND is_deleted = 0 
    ORDER BY supplier_name ASC
");
$suppliers = $suppliers_stmt->fetchAll();

// Fetch categories dynamically
$categories_stmt = $pdo->query("
    SELECT * 
    FROM inventory_categories 
    WHERE status = 1 AND is_deleted = 0 
    ORDER BY name ASC
");
$categories = $categories_stmt->fetchAll();

// Prepare options string for javascript dynamic rows
$js_categories = [];
foreach ($categories as $cat) {
    $js_categories[] = "<option value='" . htmlspecialchars($cat['code'], ENT_QUOTES) . "'>" . htmlspecialchars($cat['name'], ENT_QUOTES) . "</option>";
}
$js_category_options = implode('', $js_categories);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = intval($_POST['supplier_id'] ?? 0);
    $order_date = trim($_POST['order_date'] ?? '');
    $delivery_date = trim($_POST['delivery_date'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // Items arrays
    $item_names = $_POST['item_name'] ?? [];
    $categories_input = $_POST['category'] ?? [];
    $units = $_POST['unit'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $costs = $_POST['cost'] ?? [];
    
    $errors = [];
    
    // Header validations
    if ($supplier_id <= 0) {
        $errors[] = "Please select a valid supplier.";
    }
    if (empty($order_date)) {
        $errors[] = "Order date is required.";
    }
    
    $items_count = count($item_names);
    if ($items_count === 0) {
        $errors[] = "Purchase order must contain at least one item.";
    }
    
    // Row level validations
    for ($i = 0; $i < $items_count; $i++) {
        $name = trim($item_names[$i] ?? '');
        $cat = trim($categories_input[$i] ?? '');
        $unit = trim($units[$i] ?? '');
        $qty = $quantities[$i] ?? '';
        $row_num = $i + 1;
        
        if ($name === '') {
            $errors[] = "Row #{$row_num}: Item name is required.";
        }
        if ($cat === '') {
            $errors[] = "Row #{$row_num}: Category selection is required.";
        }
        if ($unit === '') {
            $errors[] = "Row #{$row_num}: Unit of measure is required.";
        }
        if ($qty === '' || !is_numeric($qty) || $qty <= 0) {
            $errors[] = "Row #{$row_num}: Quantity must be a positive number.";
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // 1. Generate PO Number dynamically: e.g. PO-2026-0001
            $year = date('Y', strtotime($order_date));
            $seq_stmt = $pdo->query("SELECT COUNT(*) FROM purchase_orders");
            $next_seq = intval($seq_stmt->fetchColumn()) + 1;
            
            // Ensure unique PO number
            $po_number = 'PO-' . $year . '-' . str_pad($next_seq, 4, '0', STR_PAD_LEFT);
            $dup_chk = $pdo->prepare("SELECT COUNT(*) FROM purchase_orders WHERE po_number = ?");
            while (true) {
                $dup_chk->execute([$po_number]);
                if ($dup_chk->fetchColumn() == 0) {
                    break;
                }
                $next_seq++;
                $po_number = 'PO-' . $year . '-' . str_pad($next_seq, 4, '0', STR_PAD_LEFT);
            }
            
            // 2. Set PO Total to 0.00 initially (pricing set during receipt)
            $grand_total = 0.00;
            
            // 3. Insert Purchase Order
            $po_stmt = $pdo->prepare("
                INSERT INTO purchase_orders 
                (po_number, supplier_id, order_date, delivery_date, total_amount, notes, status, is_deleted) 
                VALUES (?, ?, ?, ?, ?, ?, 'Pending', 0)
            ");
            
            $po_stmt->execute([
                $po_number,
                $supplier_id,
                $order_date,
                !empty($delivery_date) ? $delivery_date : null,
                $grand_total,
                !empty($notes) ? $notes : null
            ]);
            
            $purchase_order_id = $pdo->lastInsertId();
            
            // 4. Insert PO Items
            $item_stmt = $pdo->prepare("
                INSERT INTO purchase_order_items 
                (purchase_order_id, item_name, sku, category, quantity, unit, cost, received_quantity) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 0.00)
            ");
            
            for ($i = 0; $i < $items_count; $i++) {
                $name = trim($item_names[$i]);
                $sku = '';
                $cat = trim($categories_input[$i]);
                $unit = trim($units[$i]);
                $qty = floatval($quantities[$i]);
                $cost = 0.00;
                
                // Dynamic SKU generation if left blank
                if (empty($sku)) {
                    $cat_prefix = strtoupper(substr($cat, 0, 3));
                    if ($cat === 'access') $cat_prefix = 'ACC';
                    if (empty($cat_prefix)) $cat_prefix = 'INV';
                    
                    $clean_name = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $name));
                    $name_prefix = substr($clean_name, 0, 3);
                    if (strlen($name_prefix) < 3) {
                        $name_prefix = str_pad($name_prefix, 3, 'X');
                    }
                    
                    $suffix = rand(1000, 9999);
                    $sku = $cat_prefix . '-' . $name_prefix . '-' . $suffix;
                    
                    // Check database for duplication
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
                
                $item_stmt->execute([
                    $purchase_order_id,
                    $name,
                    $sku,
                    $cat,
                    $qty,
                    $unit,
                    $cost
                ]);
            }
            
            $pdo->commit();
            
            $_SESSION['success'] = "Purchase Order " . $po_number . " has been successfully raised!";
            header("Location: purchase-orders.php");
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Database error while saving PO: " . $e->getMessage();
        }
    }
}

$pageTitle = "Raise Purchase Order - Sogasu";
$activePage = "purchase-orders";

include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div>
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Raise Purchase Order</h2>
                <p class="text-muted" style="margin: 0.25rem 0 0 0;">Create a new official purchase request to a supplier</p>
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

    <form method="POST" id="poForm">
        
        <!-- Supplier & PO Info Card -->
        <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-top: 0; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="ri-truck-line" style="color: #4f46e5;"></i> General PO Details
            </h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem;">
                <div class="form-group">
                    <label class="form-label">Select Supplier <span style="color: red;">*</span></label>
                    <select name="supplier_id" id="supplierSelect" class="form-select" required>
                        <option value="">Choose Supplier</option>
                        <?php foreach ($suppliers as $supp): ?>
                            <option value="<?= $supp['id'] ?>" <?= (isset($_POST['supplier_id']) && $_POST['supplier_id'] == $supp['id']) ? 'selected' : '' ?> data-contact="<?= htmlspecialchars($supp['phone_no'] ?: '') ?>" data-firm="<?= htmlspecialchars($supp['firm_name'] ?: '') ?>">
                                <?= htmlspecialchars($supp['supplier_name']) ?> (<?= htmlspecialchars($supp['firm_name'] ?: 'No Firm Name') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Order Date <span style="color: red;">*</span></label>
                    <input type="date" name="order_date" class="form-control" required value="<?= isset($_POST['order_date']) ? htmlspecialchars($_POST['order_date']) : date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Expected Delivery</label>
                    <input type="date" name="delivery_date" class="form-control" value="<?= isset($_POST['delivery_date']) ? htmlspecialchars($_POST['delivery_date']) : '' ?>">
                </div>
                <div class="form-group" style="grid-column: span 1;">
                    <label class="form-label">Supplier Contact Details</label>
                    <input type="text" id="contactDisplay" class="form-control" readonly style="background: #f8fafc; font-weight: 600;" placeholder="Select a supplier">
                </div>
            </div>
            
            <div class="form-group" style="margin-top: 1.25rem;">
                <label class="form-label">General PO Notes / Special Instructions</label>
                <textarea name="notes" rows="2" class="form-control" placeholder="Add specific terms, shipping method, or comments..."><?= isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : '' ?></textarea>
            </div>
        </div>

        <!-- Ordered Items Card -->
        <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="ri-archive-line" style="color: #4f46e5;"></i> Order Line Items
                </h3>
                <button type="button" class="btn" onclick="addRow()"
                    style="background: #eef2ff; border: 1px solid #c7d2fe; color: #4f46e5; font-weight: 700; font-size: 0.85rem; padding: 6px 14px; border-radius: 6px;">
                    <i class="ri-add-line"></i> Add Item Row
                </button>
            </div>

            <div style="overflow-x: auto; margin: 0 -1.5rem; padding: 0 1.5rem;">
                <table id="poItemsTable" class="table" style="min-width: 800px; margin-bottom: 0;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e2e8f0;">
                            <th style="width: 45%;">Item Name <span style="color: red;">*</span></th>
                            <th style="width: 20%;">Category <span style="color: red;">*</span></th>
                            <th style="width: 18%;">Unit of Measure <span style="color: red;">*</span></th>
                            <th style="width: 14%;">Quantity <span style="color: red;">*</span></th>
                            <th style="width: 3%; text-align: center;">Act</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($_POST['item_name']) && count($_POST['item_name']) > 0): ?>
                            <?php for ($i = 0; $i < count($_POST['item_name']); $i++): ?>
                                <tr class="item-row">
                                    <td>
                                        <input type="text" name="item_name[]" class="form-control" required placeholder="e.g. Red Silk Fabric" value="<?= htmlspecialchars($_POST['item_name'][$i]) ?>">
                                    </td>
                                    <td>
                                        <select name="category[]" class="form-select" required>
                                            <option value="">Select</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= htmlspecialchars($cat['code']) ?>" <?= ($_POST['category'][$i] == $cat['code']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="unit[]" class="form-select unit-select" required>
                                            <option value="meters" <?= ($_POST['unit'][$i] == 'meters') ? 'selected' : '' ?>>Meters</option>
                                            <option value="pieces" <?= ($_POST['unit'][$i] == 'pieces') ? 'selected' : '' ?>>Pieces</option>
                                            <option value="rolls" <?= ($_POST['unit'][$i] == 'rolls') ? 'selected' : '' ?>>Rolls</option>
                                            <option value="boxes" <?= ($_POST['unit'][$i] == 'boxes') ? 'selected' : '' ?>>Boxes</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" name="quantity[]" class="form-control qty-input" step="0.01" min="0" required placeholder="e.g. 10.0" value="<?= htmlspecialchars($_POST['quantity'][$i]) ?>">
                                    </td>
                                    <td style="text-align: center; vertical-align: middle;">
                                        <button type="button" class="btn-remove-row" style="background:none; border:none; color:#ef4444; font-size:1.2rem; cursor:pointer;" onclick="removeRow(this)">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endfor; ?>
                        <?php else: ?>
                            <tr class="item-row">
                                <td>
                                    <input type="text" name="item_name[]" class="form-control" required placeholder="e.g. Red Silk Fabric">
                                </td>
                                <td>
                                    <select name="category[]" class="form-select" required>
                                        <option value="">Select</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= htmlspecialchars($cat['code']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <select name="unit[]" class="form-select unit-select" required>
                                        <option value="meters">Meters</option>
                                        <option value="pieces">Pieces</option>
                                        <option value="rolls">Rolls</option>
                                        <option value="boxes">Boxes</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="quantity[]" class="form-control qty-input" step="0.01" min="0" required placeholder="e.g. 10.0">
                                </td>
                                <td style="text-align: center; vertical-align: middle;">
                                    <button type="button" class="btn-remove-row" style="background:none; border:none; color:#ef4444; font-size:1.2rem; cursor:pointer;" onclick="removeRow(this)">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <!-- Foot section excluded -->
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
                style="background: #4f46e5; border: none; font-weight: 700; padding: 10px 32px; border-radius: 8px; color: white;">
                Raise Official Purchase Order
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

    .form-control,
    .form-select {
        padding: 0.65rem 0.75rem;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 0.92rem;
        width: 100%;
        outline: none;
        transition: border-color 0.2s;
        font-family: inherit;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: #4f46e5;
        box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
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
    
    .item-row {
        transition: all 0.25s ease-out;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const categoryOptions = `<?= $js_category_options ?>`;
    
    function addRow() {
        const tbody = document.querySelector('#poItemsTable tbody');
        const tr = document.createElement('tr');
        tr.className = 'item-row';
        tr.style.opacity = '0';
        tr.style.transform = 'translateY(8px)';
        tr.style.transition = 'all 0.2s ease-out';
        
        tr.innerHTML = `
            <td>
                <input type="text" name="item_name[]" class="form-control" required placeholder="e.g. Red Silk Fabric">
            </td>
            <td>
                <select name="category[]" class="form-select" required>
                    <option value="">Select</option>
                    ${categoryOptions}
                </select>
            </td>
            <td>
                <select name="unit[]" class="form-select unit-select" required>
                    <option value="meters">Meters</option>
                    <option value="pieces">Pieces</option>
                    <option value="rolls">Rolls</option>
                    <option value="boxes">Boxes</option>
                </select>
            </td>
            <td>
                <input type="number" name="quantity[]" class="form-control qty-input" step="0.01" min="0" required placeholder="e.g. 10.0">
            </td>
            <td style="text-align: center; vertical-align: middle;">
                <button type="button" class="btn-remove-row" style="background:none; border:none; color:#ef4444; font-size:1.2rem; cursor:pointer;" onclick="removeRow(this)">
                    <i class="ri-delete-bin-line"></i>
                </button>
            </td>
        `;
        
        tbody.appendChild(tr);
        setTimeout(() => {
            tr.style.opacity = '1';
            tr.style.transform = 'translateY(0)';
        }, 10);
    }

    function removeRow(btn) {
        const rows = document.querySelectorAll('.item-row');
        if (rows.length <= 1) {
            Swal.fire({
                icon: 'warning',
                title: 'Action Blocked',
                text: 'Your purchase order must request at least one item.',
                confirmButtonColor: '#4f46e5'
            });
            return;
        }
        const tr = btn.closest('tr');
        tr.style.opacity = '0';
        tr.style.transform = 'translateY(8px)';
        setTimeout(() => {
            tr.remove();
        }, 200);
    }

    const unitMap = {
        'meters': { qty: 'e.g. 10.0' },
        'pieces': { qty: 'e.g. 10' },
        'rolls': { qty: 'e.g. 2' },
        'boxes': { qty: 'e.g. 5' }
    };

    // Attach event delegation for placeholders
    document.querySelector('#poItemsTable').addEventListener('change', function(e) {
        if (e.target && e.target.classList.contains('unit-select')) {
            const select = e.target;
            const row = select.closest('tr');
            const qtyInput = row.querySelector('.qty-input');
            const config = unitMap[select.value] || { qty: 'e.g. 10.0' };
            if (qtyInput) qtyInput.placeholder = config.qty;
        }
    });

    // Supplier details display handler
    document.addEventListener('DOMContentLoaded', function() {
        const supplierSelect = document.getElementById('supplierSelect');
        const contactDisplay = document.getElementById('contactDisplay');

        function updateSupplierContact() {
            const selectedOpt = supplierSelect.options[supplierSelect.selectedIndex];
            if (selectedOpt && selectedOpt.value !== '') {
                const contact = selectedOpt.getAttribute('data-contact') || 'N/A';
                const firm = selectedOpt.getAttribute('data-firm') || 'N/A';
                contactDisplay.value = `Firm: ${firm} | Tel: ${contact}`;
            } else {
                contactDisplay.value = '';
            }
        }

        if (supplierSelect && contactDisplay) {
            supplierSelect.addEventListener('change', updateSupplierContact);
            updateSupplierContact();
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
