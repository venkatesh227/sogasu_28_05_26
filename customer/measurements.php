<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: login.php');
    exit();
}
$categoryId = $_GET['category_id'] ?? '';
$subCategoryId = $_GET['sub_category_id'] ?? '';

$userId = $_SESSION['user_id'];
$selectedCategoryId = $_GET['category_id'] ?? '';
$selectedSubCategoryId = $_GET['subcategory_id'] ?? '';
$pageTitle = "Enter Measurements - Sogasu";
$headerTitle = "Measurements";
$activePage = "new-order";

// Fetch categories and sub-categories
$categories = $pdo->query("SELECT id, category_name FROM categories WHERE status='active' AND is_deleted = 0 ORDER BY category_name")->fetchAll();

$savedData = [];
<<<<<<< Updated upstream

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $formData = [
        'category_id' => $_POST['category_id'] ?? '',
        'sub_category_id' => $_POST['sub_category_id'] ?? '',
        'full_length' => $_POST['full_length'] ?? '',
        'shoulder' => $_POST['shoulder'] ?? '',
        'chest_bust' => $_POST['chest_bust'] ?? '',
        'waist' => $_POST['waist'] ?? '',
        'hips' => $_POST['hips'] ?? '',
        'sleeve_length' => $_POST['sleeve_length'] ?? '',
        'arm_hole' => $_POST['arm_hole'] ?? '',
        'neck_depth_f' => $_POST['neck_depth_f'] ?? '',
        'neck_depth_b' => $_POST['neck_depth_b'] ?? '',
        'bottom_pant_length' => $_POST['bottom_pant_length'] ?? '',
        'additional_notes' => $_POST['additional_notes'] ?? '',
    ];

    $stmt = $pdo->prepare("
    SELECT id, data
    FROM customer_profiles
    WHERE user_id = ?
    AND section_type = 'measurements'
");

    $stmt->execute([$userId]);

    $profiles = $stmt->fetchAll();

    $existingId = null;
    $existingData = null;

    foreach ($profiles as $profile) {

        $decodedData = json_decode($profile['data'], true);

        if (
            ($decodedData['category_id'] ?? '') == $formData['category_id']
            &&
            ($decodedData['sub_category_id'] ?? '') == $formData['sub_category_id']
        ) {

            $existingId = $profile['id'];
            $existingData = $decodedData;
            break;
        }
    }

    // UPDATE EXISTING RECORD
    if ($existingId) {

        foreach ($formData as $key => $value) {

            // preserve old values if field empty
            if ($value === '' && isset($existingData[$key])) {
                $formData[$key] = $existingData[$key];
            }
        }

        $stmt = $pdo->prepare("
        UPDATE customer_profiles
        SET data = ?, updated_at = NOW()
        WHERE id = ?
    ");

        if (
            !$stmt->execute([
                json_encode($formData),
                $existingId
            ])
        ) {

            echo json_encode([
                'success' => false,
                'message' => 'Update failed'
            ]);

            exit();
        }

    } else {

        // INSERT NEW RECORD

        $stmt = $pdo->prepare("
        INSERT INTO customer_profiles
        (user_id, section_type, data)
        VALUES (?, 'measurements', ?)
    ");

        if (
            !$stmt->execute([
                $userId,
                json_encode($formData)
            ])
        ) {

            echo json_encode([
                'success' => false,
                'message' => 'Insert failed'
            ]);

            exit();
        }
    }

    header('Content-Type: application/json');
    $responseMessage = $existingId
        ? 'Measurements updated successfully!'
        : 'Measurements saved successfully!';

    $responseTitle = $existingId
        ? 'Updated!'
        : 'Saved!';

    echo json_encode([
        'success' => true,
        'title' => $responseTitle,
        'message' => $responseMessage
    ]);

    exit();
}
// FETCH MEASUREMENTS BASED ON SUB CATEGORY
if (
    $_SERVER['REQUEST_METHOD'] === 'GET'
    &&
    isset($_GET['subcategory_id'])
    &&
    isset($_GET['ajax'])
) {

    header('Content-Type: application/json');

    $subCategoryId = $_GET['subcategory_id'];

    $stmt = $pdo->prepare("
    SELECT id, data
    FROM customer_profiles
    WHERE user_id = ?
    AND section_type = 'measurements'
");

    $stmt->execute([$userId]);

    $profiles = $stmt->fetchAll();

    $found = false;

    foreach ($profiles as $profile) {

        $data = json_decode($profile['data'], true);

        if (
            ($data['category_id'] ?? '') == ($_GET['category_id'] ?? '')
            &&
            ($data['sub_category_id'] ?? '') == $subCategoryId
        ) {

            echo json_encode([
                'success' => true,
                'measurements' => $data
            ]);

            $found = true;
            break;
        }
    }

    if (!$found) {

        echo json_encode([
            'success' => false
        ]);
    }

=======
$stmt = $pdo->prepare("SELECT data FROM customer_profiles WHERE user_id = ? AND section_type = 'measurements'");
$stmt->execute([$userId]);
$result = $stmt->fetch();
if ($result) {
    $savedData = json_decode($result['data'], true) ?? [];
}
$subs = [];

if ($categoryId) {
    $stmt = $pdo->prepare("SELECT id, name FROM sub_categories 
        WHERE category_id = ? AND status='active' AND is_deleted=0");
    $stmt->execute([$categoryId]);
    $subs = $stmt->fetchAll();
}
$measurementFields = [];
$sessionOrder = $_SESSION['order'] ?? null;

if ($sessionOrder) {
    $subCategoryId = $sessionOrder['sub_category_id'];
}

if ($subCategoryId) {
    $stmt = $pdo->prepare("
        SELECT mk.key_name, mk.key_name AS label, mk.input_type
        FROM measurement_mapping mc
        JOIN measurement_keys mk ON mc.key_id = mk.id
        WHERE mc.sub_category_id = ?
    ");

    $stmt->execute([$subCategoryId]);
    $measurementFields = $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $category_id = $_POST['category_id'] ?? '';
    $sub_category_id = $_POST['sub_category_id'] ?? '';
    $formData = [];

    foreach ($measurementFields as $field) {
        if ($field['input_type'] == 'checkbox') {
            $formData[$field['key_name']] = isset($_POST[$field['key_name']]) ? 1 : 0;
        } else {
            $formData[$field['key_name']] = $_POST[$field['key_name']] ?? '';
        }
    }

    $additional_notes = $_POST['additional_notes'] ?? '';
    $deliveryType = $_POST['delivery_type'] ?? 'normal';

    // IMAGE UPLOAD
    function uploadImage($file)
    {
        if ($file['name'] == '')
            return null;

        $dir = "uploads/";
        if (!is_dir($dir))
            mkdir($dir, 0777, true);

        $name = time() . "_" . basename($file['name']);
        move_uploaded_file($file['tmp_name'], $dir . $name);

        return $name;
    }

    $material_image = uploadImage($_FILES['material_image']);
    $referral_image = uploadImage($_FILES['referral_image']);

    // STEP 1: current year
    $year = date('Y');

    // STEP 2: last order fetch
    $stmt = $pdo->prepare("SELECT order_code FROM customer_orders WHERE order_code LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute(["ORD-$year-%"]);
    $lastOrder = $stmt->fetch();

    // STEP 3: next number generate
    if ($lastOrder && !empty($lastOrder['order_code'])) {
        $lastNumber = (int) substr($lastOrder['order_code'], -3);
        $nextNumber = $lastNumber + 1;
    } else {
        $nextNumber = 1;
    }

    // STEP 4: format (001, 002...)
    $orderCode = "ORD-$year-" . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    // ✅ SAVE INTO customer_measurements TABLE
    $stmt = $pdo->prepare("
    INSERT INTO customer_measurements 
    (user_id, category_id, sub_category_id, measurements, created_by)
    VALUES (?, ?, ?, ?, ?)
");

    $stmt->execute([
        $_SESSION['user_id'],
        $sessionOrder['category_id'],
        $sessionOrder['sub_category_id'],
        json_encode($formData),
        $_SESSION['user_id']
    ]);

    $measurementId = $pdo->lastInsertId();
    // INSERT INTO ORDERS TABLE
    $stmt = $pdo->prepare("
        INSERT INTO customer_orders 
        (order_code, user_id, category_id, sub_category_id, visit_type, appointment_date, appointment_time, customer_measurement_id, delivery_type, additional_notes, material_image, referral_image, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $orderCode,
        $_SESSION['user_id'],
        $sessionOrder['category_id'],
        $sessionOrder['sub_category_id'],
        $sessionOrder['visit_type'],
        $sessionOrder['date'],
        $sessionOrder['time'],
        $measurementId,
        $deliveryType,
        $additional_notes,
        $material_image,
        $referral_image,
        $_SESSION['user_id']
    ]);

    $orderId = $pdo->lastInsertId();

    // CLEAR SESSION
    unset($_SESSION['order']);

    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully',
        'order_id' => $orderId
    ]);

>>>>>>> Stashed changes
    exit();
}

include 'includes/header.php';
?>

<div class="container">

    <div class="card" style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
<<<<<<< Updated upstream
        <a href="profile.php"
=======
        <a href="new-order.php"
>>>>>>> Stashed changes
            style="background: var(--background); border: none; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary); text-decoration: none; font-size: 1.2rem;">
            <i class="ri-arrow-left-line"></i>
        </a>
        <div>
            <h2 style="font-size: 1.3rem; font-weight: 700; color: var(--text-main);"> Measurements</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Store your body measurements for tailoring</p>
        </div>
    </div>

    <div class="card">
<<<<<<< Updated upstream
        <form id="measurementsForm" method="POST">
            <input type="hidden" name="measurement_exists" id="measurementExists" value="0">
            <!-- Category Selection Section -->
            <div style="margin-bottom: 2rem;">
                <h3
                    style="font-size: 1rem; font-weight: 600; color: var(--text-main); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; border-bottom: 2px solid var(--primary); padding-bottom: 0.75rem;">
                    <i class="ri-shirt-line"></i> Select Category & Sub-Category
                </h3>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="input-label">Category</label>
                        <select name="category_id" id="categorySelect" class="form-input" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php
                                   echo (
                                       $selectedCategoryId == $category['id']
                                       ||
                                       ($savedData['category_id'] ?? '') == $category['id']
                                   )
                                       ? 'selected'
                                       : '';
                                   ?>>
                                    <?php echo htmlspecialchars($category['category_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="input-label">Sub-Category</label>
                        <select name="sub_category_id" id="subCategorySelect" class="form-input" required>
                            <option value="">Select Sub-Category</option>
                            <?php if (!empty($selectedCategoryId) || !empty($savedData['category_id'])): ?>
                                <?php
                                $subCategories = $pdo->prepare("SELECT id, name FROM sub_categories WHERE category_id = ? AND status = 'active' AND is_deleted = 0 ORDER BY name");
                                $subCategories->execute([
                                    $selectedCategoryId ?: $savedData['category_id']
                                ]);
                                $subs = $subCategories->fetchAll();
                                foreach ($subs as $sub):
                                    ?>
                                    <option value="<?php echo $sub['id']; ?>" <?php
                                       echo (
                                           $selectedSubCategoryId == $sub['id']
                                           ||
                                           ($savedData['sub_category_id'] ?? '') == $sub['id']
                                       )
                                           ? 'selected'
                                           : '';
                                       ?>>
                                        <?php echo htmlspecialchars($sub['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
            </div>
=======
        <!-- Category Selection Section -->
        <div style="margin-bottom: 2rem;">
            <h3
                style="font-size: 1rem; font-weight: 600; color: var(--text-main); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; border-bottom: 2px solid var(--primary); padding-bottom: 0.75rem;">
                <i class="ri-shirt-line"></i> Select Category & Sub-Category
            </h3>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div>
                    <label class="input-label">Category</label>
                    <select class="form-input" disabled>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo ($categoryId == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="input-label">Sub-Category</label>
                    <select class="form-input" disabled>
                        <option value="">Select Sub-Category</option>
                        <?php foreach ($subs as $sub): ?>
                            <option value="<?php echo $sub['id']; ?>" <?php echo ($subCategoryId == $sub['id']) ? 'selected' : ''; ?>>
                                <?php echo $sub['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="section-title">
            <span>Body Measurements</span>
            <span style="font-size: 0.8rem; font-weight: 400; color: var(--text-muted);">(in inches)</span>
        </div>

        <div id="globalError" class="error" style="margin-bottom:10px;"></div>
        <form id="measurementsForm" method="POST" enctype="multipart/form-data" novalidate onsubmit="return false;">
            <input type="hidden" name="category_id" value="<?php echo $categoryId; ?>">
            <input type="hidden" name="sub_category_id" value="<?php echo $subCategoryId; ?>">
>>>>>>> Stashed changes

            <div class="section-title">
                <span>Body Measurements</span>
                <span style="font-size: 0.8rem; font-weight: 400; color: var(--text-muted);">(in inches)</span>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem;">
<<<<<<< Updated upstream

                <div>
                    <label class="input-label">Full Length</label>
                    <input type="number" name="full_length" class="form-input" placeholder="0.0"
                        value="<?php echo htmlspecialchars($savedData['full_length'] ?? ''); ?>" step="0.1">
                </div>
                <div>
                    <label class="input-label">Shoulder</label>
                    <input type="number" name="shoulder" class="form-input" placeholder="0.0"
                        value="<?php echo htmlspecialchars($savedData['shoulder'] ?? ''); ?>" step="0.1">
                </div>

                <div>
                    <label class="input-label">Chest / Bust</label>
                    <input type="number" name="chest_bust" class="form-input" placeholder="0.0"
                        value="<?php echo htmlspecialchars($savedData['chest_bust'] ?? ''); ?>" step="0.1">
                </div>
                <div>
                    <label class="input-label">Waist</label>
                    <input type="number" name="waist" class="form-input" placeholder="0.0"
                        value="<?php echo htmlspecialchars($savedData['waist'] ?? ''); ?>" step="0.1">
                </div>

                <div>
                    <label class="input-label">Hips</label>
                    <input type="number" name="hips" class="form-input" placeholder="0.0"
                        value="<?php echo htmlspecialchars($savedData['hips'] ?? ''); ?>" step="0.1">
                </div>
                <div>
                    <label class="input-label">Sleeve Length</label>
                    <input type="number" name="sleeve_length" class="form-input" placeholder="0.0"
                        value="<?php echo htmlspecialchars($savedData['sleeve_length'] ?? ''); ?>" step="0.1">
                </div>

                <div>
                    <label class="input-label">Arm Hole</label>
                    <input type="number" name="arm_hole" class="form-input" placeholder="0.0"
                        value="<?php echo htmlspecialchars($savedData['arm_hole'] ?? ''); ?>" step="0.1">
                </div>
                <div>
                    <label class="input-label">Neck Depth (F)</label>
                    <input type="number" name="neck_depth_f" class="form-input" placeholder="0.0"
                        value="<?php echo htmlspecialchars($savedData['neck_depth_f'] ?? ''); ?>" step="0.1">
                </div>

                <div>
                    <label class="input-label">Neck Depth (B)</label>
                    <input type="number" name="neck_depth_b" class="form-input" placeholder="0.0"
                        value="<?php echo htmlspecialchars($savedData['neck_depth_b'] ?? ''); ?>" step="0.1">
                </div>
                <div>
                    <label class="input-label">Bottom/Pant Length</label>
                    <input type="number" name="bottom_pant_length" class="form-input" placeholder="0.0"
                        value="<?php echo htmlspecialchars($savedData['bottom_pant_length'] ?? ''); ?>" step="0.1">
                </div>

=======
                <?php foreach ($measurementFields as $field): ?>
                    <div>
                        <label class="input-label"><?php echo $field['label']; ?></label>
                        <?php if ($field['input_type'] == 'checkbox'): ?>
                            <input type="checkbox" name="<?php echo $field['key_name']; ?>" value="1" <?php echo (!empty($savedData[$field['key_name']])) ? 'checked' : ''; ?>>
                        <?php elseif ($field['input_type'] == 'select'): ?>
                            <select name="<?php echo $field['key_name']; ?>" class="form-input">
                                <option value="">Select</option>
                                <option value="yes">Yes</option>
                                <option value="no">No</option>
                            </select>
                        <?php else: ?>
                            <input type="number" name="<?php echo $field['key_name']; ?>" class="form-input" step="0.1"
                                value="<?php echo htmlspecialchars($savedData[$field['key_name']] ?? ''); ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
>>>>>>> Stashed changes
            </div>

            <div class="section-title">Additional Notes</div>
            <textarea name="additional_notes" class="form-input" rows="4"
                placeholder="Any specific requirements regarding fit, design or style..."
                style="margin-bottom: 2rem;"><?php echo htmlspecialchars($savedData['additional_notes'] ?? ''); ?></textarea>
<<<<<<< Updated upstream

            <button type="submit" class="btn-primary" style="width: 100%; font-size: 1.1rem; padding: 1rem;">
                <i class="ri-save-line"></i>
                <span id="measurementButtonText">Save Measurements</span>
            </button>
        </form>
=======
            <div id="notesError" class="error"></div>
            <!-- DELIVERY STATUS -->
            <div class="section-title">Delivery Type</div>

            <div style="margin-bottom:2rem;">
                <select name="delivery_type" class="form-input">
                    <option value="">Select Delivery Type</option>
                    <option value="normal">Normal</option>
                    <option value="emergency">Emergency</option>
                </select>
            </div>
            <div id="deliveryError" class="error"></div>
>>>>>>> Stashed changes

            <div style="margin-bottom:2rem;">
                <div class="section-title">Attachments</div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">

                    <!-- Material Image -->
                    <div
                        style="position:relative; border:1px solid var(--border); border-radius:10px; padding:1.5rem 1rem 1rem;">
                        <div
                            style="position:absolute; top:-10px; left:15px; background:#fff; padding:0 8px; font-weight:600; font-size:14px;">
                            Material Image
                        </div>

                        <input type="file" name="material_image" accept="image/*"
                            onchange="previewImage(this, 'materialPreview')">
                        <div style="margin-top:10px;">
                            <img id="materialPreview"
                                src="<?php echo !empty($savedData['material_image']) ? 'uploads/' . $savedData['material_image'] : ''; ?>"
                                style="width:100%; max-height:250px; object-fit:contain; border-radius:8px; background:#f1f5f9; <?php echo empty($savedData['material_image']) ? 'display:none;' : ''; ?>">
                        </div>
                    </div>

                    <!-- Referral Image -->
                    <div
                        style="position:relative; border:1px solid var(--border); border-radius:10px; padding:1.5rem 1rem 1rem;">
                        <div
                            style="position:absolute; top:-10px; left:15px; background:#fff; padding:0 8px; font-weight:600; font-size:14px; ">
                            Referral Image
                        </div>
                        <input type="file" name="referral_image" accept="image/*"
                            onchange="previewImage(this, 'referralPreview')">
                        <div style="margin-top:10px;">
                            <img id="referralPreview"
                                src="<?php echo !empty($savedData['referral_image']) ? 'uploads/' . $savedData['referral_image'] : ''; ?>"
                                style="width:100%; max-height:250px; object-fit:contain; border-radius:8px; background:#f1f5f9; <?php echo empty($savedData['referral_image']) ? 'display:none;' : ''; ?>">
                        </div>
                    </div>
                </div>
            </div>
    </div>
    <button type="button" id="saveMeasurementsBtn" class="btn-primary"
        style="width: 100%; font-size: 1.1rem; padding: 1rem;">
        <i class="ri-save-line"></i> Save Measurements
    </button>
    </form>
</div>
</div>
<style>
    .alert-error {
        background: #fff3cd;
        color: #856404;
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 15px;
        border: 1px solid #ffeeba;
        font-size: 14px;
    }

    input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: var(--primary);
    }

    .input-label {
        font-size: 0.85rem;
        color: var(--text-muted);
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
    }

    .form-input {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 1rem;
        font-family: inherit;
        background: var(--background);
        outline: none;
    }

    .form-input:focus {
        border-color: var(--primary);
        background: white;
    }

    select[name="delivery_type"] option[value="emergency"] {
        color: red;
        font-weight: bold;
    }

    .error {
        color: red;
        font-size: 0.8rem;
        margin-top: 4px;
    }

    .input-error {
        border: 1px solid red !important;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Category and Sub-Category dynamic loading
    document.getElementById('categorySelect').addEventListener('change', function () {
        const categoryId = this.value;
        const subCategorySelect = document.getElementById('subCategorySelect');

        // clear measurements when category changes
        const measurementFields = [
            'full_length',
            'shoulder',
            'chest_bust',
            'waist',
            'hips',
            'sleeve_length',
            'arm_hole',
            'neck_depth_f',
            'neck_depth_b',
            'bottom_pant_length',
            'additional_notes'
        ];

        measurementFields.forEach(field => {
            const el = document.querySelector(`[name="${field}"]`);

            if (el) {
                el.value = '';
            }
        });
        document.getElementById('measurementExists').value = '0';

        document.getElementById('measurementButtonText').innerText =
            'Save Measurements';

        // reset hidden sub category
        if (categoryId) {
            fetch('get-subcategories.php?category_id=' + categoryId)
<<<<<<< Updated upstream
                .then(async response => {

                    const text = await response.text();

                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error(text);
                        throw e;
                    }
                })
                .then(data => {
                    subCategorySelect.innerHTML = '<option value="">Select Sub-Category</option>';
                    data.forEach(sub => {
                        subCategorySelect.innerHTML += `<option value="${sub.id}">${sub.name}</option>`;
                    });
=======
                .then(response => response.text())
                .then(response => response.text())
                .then(result => {

                    console.log(result);

                    let data;

                    try {

                        data = JSON.parse(result);

                    } catch (e) {

                        Swal.fire({
                            icon: 'error',
                            title: 'PHP Error',
                            text: result
                        });

                        return;
                    }

                    if (data.success) {

                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: data.message || 'Order placed successfully'
                        }).then(() => {

                            window.location.href = 'my-orders.php';

                        });

                    } else {

                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Something went wrong'
                        });

                    }

>>>>>>> Stashed changes
                })
                .catch(error => console.error('Error loading sub-categories:', error));
        } else {
            subCategorySelect.innerHTML = '<option value="">Select Sub-Category</option>';
        }
    });

    document.getElementById('subCategorySelect').addEventListener('change', function () {
<<<<<<< Updated upstream

        const subCategoryId = this.value;
        // all measurement fields
        const measurementFields = [
            'full_length',
            'shoulder',
            'chest_bust',
            'waist',
            'hips',
            'sleeve_length',
            'arm_hole',
            'neck_depth_f',
            'neck_depth_b',
            'bottom_pant_length',
            'additional_notes'
        ];

        // if no subcategory selected clear everything
        if (!subCategoryId) {
            measurementFields.forEach(field => {
                const el = document.querySelector(`[name="${field}"]`);
                if (el) el.value = '';
            });
            return;
        }

        const categoryId = document.getElementById('categorySelect').value;

        fetch(
            'measurements.php?ajax=1&category_id=' +
            categoryId +
            '&subcategory_id=' +
            subCategoryId
        )
            .then(response => response.json())
            .then(data => {

                // if measurements exist fill them
                if (data.success && data.measurements) {
                    document.getElementById('measurementExists').value = '1';

                    document.getElementById('measurementButtonText').innerText =
                        'Update Measurements';

                    measurementFields.forEach(field => {
                        const el = document.querySelector(`[name="${field}"]`);

                        if (el) {
                            el.value = data.measurements[field] ?? '';
                        }
                    });

                } else {
                    document.getElementById('measurementExists').value = '0';

                    document.getElementById('measurementButtonText').innerText =
                        'Save Measurements';

                    // if no measurements in DB clear all fields
                    measurementFields.forEach(field => {
                        const el = document.querySelector(`[name="${field}"]`);

                        if (el) {
                            el.value = '';
                        }
                    });

                }

            })
            .catch(error => {
                document.getElementById('measurementExists').value = '0';

                document.getElementById('measurementButtonText').innerText =
                    'Save Measurements';
                console.error('Error fetching measurements:', error);

                // clear fields on error also
                measurementFields.forEach(field => {
                    const el = document.querySelector(`[name="${field}"]`);

                    if (el) {
                        el.value = '';
                    }
                });
            });

    });
    window.addEventListener('load', function () {

        const categoryId =
            document.getElementById('categorySelect').value;

        const subCategoryId =
            document.getElementById('subCategorySelect').value;

        if (categoryId && subCategoryId) {

            document.getElementById('subCategorySelect')
                .dispatchEvent(new Event('change'));
        }
    });

    document.getElementById('measurementsForm')?.addEventListener('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);
        fetch('measurements.php', {
            method: 'POST',
            body: formData
        })
            .then(async response => {

                const text = await response.text();

                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error(text);
                    throw e;
                }
            })
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: data.title,
                        text: data.message,
                        confirmButtonColor: '#db2777'
                    }).then(() => {
                        window.location.href = "profile.php?section=measurements";
                    });
                }
            }).catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Failed to save measurements',
                    confirmButtonColor: '#db2777'
                });
            });
    });
</script>

=======
        document.getElementById('hiddenSubCategoryId').value = this.value;
    });
</script>

<script>

    document.getElementById('saveMeasurementsBtn').onclick = function (e) {

        e.preventDefault();

        const form = document.getElementById('measurementsForm');

        const formData = new FormData(form);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {

                if (data.success) {

                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: data.message
                    });

                } else {

                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Something went wrong'
                    });

                }

            });

    };

</script>

>>>>>>> Stashed changes
<?php include 'includes/bottom-nav.php'; ?>