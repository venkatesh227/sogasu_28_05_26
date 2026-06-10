<?php
session_start();
include '../includes/db.php';

$errors = [];
$old = [];
$id = $_GET['id'] ?? null;

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ? AND is_deleted = 0");
    $stmt->execute([$id]);
    $supplier = $stmt->fetch();

    if (!$supplier) {
        die("Supplier not found");
    }

    $old = $supplier;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // SANITIZE
    $old['supplier_name'] = trim($_POST['supplier_name'] ?? '');
    $old['firm_name'] = trim($_POST['firm_name'] ?? '');
    $old['contact_person'] = trim($_POST['contact_person'] ?? '');
$old['phone_no'] = trim($_POST['phone_no'] ?? '');
$old['alternate_no'] = trim($_POST['alternate_no'] ?? '');

$old['address'] = trim($_POST['address'] ?? '');
$old['city'] = trim($_POST['city'] ?? '');    $old['gst_no'] = trim($_POST['gst_no'] ?? '');
    $old['bank_name'] = trim($_POST['bank_name'] ?? '');
    $old['account_no'] = trim($_POST['account_no'] ?? '');
    $old['ifsc_code'] = trim($_POST['ifsc_code'] ?? '');
    $old['bank_branch'] = trim($_POST['bank_branch'] ?? '');
    $old['status'] = $_POST['status'] ?? 'active';

    // ===== VALIDATIONS =====
    if ($old['supplier_name'] == '') {
        $errors['supplier_name'] = "Supplier name required";
    } elseif (strlen($old['supplier_name']) > 100) {
        $errors['supplier_name'] = "Max 100 characters allowed";
    }

    if (strlen($old['firm_name']) > 150) {
        $errors['firm_name'] = "Max 150 characters allowed";
    }

    if (strlen($old['contact_person']) > 100) {
        $errors['contact_person'] = "Max 100 characters allowed";
    }

    if (strlen($old['phone_no']) > 20) {
        $errors['phone_no'] = "Max 20 characters allowed";
    }

    if (strlen($old['gst_no']) > 20) {
        $errors['gst_no'] = "Max 20 characters allowed";
    }

    if (strlen($old['bank_name']) > 100) {
        $errors['bank_name'] = "Max 100 characters allowed";
    }

    if (strlen($old['account_no']) > 50) {
        $errors['account_no'] = "Max 50 characters allowed";
    }

    if (strlen($old['ifsc_code']) > 20) {
        $errors['ifsc_code'] = "Max 20 characters allowed";
    }

    if (strlen($old['bank_branch']) > 100) {
        $errors['bank_branch'] = "Max 100 characters allowed";
    }

    $validStatus = ['active', 'inactive'];
    if (!in_array($old['status'], $validStatus, true)) {
        $errors['status'] = "Invalid status";
    }

    // ===== INSERT / UPDATE =====
    if (empty($errors)) {
        // Build traditional contact field as phone_no for backward-compatibility auto-fill datalists
        $contact = $old['phone_no'];

        if ($id) {
            // UPDATE
            $stmt = $pdo->prepare("
                UPDATE suppliers SET 
                supplier_name=?, firm_name=?, contact_person=?, phone_no=?,alternate_no=?, address=?, city=?, gst_no=?, bank_name=?, account_no=?, ifsc_code=?, bank_branch=?, contact=?, status=? 
                WHERE id=?
            ");
            $stmt->execute([
                $old['supplier_name'],
                $old['firm_name'],
                $old['contact_person'],
                $old['phone_no'],
                $old['alternate_no'],
                $old['address'],
                $old['city'],
                $old['gst_no'],
                $old['bank_name'],
                $old['account_no'],
                $old['ifsc_code'],
                $old['bank_branch'],
                $contact,
                $old['status'],
                $id
            ]);
            $_SESSION['success'] = "updated";
        } else {
            // INSERT
$stmt = $pdo->prepare("
    INSERT INTO suppliers
    (supplier_name, firm_name, contact_person, phone_no, alternate_no, address, city, gst_no, bank_name, account_no, ifsc_code, bank_branch, contact, status, is_deleted)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
");
            $stmt->execute([
                $old['supplier_name'],
                $old['firm_name'],
                $old['contact_person'],
                $old['phone_no'],
                $old['alternate_no'],
                $old['address'],
                $old['city'],
                $old['gst_no'],
                $old['bank_name'],
                $old['account_no'],
                $old['ifsc_code'],
                $old['bank_branch'],
                $contact,
                $old['status']
            ]);
            $_SESSION['success'] = "added";
        }

        header("Location: suppliers.php");
        exit;
    }
}

$pageTitle = ($id ? "Edit" : "Add New") . " Supplier - Sogasu";
$activePage = "suppliers";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="margin-bottom: 1.5rem;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;"><?= $id ? 'Edit' : 'Add New' ?> Supplier</h2>
                <p style="color: #64748b; margin-top: 0.25rem;"><?= $id ? 'Update existing supplier details and bank configuration' : 'Register a new stock supplier with firm and bank info' ?></p>
            </div>
            <button type="button" class="btn" onclick="history.back()" style="background: white; border: 1px solid #e2e8f0; color: #64748b; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 0.25rem;">
                <i class="ri-arrow-left-line"></i> Back
            </button>
        </div>
    </div>

<form method="POST" novalidate style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
            <!-- Left Column: Details -->
        <div style="display: flex; flex-direction: column; gap: 1.25rem;">
            
            <!-- Firm & Contact Information -->
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="ri-building-line" style="color: #4f46e5;"></i> Firm & Contact Info
                </h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Supplier/Vendor Name <span style="color:red">*</span></label>
                        <input type="text" name="supplier_name" class="form-control" placeholder="e.g. RK Textiles" maxlength="100"  value="<?= htmlspecialchars($old['supplier_name'] ?? '') ?>">
                        <?php if (isset($errors['supplier_name'])): ?>
                            <small style="color:red; margin-top: 0.25rem; display: block;"><?= $errors['supplier_name'] ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Firm Name</label>
                        <input type="text" name="firm_name" class="form-control" placeholder="e.g. RK Textiles Private Limited" maxlength="150" value="<?= htmlspecialchars($old['firm_name'] ?? '') ?>">
                        <?php if (isset($errors['firm_name'])): ?>
                            <small style="color:red; margin-top: 0.25rem; display: block;"><?= $errors['firm_name'] ?></small>
                        <?php endif; ?>
                    </div>
                </div>

<div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div class="form-group">
                        <label class="form-label">Contact Person</label>
                        <input type="text" name="contact_person" class="form-control" placeholder="e.g. Rajesh Kumar" maxlength="100" value="<?= htmlspecialchars($old['contact_person'] ?? '') ?>">
                        <?php if (isset($errors['contact_person'])): ?>
                            <small style="color:red; margin-top: 0.25rem; display: block;"><?= $errors['contact_person'] ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone No</label>
                        <input type="text" name="phone_no" class="form-control" placeholder="Phone Number" maxlength="20" value="<?= htmlspecialchars($old['phone_no'] ?? '') ?>">
                        <?php if (isset($errors['phone_no'])): ?>
                            <small style="color:red; margin-top: 0.25rem; display: block;"><?= $errors['phone_no'] ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
    <label class="form-label">Alternate No</label>
    <input type="text"
           name="alternate_no"
           class="form-control"
           placeholder="Alternate Number"
           maxlength="20"
           value="<?= htmlspecialchars($old['alternate_no'] ?? '') ?>">
</div>
                    <div class="form-group">
                        <label class="form-label">GST No</label>
                        <input type="text" name="gst_no" class="form-control" placeholder="GSTIN Format" maxlength="20" value="<?= htmlspecialchars($old['gst_no'] ?? '') ?>">
                        <?php if (isset($errors['gst_no'])): ?>
                            <small style="color:red; margin-top: 0.25rem; display: block;"><?= $errors['gst_no'] ?></small>
                        <?php endif; ?>
                    </div>
                </div>

          <div style="display:grid; grid-template-columns:2fr 1fr; gap:1rem;">
    
    <div class="form-group">
        <label class="form-label">Address</label>
        <textarea name="address"
                  class="form-control"
                  rows="3"
                  placeholder="Registered office / factory address"
                  style="resize: vertical;"><?= htmlspecialchars($old['address'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
        <label class="form-label">City</label>
        <input type="text"
               name="city"
               class="form-control"
               placeholder="City"
               maxlength="100"
               value="<?= htmlspecialchars($old['city'] ?? '') ?>">
    </div>
          </div>
</div>

            <!-- Bank Account Details -->
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="ri-bank-card-line" style="color: #10b981;"></i> Bank Account Details
                </h3>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Name of the Bank</label>
                        <input type="text" name="bank_name" class="form-control" placeholder="e.g. State Bank of India" maxlength="100" value="<?= htmlspecialchars($old['bank_name'] ?? '') ?>">
                        <?php if (isset($errors['bank_name'])): ?>
                            <small style="color:red; margin-top: 0.25rem; display: block;"><?= $errors['bank_name'] ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Account No</label>
                        <input type="text" name="account_no" class="form-control" placeholder="Bank Account Number" maxlength="50" value="<?= htmlspecialchars($old['account_no'] ?? '') ?>">
                        <?php if (isset($errors['account_no'])): ?>
                            <small style="color:red; margin-top: 0.25rem; display: block;"><?= $errors['account_no'] ?></small>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">IFSC Code</label>
                        <input type="text" name="ifsc_code" class="form-control" placeholder="11-digit IFSC" maxlength="20" value="<?= htmlspecialchars($old['ifsc_code'] ?? '') ?>">
                        <?php if (isset($errors['ifsc_code'])): ?>
                            <small style="color:red; margin-top: 0.25rem; display: block;"><?= $errors['ifsc_code'] ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Branch</label>
                        <input type="text" name="bank_branch" class="form-control" placeholder="Bank Branch Name" maxlength="100" value="<?= htmlspecialchars($old['bank_branch'] ?? '') ?>">
                        <?php if (isset($errors['bank_branch'])): ?>
                            <small style="color:red; margin-top: 0.25rem; display: block;"><?= $errors['bank_branch'] ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Settings & Actions -->
        <div style="display: flex; flex-direction: column; gap: 1.25rem;">
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1.25rem;">Configuration</h3>
                
                <div class="form-group">
                    <label class="form-label" style="font-weight: 600; color: #334155;">Status</label>
                    <select name="status" class="form-select" style="padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem; width: 100%;">
                        <option value="active" <?= ($old['status'] ?? '') == 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($old['status'] ?? '') == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1.25rem;">Actions</h3>
                <button type="submit" class="btn btn-primary" style="background: #4f46e5; border: none; padding: 12px; border-radius: 6px; font-weight: 600; color: white; width: 100%; cursor: pointer; margin-bottom: 0.75rem; display: flex; align-items: center; justify-content: center; gap: 0.25rem;">
                    <i class="ri-save-3-line"></i> <?= $id ? 'Update Supplier' : 'Save Supplier' ?>
                </button>
                <button type="button" class="btn" onclick="window.location.href='suppliers.php'" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 6px; font-weight: 600; color: #64748b; width: 100%; cursor: pointer;">
                    Cancel
                </button>
            </div>
        </div>
    </form>
</main>

<style>
    .form-group { display: flex; flex-direction: column; gap: 0.4rem; }
    .form-label { font-size: 0.85rem; font-weight: 600; color: #475569; }
    .form-control, .form-select {
        padding: 0.7rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.92rem; width: 100%; outline: none; transition: border-color 0.2s; font-family: inherit;
    }
    .form-control:focus, .form-select:focus { border-color: #4f46e5 !important; box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1); }
</style>

<?php include 'includes/footer.php'; ?>
