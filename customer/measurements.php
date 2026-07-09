<?php
session_start();
require '../includes/db.php';
require '../admin/check-slot.php';
require '../admin/find-next-slot.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
$categoryId = $_GET['category_id'] ?? '';
$subCategoryId = $_GET['sub_category_id'] ?? '';
$visitType = $_GET['visit_type'] ?? '';
$appointmentDate = $_GET['appointment_date'] ?? '';
$appointmentTime = $_GET['appointment_time'] ?? '';
$userId = $_SESSION['user_id'];
$selectedCategoryId = $_GET['category_id'] ?? '';
$selectedSubCategoryId = $_GET['sub_category_id'] ?? '';
$pageTitle = "Enter Measurements - Sogasu";
$headerTitle = "Measurements";
$activePage = "new-order";

// Fetch categories and sub-categories
$categories = $pdo->query("SELECT id, category_name FROM categories WHERE status='active' AND is_deleted = 0 ORDER BY category_name")->fetchAll();

$savedData = [];

if ($subCategoryId) {

    $stmt = $pdo->prepare("
        SELECT data
        FROM customer_profiles
        WHERE user_id = ?
        AND section_type = 'measurements'
        AND JSON_EXTRACT(data, '$.category_id') = ?
        AND JSON_EXTRACT(data, '$.sub_category_id') = ?
        LIMIT 1
    ");

    $stmt->execute([
        $userId,
        $categoryId,
        $subCategoryId
    ]);

    $result = $stmt->fetch();

    if ($result) {

        $savedData = json_decode(
            $result['data'],
            true
        ) ?? [];
    }
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
    SELECT 
        mk.key_name, 
        mk.key_name AS label, 
        mk.input_type
    FROM measurement_mapping mc
    JOIN measurement_keys mk 
        ON mc.key_id = mk.id
    WHERE mc.sub_category_id = ?
    AND mk.status = 'active'
    AND mk.is_deleted = 0
    ORDER BY mk.key_name ASC
");

    $stmt->execute([$subCategoryId]);
    $measurementFields = $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    header('Content-Type: application/json');

    try {

        $category_id = $_POST['category_id'] ?? '';
        $sub_category_id = $_POST['sub_category_id'] ?? '';
        $formData = [];

        foreach ($measurementFields as $field) {

            $fieldName = str_replace(' ', '_', $field['key_name']);

            if ($field['input_type'] == 'checkbox') {

                $formData[$field['key_name']] = isset($_POST[$fieldName]) ? 1 : 0;

            } else {

                $formData[$field['key_name']] = $_POST[$fieldName] ?? '';
            }
        }

        $additional_notes = $_POST['additional_notes'] ?? '';
        $deliveryType = $_POST['delivery_type'] ?? 'normal';
        $base_price = $_GET['base_price'] ?? 0;

        $extra_charges = $_GET['extra_charges'] ?? 0;

        $total_amount = $_GET['total_amount'] ?? 0;

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


        // ✅ SAVE INTO customer_measurements TABLE
        $stmt = $pdo->prepare("
            INSERT INTO customer_measurements 
            (
                user_id,
                category_id,
                sub_category_id,
                measurements,
                created_by
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $_SESSION['user_id'],
            $category_id,
            $sub_category_id,
            json_encode($formData),
            $_SESSION['user_id']
        ]);

        $measurementId = $pdo->lastInsertId();
        // ✅ UPDATE LATEST SAVED MEASUREMENTS IN customer_profiles

        $stmt = $pdo->prepare("
            SELECT id
            FROM customer_profiles
            WHERE user_id = ?
            AND section_type = 'measurements'
            AND JSON_EXTRACT(data, '$.category_id') = ?
            AND JSON_EXTRACT(data, '$.sub_category_id') = ?
            LIMIT 1
        ");

        $stmt->execute([
            $_SESSION['user_id'],
            $category_id,
            $sub_category_id
        ]);

        $existingProfile = $stmt->fetch();

        $profileData = array_merge($formData, [
            'category_id' => $category_id,
            'sub_category_id' => $sub_category_id,
            'additional_notes' => $additional_notes
        ]);

        if ($existingProfile) {

            $stmt = $pdo->prepare("
                UPDATE customer_profiles
                SET data = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                json_encode($profileData),
                $existingProfile['id']
            ]);

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO customer_profiles
                (
                    user_id,
                    section_type,
                    data,
                    created_at
                )
                VALUES (?, 'measurements', ?, NOW())
            ");

            $stmt->execute([
                $_SESSION['user_id'],
                json_encode($profileData)
            ]);
        }
        $conflict = checkSlotConflict(
            $pdo,
            $appointmentDate,
            $appointmentTime
        );

        if ($conflict) {

            $nextSlot = findNextAvailableSlot(
                $pdo,
                $appointmentDate,
                $appointmentTime
            );

            $suggestedDate = $appointmentDate;

            $suggestedTime = $nextSlot;

            $deleteStmt = $pdo->prepare("

                DELETE FROM appointment_notifications
                WHERE user_id = ?
                AND status = 'pending'

            ");

            $deleteStmt->execute([
                $_SESSION['user_id']
            ]);

            $notifyStmt = $pdo->prepare("

                INSERT INTO appointment_notifications (
                    user_id,
                    title,
                    message,
                    suggested_date,
                    suggested_time,
                    status,
                    created_at
                )

                VALUES (?, ?, ?, ?, ?, ?, NOW())

            ");

            $notifyStmt->execute([
                $_SESSION['user_id'],
                'Appointment Slot Conflict',
                'Selected slot unavailable.',
                $suggestedDate,
                $suggestedTime,
                'pending'
            ]);

            echo json_encode([
                'success' => false,
                'slot_conflict' => true,
                'message' => 'Selected slot already booked. Try after '
                    . date('h:i A', strtotime($nextSlot))
            ]);

            exit();
        }
        // Get customer details
        $stmt = $pdo->prepare("
            SELECT username, mobile
            FROM users
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        $stmt = $pdo->prepare("
INSERT INTO appointments
(
    user_id,
    customer_name,
    customer_phone,
    category_id,
    sub_category_id,
    appointment_date,
    appointment_time,
    visit_type,
    measurement_id,
    material_image,
    referral_image,
    delivery_type,
    delivery_method,
    appointment_source,
    workflow_status,
    status,
    notes,
    created_by,
    created_at
)
VALUES
(
    :user_id,
    :customer_name,
    :customer_phone,
    :category_id,
    :sub_category_id,
    :appointment_date,
    :appointment_time,
    :visit_type,
    :measurement_id,
    :material_image,
    :referral_image,
    :delivery_type,
    :delivery_method,
    'customer',
    'pending',
    'scheduled',
    :notes,
    :created_by,
    NOW()
)
");

        $stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':customer_name' => $user['username'] ?? '',
            ':customer_phone' => $user['mobile'] ?? '',
            ':category_id' => $category_id,
            ':sub_category_id' => $sub_category_id,
            ':appointment_date' => $appointmentDate,
            ':appointment_time' => $appointmentTime,
            ':visit_type' => $visitType,
            ':measurement_id' => $measurementId,
            ':material_image' => $material_image,
            ':referral_image' => $referral_image,
            ':delivery_type' => $deliveryType,
            ':delivery_method' => $_POST['delivery_method'] ?? null,
            ':notes' => $additional_notes,
            ':created_by' => $_SESSION['user_id']
        ]);

        // CLEAR SESSION
        unset($_SESSION['order']);
        $_SESSION['appointment_success'] = 'Appointment created successfully!';
        session_write_close();

        echo json_encode([
            'success' => true,
            'redirect' => 'dashboard.php'
        ]);

        exit();

    } catch (Exception $e) {

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);

        exit();
    }
}

include 'includes/header.php';
?>

<div class="container">

    <div class="card" style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
        <a href="new-order.php"
            style="background: var(--background); border: none; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary); text-decoration: none; font-size: 1.2rem;">
            <i class="ri-arrow-left-line"></i>
        </a>
        <div>
            <h2 style="font-size: 1.3rem; font-weight: 700; color: var(--text-main);"> Measurements</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Store your body measurements for tailoring</p>
        </div>
    </div>

    <div class="card">
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
        <div id="globalError" class="error" style="margin-bottom:10px;"></div>
        <form id="measurementsForm" method="POST" enctype="multipart/form-data" novalidate onsubmit="return false;">
            <input type="hidden" name="category_id" value="<?php echo $categoryId; ?>">
            <input type="hidden" name="sub_category_id" value="<?php echo $subCategoryId; ?>">
            <div style="margin-bottom:20px;">
                <label class="input-label">Do you want to provide measurements?</label>

                <label style="margin-right:20px;">
                    <input type="radio" name="has_measurements" value="yes">
                    Yes
                </label>

                <label>
                    <input type="radio" name="has_measurements" value="no" checked>
                    No
                </label>
            </div>
            <div id="measurementSection">

                <div class="section-title">
                    <span>Body Measurements</span>
                    <span style="font-size: 0.8rem; font-weight: 400; color: var(--text-muted);">(in inches)</span>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem;">
                    <?php foreach ($measurementFields as $field): ?>
                        <div>
                            <label class="input-label"><?php echo $field['label']; ?></label>
                            <?php if ($field['input_type'] == 'checkbox'): ?>
                                <input type="checkbox" name="<?php echo str_replace(' ', '_', $field['key_name']); ?>" value="1"
                                    <?php
                                    $fieldKey = str_replace(' ', '_', $field['key_name']);

                                    echo (
                                        !empty($savedData[$field['key_name']])
                                        || !empty($savedData[$fieldKey])
                                    )
                                        ? 'checked'
                                        : '';
                                    ?>>
                            <?php elseif ($field['input_type'] == 'select'): ?>
                                <select name="<?php echo str_replace(' ', '_', $field['key_name']); ?>" class="form-input">
                                    <option value="">Select</option>
                                    <option value="yes">Yes</option>
                                    <option value="no">No</option>
                                </select>
                            <?php else: ?>
                                <input type="number" name="<?php echo str_replace(' ', '_', $field['key_name']); ?>"
                                    class="form-input" step="0.1" <?php
                                    $fieldKey = str_replace(' ', '_', $field['key_name']);

                                    $value =
                                        $savedData[$field['key_name']]
                                        ?? $savedData[$fieldKey]
                                        ?? '';
                                    ?>
                                    value="<?php echo htmlspecialchars($value); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="section-title">Additional Notes</div>
            <textarea name="additional_notes" class="form-input" rows="4"
                placeholder="Any specific requirements regarding fit, design or style..."
                style="margin-bottom: 2rem;"><?php echo htmlspecialchars($savedData['additional_notes'] ?? ''); ?></textarea>
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
            <div class="section-title">Delivery Method</div>

            <div style="margin-bottom:2rem;">
                <select name="delivery_method" class="form-input">
                    <option value="">Select Delivery Method</option>
                    <option value="store_pickup">Store Pickup</option>
                    <option value="home_delivery">Home Delivery</option>
                </select>
            </div>

            <div id="deliveryMethodError" class="error"></div>

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

    @media (max-width: 768px) {

        /* Category & Sub Category */
        .card>div:first-child>div[style*="grid-template-columns: 1fr 1fr"] {
            display: grid !important;
            grid-template-columns: 1fr !important;
            gap: 1rem !important;
        }

        /* Measurement fields */
        #measurementSection>div:last-child {
            display: grid !important;
            grid-template-columns: 1fr !important;
            gap: 1rem !important;
        }

        /* Attachments */
        div[style*="grid-template-columns:1fr 1fr"] {
            display: grid !important;
            grid-template-columns: 1fr !important;
            gap: 1rem !important;
        }

        .form-input,
        textarea,
        input[type="file"] {
            width: 100%;
            box-sizing: border-box;
        }

        .card {
            overflow: hidden;
        }

        .container {
            overflow-x: hidden;
        }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const measurementSection = document.getElementById('measurementSection');

    function toggleMeasurements() {

        const selected = document.querySelector(
            'input[name="has_measurements"]:checked'
        );

        if (selected && selected.value === 'yes') {
            measurementSection.style.display = 'block';
        } else {
            measurementSection.style.display = 'none';
        }
    }

    // Page load lo default state
    toggleMeasurements();

    // Radio change ayinappudu
    document.querySelectorAll('input[name="has_measurements"]').forEach(radio => {

        radio.addEventListener('change', toggleMeasurements);

    });

    document.getElementById('saveMeasurementsBtn').onclick = function (e) {

        e.preventDefault();

        const form = document.getElementById('measurementsForm');
        const deliveryMethod =
            document.querySelector('[name="delivery_method"]').value;

        document.getElementById('deliveryMethodError').innerText = '';

        document.querySelector('[name="delivery_method"]')
            .classList.remove('input-error');

        if (!deliveryMethod) {

            document.getElementById('deliveryMethodError').innerText =
                'Please select delivery method';

            document.querySelector('[name="delivery_method"]')
                .classList.add('input-error');

            return;
        }

        const formData = new FormData(form);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {

                if (data.success) {

                    window.location.href = data.redirect;

                } else {

                    console.log(data);
                    alert(data.message);

                }
            })
            .catch(error => {

                console.error(error);

                alert('Server error');
            });

    };

</script>
<script>
    function previewImage(input, previewId) {

        if (input.files && input.files[0]) {

            const reader = new FileReader();

            reader.onload = function (e) {

                const preview =
                    document.getElementById(previewId);

                preview.src = e.target.result;

                preview.style.display = 'block';
            };

            reader.readAsDataURL(input.files[0]);

        }
    }
</script>
<?php include 'includes/bottom-nav.php'; ?>