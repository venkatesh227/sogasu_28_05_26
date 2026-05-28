<?php
session_start();
include '../includes/db.php';

$errors = [];

if (isset($_POST['submit'])) {
    $service_name   = trim($_POST['service_name'] ?? '');
    $category_id    = $_POST['category_id'] ?? '';
    $description    = trim($_POST['description'] ?? '');
    $base_price     = $_POST['base_price'] ?? '';
    $price_type     = $_POST['price_type'] ?? '';
    $estimated_time = trim($_POST['estimated_time'] ?? '');
    $status         = $_POST['status'] ?? 'active';

    if (empty($service_name)) $errors['service_name'] = "Service name is required";
    if (empty($category_id)) $errors['category_id'] = "Category is required";
    if ($base_price === '') $errors['base_price'] = "Base price is required";

    if (empty($errors)) {
        $user_id = $_SESSION['user_id'] ?? 1;
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare("UPDATE services SET service_name=?, category_id=?, description=?, base_price=?, price_type=?, estimated_time=?, status=? WHERE id=?");
            $stmt->execute([$service_name, $category_id, $description, $base_price, $price_type, $estimated_time, $status, $_GET['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO services (service_name, category_id, description, base_price, price_type, estimated_time, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$service_name, $category_id, $description, $base_price, $price_type, $estimated_time, $status, $user_id]);
        }
        
        header("Location: services-pricing.php?created=1");
        exit;
    }
}

$id = $_GET['id'] ?? null;
if ($id && !isset($_POST['submit'])) {
    $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
    $stmt->execute([$id]);
    $service_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($service_data) {
        $_POST = $service_data;
    }
}

$pageTitle = (isset($_GET['id']) ? "Edit" : "Add New") . " Service - Sogasu";
$activePage = "services";

$stmt = $pdo->query("SELECT id, category_name FROM categories WHERE status='active' AND is_deleted = 0");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>
<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div >
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                 <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;"><?= isset($_GET['id']) ? 'Edit Service' : 'Add New Service' ?></h2>
                 <p class="text-muted"><?= isset($_GET['id']) ? 'Update existing service details.' : 'Register a new tailoring service and price.' ?></p>
            </div>
            <button class="btn" onclick="history.back()" style="background: white; border: 1px solid #e2e8f0; color: #64748b;">
                <i class="ri-arrow-left-line"></i> Back
            </button>
        </div>
    </div>

    <form method="POST" style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
        
        <!-- Left Column: Service Details -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Service Information</h3>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Service Name</label>
                    <input type="text" name="service_name" class="form-control" placeholder="e.g. Katori Blouse Stitching" value="<?= htmlspecialchars($_POST['service_name'] ?? '') ?>">
                    <span style="color:red; font-size: 0.8rem;"><?= $errors['service_name'] ?? '' ?></span>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select">
                        <option value="">Select Category</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= (($_POST['category_id'] ?? '') == $cat['id']) ? 'selected' : '' ?>><?= $cat['category_name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span style="color:red; font-size: 0.8rem;"><?= $errors['category_id'] ?? '' ?></span>
                </div>

                <div class="form-group">
                    <label class="form-label">Description / Includes</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Describe what is included in this service..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>
            </div>

            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Pricing Details</h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Base Price</label>
                         <div style="position: relative;">
                             <span style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #64748b;">₹</span>
                            <input type="number" step="0.01" name="base_price" class="form-control" style="padding-left: 2rem;" placeholder="0.00" value="<?= htmlspecialchars($_POST['base_price'] ?? '') ?>">
                        </div>
                        <span style="color:red; font-size: 0.8rem;"><?= $errors['base_price'] ?? '' ?></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Price Type</label>
                        <select name="price_type" class="form-select">
                            <option value="fixed" <?= (($_POST['price_type'] ?? '') == 'fixed') ? 'selected' : '' ?>>Fixed Price</option>
                            <option value="starting" <?= (($_POST['price_type'] ?? '') == 'starting') ? 'selected' : '' ?>>Starting From</option>
                            <option value="variable" <?= (($_POST['price_type'] ?? '') == 'variable') ? 'selected' : '' ?>>Variable / Hourly</option>
                        </select>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column: Settings & Actions -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            
             <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Settings</h3>
                
                 <div class="form-group" style="margin-bottom: 1rem;">
                     <label class="form-label">Estimated Time</label>
                     <input type="text" name="estimated_time" class="form-control" placeholder="e.g. 2 Days" value="<?= htmlspecialchars($_POST['estimated_time'] ?? '') ?>">
                </div>

                 <div class="form-group">
                     <label class="form-label">Status</label>
                     <select name="status" class="form-select">
                        <option value="active" <?= (($_POST['status'] ?? '') == 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= (($_POST['status'] ?? '') == 'inactive') ? 'selected' : '' ?>>Inactive</option>
                     </select>
                </div>
            </div>

             <!-- Actions -->
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                 <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Actions</h3>
                 <button type="submit" name="submit" class="btn btn-primary w-full" style="justify-content: center; width: 100%; margin-bottom: 1rem;"><?= isset($_GET['id']) ? 'Update Service' : 'Save Service' ?></button>
                 <button type="button" class="btn w-full" style="justify-content: center; width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b;">Cancel</button>
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
    }
    .form-control, .form-select {
        padding: 0.75rem;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 0.95rem;
        width: 100%;
        outline: none;
        transition: border-color 0.2s;
        font-family: inherit;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--primary);
    }
</style>

<?php include 'includes/footer.php'; ?>
