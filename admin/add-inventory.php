<?php
session_start();
include '../includes/db.php';

$id = $_GET['id'] ?? null;
$old = [];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ? AND is_deleted = 0");
    $stmt->execute([$id]);
    $item = $stmt->fetch();

    if (!$item) {
        die("Item not found");
    }

    $old = $item;
}

// Fetch active suppliers from the suppliers master table
$suppliers_stmt = $pdo->query("SELECT supplier_name, contact AS supplier_contact 
                               FROM suppliers 
                               WHERE status = 'active' AND is_deleted = 0 
                               ORDER BY supplier_name ASC");
$unique_suppliers = $suppliers_stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $item_name = trim($_POST['item_name']);
    $sku = trim($_POST['sku']);
    $category = $_POST['category'];
    $description = trim($_POST['description']);
    $unit = $_POST['unit'];
    $cost = $_POST['cost'];
    $quantity = $_POST['quantity'];
    $low_stock = $_POST['low_stock'];
    $status = $_POST['status'];
    $supplier_name = trim($_POST['supplier_name']);
    $contact = trim($_POST['contact']);

    $created_at = date("Y-m-d H:i:s");
    $updated_at = date("Y-m-d H:i:s");

    $errors = [];

    // AUTO GENERATE SKU IF EMPTY
    if ($sku == '' && $item_name != '') {
        $cat_prefix = strtoupper(substr($category, 0, 3));
        if ($category == 'access')
            $cat_prefix = 'ACC';
        if ($cat_prefix == '')
            $cat_prefix = 'INV';

        $clean_name = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $item_name));
        $name_prefix = substr($clean_name, 0, 3);
        if (strlen($name_prefix) < 3) {
            $name_prefix = str_pad($name_prefix, 3, 'X');
        }

        $suffix = rand(1000, 9999);
        $sku = $cat_prefix . '-' . $name_prefix . '-' . $suffix;

        // Ensure SKU is unique
        $chk = $pdo->prepare("SELECT COUNT(*) FROM inventory WHERE sku = ? AND is_deleted = 0");
        while (true) {
            $chk->execute([$sku]);
            if ($chk->fetchColumn() == 0) {
                break;
            }
            $suffix = rand(1000, 9999);
            $sku = $cat_prefix . '-' . $name_prefix . '-' . $suffix;
        }
    }

    // VALIDATIONS

    if ($item_name == '') {

        $errors['item_name'] = "Item name is required";

    } elseif (!preg_match("/^[A-Za-z0-9\s\-\&\.\(\)]+$/", $item_name)) {

        $errors['item_name'] = "Invalid item name";
    }


    if ($sku != '') {

        if ($id) {

            $skuCheck = $pdo->prepare("SELECT COUNT(*) FROM inventory 
                                   WHERE sku = ? 
                                   AND id != ? 
                                   AND is_deleted = 0");

            $skuCheck->execute([$sku, $id]);

        } else {

            $skuCheck = $pdo->prepare("SELECT COUNT(*) FROM inventory 
                                   WHERE sku = ? 
                                   AND is_deleted = 0");

            $skuCheck->execute([$sku]);
        }

        if ($skuCheck->fetchColumn() > 0) {

            $errors['sku'] = "SKU already exists";
        }
    }


    if ($category == '') {

        $errors['category'] = "Category is required";
    }


    if ($unit == '') {

        $errors['unit'] = "Unit is required";
    }


    if ($cost === '') {

        $errors['cost'] = "Cost is required";

    } elseif (!is_numeric($cost)) {

        $errors['cost'] = "Cost must be numeric";

    } elseif ((float)$cost < 0) {

        $errors['cost'] = "Cost cannot be negative";
    }


    if ($quantity === '') {

        $errors['quantity'] = "Quantity is required";

    } elseif (!is_numeric($quantity)) {

        $errors['quantity'] = "Quantity must be numeric";

    } elseif ((float)$quantity < 0) {

        $errors['quantity'] = "Quantity cannot be negative";
    }


    if ($low_stock === '') {

        $errors['low_stock'] = "Low stock alert is required";

    } elseif (!is_numeric($low_stock)) {

        $errors['low_stock'] = "Low stock must be numeric";

    } elseif ((float)$low_stock < 0) {

        $errors['low_stock'] = "Low stock cannot be negative";
    }


    if (!in_array($status, ['0', '1', 0, 1], true)) {

        $errors['status'] = "Invalid status";
    }


    if (
        !empty($supplier_name) &&
        !preg_match("/^[A-Za-z0-9\s\-\&\.\(\)]+$/", $supplier_name)
    ) {

        $errors['supplier_name'] = "Invalid supplier name";
    }


    if (!empty($contact)) {

        $contact = trim($contact);

        $isPhone = preg_match("/^[0-9]{10}$/", $contact);

        $isEmail = filter_var($contact, FILTER_VALIDATE_EMAIL);

        if (!$isPhone && !$isEmail) {

            $errors['contact'] = "Enter valid phone number or email";
        }
    }

    if (empty($errors)) {

        if ($id) {
            //  UPDATE
            $stmt = $pdo->prepare("UPDATE inventory SET

item_name=?,
sku=?,
category=?,
description=?,
unit=?,
cost=?,
quantity=?,
low_stock_alert=?,
status=?,
supplier_name=?,
supplier_contact=?,
item_image=?,
updated_at=?

WHERE id=?");
            if (
                $stmt->execute([
                    $item_name,
                    $sku,
                    $category,
                    $description,
                    $unit,
                    $cost,
                    $quantity,
                    $low_stock,
                    $status,
                    $supplier_name,
                    $contact,
                    $image_name,
                    $updated_at,
                    $id
                ])
            ) {


                $_SESSION['success'] = "Inventory item updated successfully!";
                header("Location: inventory.php");
                exit;
            }

        } else {
            //  INSERT
            $stmt = $pdo->prepare("INSERT INTO inventory 

(
item_name,
sku,
category,
description,
unit,
cost,
quantity,
low_stock_alert,
status,
supplier_name,
supplier_contact,
item_image,
created_at,
is_deleted
)

VALUES

(?,?,?,?,?,?,?,?,?,?,?,?,?,0)");
            if (
                $stmt->execute([
                    $item_name,
                    $sku,
                    $category,
                    $description,
                    $unit,
                    $cost,
                    $quantity,
                    $low_stock,
                    $status,
                    $supplier_name,
                    $contact,
                    $image_name,
                    $created_at
                ])
            ) {
                $_SESSION['success'] = "Inventory item added successfully!";
                header("Location: inventory.php");
                exit;
            }
        }

        $error = "DB operation failed";
    }
}

$pageTitle = "Add Inventory Item - Sogasu";
$activePage = "inventory";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div>
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Add New Item</h2>
                <p class="text-muted">Register new stock materials</p>
            </div>
            <button class="btn" onclick="history.back()"
                style="background: white; border: 1px solid #e2e8f0; color: #64748b;">
                <i class="ri-arrow-left-line"></i> Back
            </button>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data" style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">

        <!-- Left Column: Item Details -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">

            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Item Information
                </h3>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Item Name <span style="color:red">*</span></label>
                        <input type="text" name="item_name" maxlength="100" class="form-control"
                            placeholder="e.g. Red Silk Fabric" value="<?= $old['item_name'] ?? '' ?>">
                        <?php if (isset($errors['item_name']))
                            echo "<div style='color:red; font-size:0.9rem; margin-top:0.5rem;'>{$errors['item_name']}</div>"; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">SKU / Code<span
                                style="font-size:0.75rem; color:#64748b; font-weight: normal;">(Optional)</span></label>
                        <input type="text" name="sku" maxlength="50" class="form-control"
                            placeholder="e.g. FAB-RED-001 (Auto-generated if left blank)"
                            value="<?= $old['sku'] ?? '' ?>">
                        <?php if (isset($errors['sku']))
                            echo "<div style='color:red; font-size:0.9rem; margin-top:0.5rem;'>{$errors['sku']}</div>"; ?>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Category <span style="color:red">*</span></label>
                    <select name="category" class="form-select">
                        <option value="">Select Category</option>
                        <?php
                        $categories_stmt = $pdo->query("SELECT * FROM inventory_categories WHERE status = 1 AND is_deleted = 0 ORDER BY name ASC");
                        $inventory_categories = $categories_stmt->fetchAll();
                        if (!empty($inventory_categories)):
                            foreach ($inventory_categories as $cat):
                                ?>
                                <option value="<?= htmlspecialchars($cat['code']) ?>" <?= ($old['category'] ?? '') == $cat['code'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                <?php
                            endforeach;
                        else:
                            ?>
                            <option value="fabric" <?= ($old['category'] ?? '') == 'fabric' ? 'selected' : '' ?>>Fabric
                            </option>
                            <option value="lining" <?= ($old['category'] ?? '') == 'lining' ? 'selected' : '' ?>>Lining
                            </option>
                            <option value="thread" <?= ($old['category'] ?? '') == 'thread' ? 'selected' : '' ?>>Thread
                            </option>
                            <option value="access" <?= ($old['category'] ?? '') == 'access' ? 'selected' : '' ?>>Accessories
                                (Buttons/Zips)</option>
                        <?php endif; ?>
                    </select>
                    <?php if (isset($errors['category']))
                        echo "<div style='color:red; font-size:0.9rem; margin-top:0.5rem;'>{$errors['category']}</div>"; ?>
                </div>

                <div class="form-group">
                    <label class="form-label">Description / Notes</label>
                    <textarea name="description" maxlength="255" class="form-control" rows="3"
                        placeholder="Supplier info, material quality, etc."><?= $old['description'] ?? '' ?></textarea>
                </div>
            </div>

            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Stock & Pricing
                </h3>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Unit of Measure</label>
                        <select name="unit" id="unitSelect" class="form-select">
                            <option value="meters" <?= ($old['unit'] ?? '') == 'meters' ? 'selected' : '' ?>>Meters
                            </option>
                            <option value="pieces" <?= ($old['unit'] ?? '') == 'pieces' ? 'selected' : '' ?>>Pieces
                            </option>
                            <option value="rolls" <?= ($old['unit'] ?? '') == 'rolls' ? 'selected' : '' ?>>Rolls</option>
                            <option value="boxes" <?= ($old['unit'] ?? '') == 'boxes' ? 'selected' : '' ?>>Boxes</option>
                        </select>
                        <?php if (isset($errors['unit']))
                            echo "<div style='color:red; font-size:0.9rem; margin-top:0.5rem;'>{$errors['unit']}</div>"; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label" id="costLabel">Cost per Unit (₹)</label>
                        <input type="number" name="cost" step="0.01" class="form-control" placeholder="0.00"
                            value="<?= $old['cost'] ?? '' ?>">
                        <?php if (isset($errors['cost']))
                            echo "<div style='color:red; font-size:0.9rem; margin-top:0.5rem;'>{$errors['cost']}</div>"; ?>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" id="quantityLabel">Quantity in Stock</label>
                        <input type="number" name="quantity" id="quantityInput" class="form-control" 
                            style="background: #f8fafc; font-weight: 700; color: #475569;" placeholder="0.0"
                            value="<?= isset($old['quantity']) ? htmlspecialchars($old['quantity']) : '0.0' ?>">
                        <div
                            style="background: rgba(79, 70, 229, 0.06); border: 1px dashed rgba(79, 70, 229, 0.2); color: #4f46e5; border-radius: 6px; padding: 8px 10px; font-size: 0.76rem; font-weight: 500; margin-top: 5px; line-height: 1.4;">
                            <i class="ri-information-line" style="font-size: 0.9rem; vertical-align: middle;"></i> Stock
                            levels must be managed by raising and receiving a <a href="purchase-orders.php"
                                style="font-weight: 700; color: #4f46e5; text-decoration: underline;">Purchase
                                Order</a>.
                        </div>
                        <?php if (isset($errors['quantity']))
                            echo "<div style='color:red; font-size:0.9rem; margin-top:0.5rem;'>{$errors['quantity']}</div>"; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label" id="lowStockLabel">Low Stock Alert Level</label>
                        <input type="number" name="low_stock" id="lowStockInput" class="form-control"
                            placeholder="e.g. 5" value="<?= $old['low_stock_alert'] ?? '5' ?>">
                        <?php if (isset($errors['low_stock']))
                            echo "<div style='color:red; font-size:0.9rem; margin-top:0.5rem;'>{$errors['low_stock']}</div>"; ?>
                    </div>
                </div>

            </div>

        </div>

        <!-- Right Column: Image & Status -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">

            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Item Image</h3>

                <!-- Upload Box -->
                <div onclick="document.getElementById('imageInput').click()"
                    style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 6px; padding: 2rem; text-align: center; margin-bottom: 1rem; cursor:pointer;">

                    <i class="ri-image-add-line" style="font-size: 2rem; color: #94a3b8;"></i>

                    <div style="font-size: 0.9rem; color: #64748b; margin-top: 0.5rem;">
                        Click to upload (Max 5MB | JPG, PNG)
                    </div>

                    <!--  HIDDEN INPUT -->
                    <input type="file" id="imageInput" name="image" style="display:none;" onchange="showFileName(this)">

                    <!--  FILE NAME DISPLAY -->
                    <div id="fileName" style="margin-top:10px; font-size:0.85rem; color:#334155;"></div>

                    <!--  OLD IMAGE -->
                    <?php if (!empty($old['item_image'])): ?>
                        <div style="margin-top:15px;">
                            <img src="../uploads/inventory/<?= $old['item_image'] ?>" width="120"
                                style="border-radius:6px;">
                            <div style="font-size: 0.8rem; color:#64748b;">
                                <?= $old['item_image'] ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (isset($errors['image']))
                    echo "<div style='color:red; font-size:0.9rem; margin-top:0.5rem;'>{$errors['image']}</div>"; ?>

                <!-- STATUS -->
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="1" <?= ($old['status'] ?? '') == '1' ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= ($old['status'] ?? '') == '0' ? 'selected' : '' ?>>Archived/Discontinued
                        </option>
                    </select>
                    <?php if (isset($errors['status']))
                        echo "<div style='color:red; font-size:0.9rem; margin-top:0.5rem;'>{$errors['status']}</div>"; ?>
                </div>
            </div>
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Supplier Info</h3>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Supplier Name</label>
                    <input type="text" id="supplierNameInput" name="supplier_name" maxlength="100" class="form-control"
                        placeholder="e.g. RK Textiles" list="supplierList" value="<?= $old['supplier_name'] ?? '' ?>">
                    <?php if (isset($errors['supplier_name']))
                        echo "<div style='color:red; font-size:0.9rem; margin-top:0.5rem;'>{$errors['supplier_name']}</div>"; ?>
                </div>
                <div class="form-group">
                    <label class="form-label">Contact</label>
                    <input type="text" id="supplierContactInput" name="contact" maxlength="10" class="form-control"
                        placeholder="Phone or Email" value="<?= $old['supplier_contact'] ?? '' ?>">
                    <?php if (isset($errors['contact']))
                        echo "<div style='color:red; font-size:0.9rem; margin-top:0.5rem;'>{$errors['contact']}</div>"; ?>
                </div>

                <!-- Unique Suppliers Datalist -->
                <datalist id="supplierList">
                    <?php foreach ($unique_suppliers as $supp): ?>
                        <option value="<?= htmlspecialchars($supp['supplier_name']) ?>"
                            data-contact="<?= htmlspecialchars($supp['supplier_contact'] ?? '') ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>

            <!-- Actions -->
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Actions</h3>
                <button type="submit" class="btn btn-primary w-full"
                    style="justify-content: center; width: 100%; margin-bottom: 1rem;">Save Item</button>
                <button type="button" class="btn w-full"
                    style="justify-content: center; width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b;">Cancel</button>
            </div>

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
        font-weight: 500;
        color: #334155;
        transition: opacity 0.15s ease-in-out, transform 0.15s ease-in-out;
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
        border-color: var(--primary);
    }
</style>
<script>
    function showFileName(input) {
        if (input.files.length > 0) {
            document.getElementById("fileName").innerText = input.files[0].name;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const unitSelect = document.getElementById('unitSelect');
        const costLabel = document.getElementById('costLabel');
        const quantityLabel = document.getElementById('quantityLabel');
        const quantityInput = document.getElementById('quantityInput');
        const lowStockLabel = document.getElementById('lowStockLabel');
        const lowStockInput = document.getElementById('lowStockInput');

        const unitMap = {
            'meters': {
                cost: 'Cost per Meter (₹) <span style="color:red;">*</span>',
                qtyLabel: 'Initial Quantity (Meters)',
                qtyPlaceholder: 'e.g. 10.0',
                lowLabel: 'Low Stock Alert Level (Meters)',
                lowPlaceholder: 'e.g. 5'
            },
            'pieces': {
                cost: 'Cost per Piece (₹)',
                qtyLabel: 'Initial Quantity (Pieces)',
                qtyPlaceholder: 'e.g. 10',
                lowLabel: 'Low Stock Alert Level (Pieces)',
                lowPlaceholder: 'e.g. 5'
            },
            'rolls': {
                cost: 'Cost per Roll (₹)',
                qtyLabel: 'Initial Quantity (Rolls)',
                qtyPlaceholder: 'e.g. 2',
                lowLabel: 'Low Stock Alert Level (Rolls)',
                lowPlaceholder: 'e.g. 1'
            },
            'boxes': {
                cost: 'Cost per Box (₹)',
                qtyLabel: 'Initial Quantity (Boxes)',
                qtyPlaceholder: 'e.g. 5',
                lowLabel: 'Low Stock Alert Level (Boxes)',
                lowPlaceholder: 'e.g. 2'
            }
        };

        function updateFields(unitKey, animate = true) {
            const config = unitMap[unitKey] || {
                cost: 'Cost per Unit (₹)',
                qtyLabel: 'Initial Quantity',
                qtyPlaceholder: '0.0',
                lowLabel: 'Low Stock Alert Level',
                lowPlaceholder: 'e.g. 5'
            };

            const targets = [
                { el: costLabel, text: config.cost },
                { el: quantityLabel, text: config.qtyLabel },
                { el: lowStockLabel, text: config.lowLabel }
            ];

            if (animate) {
                // Apply fade-out and slide-up transition
                targets.forEach(t => {
                    if (t.el) {
                        t.el.style.opacity = '0';
                        t.el.style.transform = 'translateY(-2px)';
                    }
                });

                // Update text and apply fade-in
                setTimeout(() => {
                    targets.forEach(t => {
                        if (t.el) {
                            t.el.innerHTML = t.text;
                            t.el.style.opacity = '1';
                            t.el.style.transform = 'translateY(0)';
                        }
                    });
                    if (quantityInput) quantityInput.placeholder = config.qtyPlaceholder;
                    if (lowStockInput) lowStockInput.placeholder = config.lowPlaceholder;
                }, 150);
            } else {
                // No animation on initial page load to prevent a visible flash
                targets.forEach(t => {
                    if (t.el) {
                        t.el.innerHTML = t.text;
                        t.el.style.opacity = '1';
                        t.el.style.transform = 'translateY(0)';
                    }
                });
                if (quantityInput) quantityInput.placeholder = config.qtyPlaceholder;
                if (lowStockInput) lowStockInput.placeholder = config.lowPlaceholder;
            }
        }

        if (unitSelect) {
            // Listen for Unit of Measure selection changes
            unitSelect.addEventListener('change', function () {
                updateFields(this.value, true);
            });

            // Perform initial setup matching current/saved value
            updateFields(unitSelect.value, false);
        }

        // Supplier autocomplete auto-fill handler
        const supplierNameInput = document.getElementById('supplierNameInput');
        const supplierContactInput = document.getElementById('supplierContactInput');
        const supplierList = document.getElementById('supplierList');

        if (supplierNameInput && supplierContactInput && supplierList) {
            const handleSupplierAutoFill = function () {
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