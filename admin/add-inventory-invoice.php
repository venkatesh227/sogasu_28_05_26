<?php
session_start();
include '../includes/db.php';

// Fetch categories dynamically
$categories_stmt = $pdo->query("SELECT * FROM inventory_categories WHERE status = 1 AND is_deleted = 0 ORDER BY name ASC");
$inventory_categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch active suppliers from the suppliers master table
$suppliers_stmt = $pdo->query("SELECT supplier_name, contact AS supplier_contact 
                               FROM suppliers 
                               WHERE status = 'active' AND is_deleted = 0 
                               ORDER BY supplier_name ASC");
$unique_suppliers = $suppliers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare options string for javascript dynamic rows
$js_categories = [];
foreach ($inventory_categories as $cat) {
    $js_categories[] = "<option value='" . htmlspecialchars($cat['code'], ENT_QUOTES) . "'>" . htmlspecialchars($cat['name'], ENT_QUOTES) . "</option>";
}
$js_category_options = implode('', $js_categories);

$pageTitle = "Add Inventory via Invoice - Sogasu";
$activePage = "inventory";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div>
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Add Stock via Invoice</h2>
                <p class="text-muted" style="margin: 0.25rem 0 0 0;">Register a batch of inventory materials from a supplier purchase invoice</p>
            </div>
            <button class="btn" onclick="history.back()"
                style="background: white; border: 1px solid #e2e8f0; color: #64748b; font-weight: 600;">
                <i class="ri-arrow-left-line"></i> Back
            </button>
        </div>
    </div>

    <form action="save-inventory-invoice.php" method="POST" enctype="multipart/form-data" id="invoiceForm">

        <!-- Card 1: Invoice Header Information -->
        <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-top: 0; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="ri-file-text-line" style="color: #4f46e5;"></i> Invoice Details
            </h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem;">
                <div class="form-group">
                    <label class="form-label">Invoice Number <span style="color: red;">*</span></label>
                    <input type="text" name="invoice_no" class="form-control" required placeholder="e.g. INV-2026-9041">
                </div>
                <div class="form-group">
                    <label class="form-label">Invoice Date <span style="color: red;">*</span></label>
                    <input type="date" name="invoice_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Supplier Name <span style="color: red;">*</span></label>
                    <input type="text" id="supplierNameInput" name="supplier_name" class="form-control" required placeholder="e.g. RK Textiles" list="supplierList">
                </div>
                <div class="form-group">
                    <label class="form-label">Supplier Contact</label>
                    <input type="text" id="supplierContactInput" name="contact" maxlength="10" class="form-control" placeholder="10-digit Phone or Email">
                </div>

                <!-- Unique Suppliers Datalist -->
                <datalist id="supplierList">
                    <?php foreach ($unique_suppliers as $supp): ?>
                        <option value="<?= htmlspecialchars($supp['supplier_name']) ?>" data-contact="<?= htmlspecialchars($supp['supplier_contact'] ?? '') ?>"></option>
                    <?php endforeach; ?>
                </datalist>
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

        <!-- Card 2: Items List Grid -->
        <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem; overflow: visible;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="ri-archive-line" style="color: #4f46e5;"></i> Purchased Stock Items
                </h3>
                <button type="button" class="btn" onclick="addRow()"
                    style="background: #eef2ff; border: 1px solid #c7d2fe; color: #4f46e5; font-weight: 700; font-size: 0.85rem; padding: 6px 14px; border-radius: 6px;">
                    <i class="ri-add-line"></i> Add Item Row
                </button>
            </div>

            <div style="overflow-x: auto; margin: 0 -1.5rem; padding: 0 1.5rem;">
                <table id="itemsTable" class="table" style="min-width: 1000px; margin-bottom: 0;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e2e8f0;">
                            <th style="width: 28%;">Item Name <span style="color: red;">*</span></th>
                            <th style="width: 15%;">Category <span style="color: red;">*</span></th>
                            <th style="width: 12%;">Unit of Measure <span style="color: red;">*</span></th>
                            <th style="width: 10%;">Quantity <span style="color: red;">*</span></th>
                            <th style="width: 10%;">Cost per Unit (₹) <span style="color: red;">*</span></th>
                            <th style="width: 12%;">Total Price (₹)</th>
                            <th style="width: 10%;">Min. Alert <span style="color: red;">*</span></th>
                            <th style="width: 3%; text-align: center;">Act</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="item-row">
                            <td>
                                <input type="text" name="item_name[]" class="form-control" required placeholder="e.g. Red Silk Fabric">
                                <input type="hidden" name="sku[]" value="">
                            </td>
                            <td>
                                <select name="category[]" class="form-select" required>
                                    <option value="">Select</option>
                                    <?php foreach ($inventory_categories as $cat): ?>
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
                            <td>
                                <input type="number" name="cost[]" class="form-control cost-input" step="0.01" min="0" required placeholder="0.00">
                            </td>
                            <td>
                                <input type="text" class="form-control row-total" readonly value="0.00" style="background: #f8fafc; font-weight: 600; text-align: right;">
                            </td>
                            <td>
                                <input type="number" name="low_stock[]" class="form-control low-input" min="0" required value="5" placeholder="e.g. 5">
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                <button type="button" class="btn-remove-row" style="background:none; border:none; color:#ef4444; font-size:1.2rem; cursor:pointer;" onclick="removeRow(this)">
                                    <i class="ri-delete-bin-line"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr style="border-top: 2px solid #e2e8f0; background: #f8fafc; font-weight: 700;">
                            <td colspan="5" style="text-align: right; padding: 12px; font-size: 0.95rem; color: #475569; vertical-align: middle;">Grand Total:</td>
                            <td style="padding: 8px; vertical-align: middle;">
                                <input type="text" id="grandTotal" readonly class="form-control" style="background: #eef2ff; color: #4f46e5; border-color: #c7d2fe; font-weight: 700; font-size: 1rem; text-align: right;" value="₹0.00">
                            </td>
                            <td></td>
                            <td></td>
                        </tr>
                    </tfoot>
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
                Save Invoice and Stock
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
        border-color: var(--primary);
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
    
    function showFileName(input) {
        if (input.files.length > 0) {
            document.getElementById("filePreview").innerHTML = `<i class="ri-file-check-line" style="color:#10b981;"></i> ${input.files[0].name}`;
        } else {
            document.getElementById("filePreview").innerText = "No file chosen";
        }
    }

    function addRow() {
        const tbody = document.querySelector('#itemsTable tbody');
        const tr = document.createElement('tr');
        tr.className = 'item-row';
        tr.style.opacity = '0';
        tr.style.transform = 'translateY(8px)';
        tr.style.transition = 'all 0.2s ease-out';
        
        tr.innerHTML = `
            <td>
                <input type="text" name="item_name[]" class="form-control" required placeholder="e.g. Red Silk Fabric">
                <input type="hidden" name="sku[]" value="">
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
            <td>
                <input type="number" name="cost[]" class="form-control cost-input" step="0.01" min="0" required placeholder="0.00">
            </td>
            <td>
                <input type="text" class="form-control row-total" readonly value="0.00" style="background: #f8fafc; font-weight: 600; text-align: right;">
            </td>
            <td>
                <input type="number" name="low_stock[]" class="form-control low-input" min="0" required value="5" placeholder="e.g. 5">
            </td>
            <td style="text-align: center; vertical-align: middle;">
                <button type="button" class="btn-remove-row" style="background:none; border:none; color:#ef4444; font-size:1.2rem; cursor:pointer;" onclick="removeRow(this)">
                    <i class="ri-delete-bin-line"></i>
                </button>
            </td>
        `;
        
        tbody.appendChild(tr);
        // Trigger animate-in
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
                text: 'Your invoice must register at least one stock item.',
                confirmButtonColor: '#4f46e5'
            });
            return;
        }
        const tr = btn.closest('tr');
        tr.style.opacity = '0';
        tr.style.transform = 'translateY(8px)';
        setTimeout(() => {
            tr.remove();
            calculateTotals();
        }, 200);
    }

    // Dynamic translation dictionary for unit updates
    const unitMap = {
        'meters': { qty: 'e.g. 10.0', low: 'e.g. 5' },
        'pieces': { qty: 'e.g. 10', low: 'e.g. 5' },
        'rolls': { qty: 'e.g. 2', low: 'e.g. 1' },
        'boxes': { qty: 'e.g. 5', low: 'e.g. 2' }
    };

    // Dynamic calculation function for row totals and grand total
    function calculateTotals() {
        let grandTotal = 0;
        const rows = document.querySelectorAll('.item-row');
        rows.forEach(row => {
            const qtyInput = row.querySelector('.qty-input');
            const costInput = row.querySelector('.cost-input');
            const totalInput = row.querySelector('.row-total');
            
            const qty = parseFloat(qtyInput.value) || 0;
            const cost = parseFloat(costInput.value) || 0;
            const total = qty * cost;
            
            if (totalInput) {
                totalInput.value = total.toFixed(2);
            }
            grandTotal += total;
        });
        
        const grandTotalInput = document.getElementById('grandTotal');
        if (grandTotalInput) {
            grandTotalInput.value = '₹' + grandTotal.toFixed(2);
        }
    }

    // Attach event delegation for live placeholder updates per row
    document.querySelector('#itemsTable').addEventListener('change', function(e) {
        if (e.target && e.target.classList.contains('unit-select')) {
            const select = e.target;
            const row = select.closest('tr');
            const qtyInput = row.querySelector('.qty-input');
            const lowInput = row.querySelector('.low-input');
            
            const config = unitMap[select.value] || { qty: 'e.g. 10.0', low: 'e.g. 5' };
            
            if (qtyInput) qtyInput.placeholder = config.qty;
            if (lowInput) lowInput.placeholder = config.low;
        }
    });

    // Auto-calculate row totals and grand total on quantity/cost inputs
    document.querySelector('#itemsTable').addEventListener('input', function(e) {
        if (e.target && (e.target.classList.contains('qty-input') || e.target.classList.contains('cost-input'))) {
            calculateTotals();
        }
    });

    document.querySelector('#itemsTable').addEventListener('change', function(e) {
        if (e.target && (e.target.classList.contains('qty-input') || e.target.classList.contains('cost-input'))) {
            calculateTotals();
        }
    });

    // Supplier autocomplete auto-fill handler
    document.addEventListener('DOMContentLoaded', function() {
        // Run initial calculation in case of edit or default rows
        calculateTotals();

        const supplierNameInput = document.getElementById('supplierNameInput');
        const supplierContactInput = document.getElementById('supplierContactInput');
        const supplierList = document.getElementById('supplierList');

        if (supplierNameInput && supplierContactInput && supplierList) {
            const handleSupplierAutoFill = function() {
                const val = supplierNameInput.value.trim().toLowerCase();
                let matchedContact = '';
                let found = false;
                Array.from(supplierList.options).forEach(opt => {
                    if (opt.value.trim().toLowerCase() === val) {
                        matchedContact = opt.getAttribute('data-contact') || '';
                        found = true;
                    }
                });
                if (found) {
                    supplierContactInput.value = matchedContact;
                }
            };

            supplierNameInput.addEventListener('input', handleSupplierAutoFill);
            supplierNameInput.addEventListener('change', handleSupplierAutoFill);
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
