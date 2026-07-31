<?php
session_start();
require '../includes/db.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employee') {
    header("Location: login.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT id
    FROM employees
    WHERE user_id = ?
    AND is_deleted = 0
    LIMIT 1
");

$stmt->execute([$_SESSION['user_id']]);

$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header("Location: login.php");
    exit;
}

$employeeId = $employee['id'];

$pageTitle = "Add Measurements - Sogasu";
$headerTitle = "Add Measurements";
$activePage = "measurements";

$appointmentId = $_GET['appointment_id'] ?? 0;

if (!$appointmentId) {
    die("Invalid Appointment");
}

/*
|--------------------------------------------------------------------------
| Fetch Appointment
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM appointments
    WHERE id = ?
    AND assigned_employee_id = ?
    AND is_deleted = 0
    LIMIT 1
");

$stmt->execute([
    $appointmentId,
    $employeeId
]);

$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appointment) {
    die("Appointment not found or not assigned to you.");
}

$userId = $appointment['user_id'];
$categoryId = $appointment['category_id'];
$subCategoryId = $appointment['sub_category_id'];
$measurementId = $appointment['measurement_id'];
$orderId = $appointment['order_id'];
$workflowStatus = $appointment['workflow_status'] ?? '';

$isEditMode = !empty($orderId);

/*
|--------------------------------------------------------------------------
| Current Order And Rack
|--------------------------------------------------------------------------
*/

$currentOrder = null;
$currentRackId = null;
$currentRackName = null;

if ($isEditMode) {

    $stmt = $pdo->prepare("
        SELECT
            o.order_code,
            o.rack_id,
            r.rack_name
        FROM orders o
        LEFT JOIN racks r
            ON r.id = o.rack_id
        WHERE o.order_code = ?
        AND o.is_deleted = 0
        LIMIT 1
    ");

    $stmt->execute([$orderId]);

    $currentOrder = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentOrder) {
        die("Order not found.");
    }

    $currentRackId = $currentOrder['rack_id'];
    $currentRackName = $currentOrder['rack_name'];
}

/*
|--------------------------------------------------------------------------
| Categories
|--------------------------------------------------------------------------
*/

$categories = $pdo->query("
SELECT id,category_name
FROM categories
WHERE status='active'
AND is_deleted=0
ORDER BY category_name
")->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Sub Categories
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
SELECT id,name
FROM sub_categories
WHERE category_id=?
AND status='active'
AND is_deleted=0
");

$stmt->execute([$categoryId]);

$subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Measurement Fields
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
SELECT
mk.key_name,
mk.key_name AS label,
mk.input_type

FROM measurement_mapping mm

JOIN measurement_keys mk
ON mm.key_id=mk.id

WHERE mm.sub_category_id=?

AND mk.status='active'
AND mk.is_deleted=0

ORDER BY mk.key_name
");

$stmt->execute([$subCategoryId]);

$measurementFields = $stmt->fetchAll(PDO::FETCH_ASSOC);
/*
|--------------------------------------------------------------------------
| Available Racks
|--------------------------------------------------------------------------
*/

$rackStmt = $pdo->query("
    SELECT id, rack_name, status
    FROM racks
    ORDER BY rack_name ASC
");

$racks = $rackStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Existing Measurements
|--------------------------------------------------------------------------
*/

$savedMeasurements = [];

if (!empty($measurementId)) {

    $stmt = $pdo->prepare("
    SELECT *
    FROM customer_measurements
    WHERE id=?
    ");

    $stmt->execute([$measurementId]);

    $measurementRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($measurementRow) {

        $savedMeasurements =
            json_decode(
                $measurementRow['measurements'],
                true
            ) ?? [];

    }

}

/*
|--------------------------------------------------------------------------
| Save
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    header("Content-Type: application/json");

    try {

        $pdo->beginTransaction();

        $action = $_POST['action'] ?? 'create_order';

        $allowedActions = [
            'create_order',
            'save_measurements',
            'visit_completed',
            'select_fabric_source',
            'update_measurements',
            'change_rack'
        ];

        if (!in_array($action, $allowedActions, true)) {
            throw new Exception('Invalid action.');
        }

        $rackId = intval($_POST['rack_id'] ?? 0);
        $fabricSource = $_POST['fabric_source'] ?? '';

        if ($action === 'visit_completed' || $action === 'select_fabric_source') {

            $stmt = $pdo->prepare("
                SELECT measurement_id, order_id, workflow_status, material_image
                FROM appointments
                WHERE id = ?
                AND assigned_employee_id = ?
                AND is_deleted = 0
                FOR UPDATE
            ");

            $stmt->execute([
                $appointmentId,
                $employeeId
            ]);

            $lockedAppointment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$lockedAppointment) {
                throw new Exception('Appointment not found.');
            }

            if (empty($lockedAppointment['measurement_id'])) {
                throw new Exception('Measurements must be saved before completing the visit.');
            }

            if (!empty($lockedAppointment['order_id'])) {
                throw new Exception('Order has already been created for this appointment.');
            }

            if ($action === 'visit_completed') {

                if (
                    !in_array(
                        $lockedAppointment['workflow_status'],
                        [
                            'employee_assigned',
                            'appointment_confirmed',
                            'visit_completed'
                        ],
                        true
                    )
                ) {
                    throw new Exception(
                        'This appointment cannot be marked as visit completed at its current workflow stage.'
                    );
                }

                $stmt = $pdo->prepare("
                    UPDATE appointments
                    SET workflow_status = 'visit_completed', updated_at = NOW()
                    WHERE id = ?
                ");

                $stmt->execute([$appointmentId]);

            } else {

                if ($lockedAppointment['workflow_status'] !== 'visit_completed') {
                    throw new Exception('Please complete the visit before selecting fabric source.');
                }

                if ($fabricSource !== 'customer') {
                    throw new Exception('Please select Customer Fabric to continue with rack assignment.');
                }

                $hasNewFabricImage = isset($_FILES['fabric_image']) &&
                    $_FILES['fabric_image']['error'] === UPLOAD_ERR_OK;

                if (empty($lockedAppointment['material_image']) && !$hasNewFabricImage) {
                    throw new Exception('FABRIC_IMAGE_REQUIRED|Please upload the customer fabric image.');
                }

                $materialImage = null;

                if ($hasNewFabricImage) {

                    $fabricImageExtension = strtolower(pathinfo($_FILES['fabric_image']['name'], PATHINFO_EXTENSION));
                    $allowedFabricImageExtensions = ['jpg', 'jpeg', 'png', 'webp'];

                    if (!in_array($fabricImageExtension, $allowedFabricImageExtensions, true)) {
                        throw new Exception('INVALID_FABRIC_IMAGE|Please upload a JPG, JPEG, PNG, or WEBP image.');
                    }

                    $fabricUploadDirectory = __DIR__ . '/../uploads/fabrics/';

                    if (!is_dir($fabricUploadDirectory) && !mkdir($fabricUploadDirectory, 0777, true)) {
                        throw new Exception('Unable to prepare the fabric image upload directory.');
                    }

                    $fabricImageName = 'fabric_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fabricImageExtension;

                    if (!move_uploaded_file($_FILES['fabric_image']['tmp_name'], $fabricUploadDirectory . $fabricImageName)) {
                        throw new Exception('Unable to upload the fabric image.');
                    }

                    $materialImage = 'uploads/fabrics/' . $fabricImageName;
                }

                $stmt = $pdo->prepare("
                    UPDATE appointments
                    SET
                        fabric_source = 'customer',
                        material_image = COALESCE(?, material_image),
                        workflow_status = 'fabric_received',
                        updated_at = NOW()
                    WHERE id = ?
                ");

                $stmt->execute([
                    $materialImage,
                    $appointmentId
                ]);
            }

            $pdo->commit();

            echo json_encode([
                "success" => true,
                "action" => $action
            ]);

            exit;
        }
        /*
|--------------------------------------------------------------------------
| Change Rack Only
|--------------------------------------------------------------------------
*/

        if ($action === 'change_rack') {

            if (!$isEditMode) {
                throw new Exception(
                    'Rack can be changed only after order creation.'
                );
            }

            if ($rackId <= 0) {
                throw new Exception(
                    'RACK_REQUIRED|Please choose a new rack.'
                );
            }

            $stmt = $pdo->prepare("
                SELECT
                    order_code,
                    rack_id
                FROM orders
                WHERE order_code = ?
                AND is_deleted = 0
                FOR UPDATE
            ");

            $stmt->execute([$orderId]);

            $lockedOrder = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$lockedOrder) {
                throw new Exception('Order not found.');
            }

            $oldRackId = $lockedOrder['rack_id'];

            if ((int) $oldRackId === $rackId) {
                throw new Exception(
                    'Please choose a different rack.'
                );
            }

            $stmt = $pdo->prepare("
                SELECT id, status
                FROM racks
                WHERE id = ?
                FOR UPDATE
            ");

            $stmt->execute([$rackId]);

            $newRack = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$newRack) {
                throw new Exception('Rack not found.');
            }

            if ($newRack['status'] !== 'Available') {
                throw new Exception(
                    'Selected rack is no longer available.'
                );
            }

            $stmt = $pdo->prepare("
                UPDATE orders
                SET
                    rack_id = ?,
                    updated_at = NOW()
                WHERE order_code = ?
            ");

            $stmt->execute([
                $rackId,
                $orderId
            ]);

            if (!empty($oldRackId)) {

                $stmt = $pdo->prepare("
                    UPDATE racks
                    SET status = 'Available'
                    WHERE id = ?
                ");

                $stmt->execute([$oldRackId]);
            }

            $stmt = $pdo->prepare("
                UPDATE racks
                SET status = 'Occupied'
                WHERE id = ?
            ");

            $stmt->execute([$rackId]);

            $pdo->commit();

            echo json_encode([
                "success" => true,
                "action" => "rack_changed",
                "redirect" => "measurements.php"
            ]);

            exit;
        }

        $formData = [];

        foreach ($measurementFields as $field) {

            $fieldName = str_replace(' ', '_', $field['key_name']);

            if ($field['input_type'] == 'checkbox') {

                $formData[$field['key_name']] =
                    isset($_POST[$fieldName]) ? 1 : 0;

            } else {

                $formData[$field['key_name']] =
                    $_POST[$fieldName] ?? '';

            }

        }

        /*
        |--------------------------------------------------------------------------
        | Validate Measurements
        |--------------------------------------------------------------------------
        */

        $measurementValues = [];

        foreach ($measurementFields as $field) {

            if ($field['input_type'] === 'checkbox') {
                continue;
            }

            $measurementValues[] =
                trim((string) ($formData[$field['key_name']] ?? ''));
        }

        /*
        |--------------------------------------------------------------------------
        | All Measurements Empty
        |--------------------------------------------------------------------------
        */

        $hasMeasurementValue = false;

        foreach ($measurementValues as $value) {

            if ($value !== '') {
                $hasMeasurementValue = true;
                break;
            }
        }

        if (!$hasMeasurementValue) {

            throw new Exception(
                'MEASUREMENTS_EMPTY|' .
                (
                    $isEditMode
                    ? 'Please enter the measurements before updating.'
                    : 'Please enter the measurements before assigning a rack.'
                )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Individual Measurement Validation
        |--------------------------------------------------------------------------
        */

        foreach ($measurementFields as $field) {

            if ($field['input_type'] === 'checkbox') {
                continue;
            }

            $value = trim(
                (string) ($formData[$field['key_name']] ?? '')
            );

            if ($value === '') {

                throw new Exception(
                    'MEASUREMENT_REQUIRED|' .
                    $field['label'] .
                    ' measurement is required.'
                );
            }

            if (
                is_numeric($value) &&
                (float) $value <= 0
            ) {

                throw new Exception(
                    'INVALID_MEASUREMENT|' .
                    $field['label'] .
                    ' must be greater than 0.'
                );
            }
        }

        $jsonMeasurements = json_encode($formData);

        /*
        |--------------------------------------------------------------------------
        | INSERT / UPDATE Measurements
        |--------------------------------------------------------------------------
        */

        if (!empty($measurementId)) {

            // Update Existing Measurement

            $stmt = $pdo->prepare("
                UPDATE customer_measurements
                SET
                    measurements = ?,
                    updated_by = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $jsonMeasurements,
                $_SESSION['user_id'],
                $measurementId
            ]);

        } else {

            // Create New Measurement

            $stmt = $pdo->prepare("
                INSERT INTO customer_measurements
                (
                    user_id,
                    category_id,
                    sub_category_id,
                    measurements,
                    created_by
                )
                VALUES
                (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $userId,
                $categoryId,
                $subCategoryId,
                $jsonMeasurements,
                $_SESSION['user_id']
            ]);

            $measurementId = $pdo->lastInsertId();

            // Update Appointment

            $stmt = $pdo->prepare("
                UPDATE appointments
                SET measurement_id = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $measurementId,
                $appointmentId
            ]);
        }
        /*
|--------------------------------------------------------------------------
| Update Measurements Only
|--------------------------------------------------------------------------
*/

        if ($action === 'update_measurements') {

            if (!$isEditMode) {
                throw new Exception(
                    'Measurement update is not available before order creation.'
                );
            }

            if (empty($measurementId)) {
                throw new Exception(
                    'Existing measurements not found for this order.'
                );
            }

            $pdo->commit();

            echo json_encode([
                "success" => true,
                "action" => "measurement_updated",
                "redirect" => "measurements.php"
            ]);

            exit;
        }

        if ($action === 'save_measurements') {

            if ($isEditMode) {
                throw new Exception(
                    'Measurements have already been saved for this order.'
                );
            }

            $pdo->commit();

            echo json_encode([
                "success" => true,
                "action" => "measurements_saved"
            ]);

            exit;
        }
        /*
        |--------------------------------------------------------------------------
        | Assign Rack And Create Order
        |--------------------------------------------------------------------------
        */
        if ($action === 'create_order' && !in_array($fabricSource, ['customer', 'boutique'], true)) {

            throw new Exception(
                'FABRIC_SOURCE_REQUIRED|Please select a fabric source.'
            );
        }

        if ($action === 'create_order' && $fabricSource === 'customer' && $rackId <= 0) {

            throw new Exception(
                'RACK_REQUIRED|Please select a rack.'
            );
        }

        $stmt = $pdo->prepare("
                SELECT *
                FROM appointments
                WHERE id = ?
                AND assigned_employee_id = ?
                AND is_deleted = 0
                FOR UPDATE
            ");

        $stmt->execute([
            $appointmentId,
            $employeeId
        ]);

        $lockedAppointment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lockedAppointment) {
            throw new Exception('Appointment not found.');
        }

        if (empty($lockedAppointment['measurement_id'])) {
            throw new Exception(
                'Measurements must be saved before assigning a rack.'
            );
        }

        if ($fabricSource === 'customer' && $lockedAppointment['workflow_status'] !== 'fabric_received') {
            throw new Exception('Please select Customer Fabric before assigning a rack.');
        }

        if ($fabricSource === 'boutique' && $lockedAppointment['workflow_status'] !== 'visit_completed') {
            throw new Exception('Please complete the visit before creating the order.');
        }

        if ($fabricSource === 'boutique') {

            $stmt = $pdo->prepare("
                UPDATE appointments
                SET fabric_source = 'boutique', updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([$appointmentId]);
        }

        if ($fabricSource === 'customer') {

            $stmt = $pdo->prepare("
            SELECT id, status
            FROM racks
            WHERE id = ?
            FOR UPDATE
        ");

            $stmt->execute([$rackId]);

            $rack = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$rack) {
                throw new Exception('Rack not found.');
            }

            if ($rack['status'] !== 'Available') {
                throw new Exception(
                    'Selected rack is no longer available.'
                );
            }

        }

        $orderCode = $lockedAppointment['order_id'];

        if (!empty($orderCode)) {
            throw new Exception('Order has already been created for this appointment.');
        }

        if (empty($orderCode)) {

            $orderCode =
                'ORD-' .
                date('Y') .
                '-' .
                str_pad(
                    mt_rand(1, 9999),
                    4,
                    '0',
                    STR_PAD_LEFT
                );

            $customerId = null;

            if (!empty($lockedAppointment['user_id'])) {

                $custStmt = $pdo->prepare("
                SELECT id
                FROM customers
                WHERE user_id = ?
                LIMIT 1
            ");

                $custStmt->execute([
                    $lockedAppointment['user_id']
                ]);

                $customer =
                    $custStmt->fetch(PDO::FETCH_ASSOC);

                $customerId =
                    $customer['id'] ?? null;
            }
            /*
|--------------------------------------------------------------------------
| Order Pricing And Due Date
|--------------------------------------------------------------------------
*/

            $pricingStmt = $pdo->prepare("
    SELECT
        price,
        preparation_days
    FROM sub_categories
    WHERE id = ?
    AND category_id = ?
    AND status = 'active'
    AND is_deleted = 0
    LIMIT 1
");

            $pricingStmt->execute([
                $lockedAppointment['sub_category_id'],
                $lockedAppointment['category_id']
            ]);

            $subCategoryDetails =
                $pricingStmt->fetch(PDO::FETCH_ASSOC);

            if (!$subCategoryDetails) {
                throw new Exception(
                    'Unable to calculate order pricing and due date.'
                );
            }

            $basePrice = (float) (
                $subCategoryDetails['price'] ?? 0
            );

            $extraCharges = 0.00;

            $totalAmount =
                $basePrice + $extraCharges;

            $preparationDays = (int) (
                $subCategoryDetails['preparation_days'] ?? 0
            );

            $dueDate = date(
                'Y-m-d',
                strtotime(
                    '+' . $preparationDays . ' days'
                )
            );

            $stmt = $pdo->prepare("
            INSERT INTO orders
            (
                order_code,
                customer_id,
                category_id,
                sub_category_id,
                fabric_details,
                notes,
                material_image,
                referral_image,
                order_status,
                rack_id,
                base_price,
                extra_charges,
                total_amount,
                advance_amount,
                due_date,
                measurement_unit,
                is_customer_order,
                is_deleted,
                created_at,
                updated_at
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?, ?, ?,
                'pending',
                ?,
                ?,
                ?,
                ?,
                0.00,
                ?,
                'CMS',
                1,
                0,
                NOW(),
                NOW()
            )
        ");

            $stmt->execute([
                $orderCode,
                $customerId,
                $lockedAppointment['category_id'],
                $lockedAppointment['sub_category_id'],
                $lockedAppointment['type'] ?? '',
                $lockedAppointment['notes'],
                $lockedAppointment['material_image'],
                $lockedAppointment['referral_image'],
                $fabricSource === 'boutique' ? null : $rackId,
                $basePrice,
                $extraCharges,
                $totalAmount,
                $dueDate
            ]);
        }

        $stmt = $pdo->prepare("
            UPDATE appointments
            SET
                order_id = ?,
                workflow_status = 'order_created',
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $orderCode,
            $appointmentId
        ]);

        if ($fabricSource === 'customer') {

            $stmt = $pdo->prepare("
            UPDATE racks
            SET status = 'Occupied'
            WHERE id = ?
        ");

            $stmt->execute([$rackId]);

        }

        $pdo->commit();

        echo json_encode([
            "success" => true,
            "redirect" => "measurements.php"
        ]);

        exit;

    } catch (Exception $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $errorMessage = $e->getMessage();

        $errorType = 'ERROR';

        if (strpos($errorMessage, '|') !== false) {

            [$errorType, $errorMessage] =
                explode('|', $errorMessage, 2);
        }

        echo json_encode([
            "success" => false,
            "error_type" => $errorType,
            "message" => $errorMessage
        ]);

        exit;
    }

}

include 'includes/header.php';
?>

<div class="container">

    <div class="card">

        <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">

            <a href="measurements.php" style="text-decoration:none;font-size:20px;">
                <i class="ri-arrow-left-line"></i>
            </a>

            <div>

                <h2>
                    <?= $isEditMode ? 'Update Measurements' : 'Add Measurements' ?>
                </h2>

                <p style="color:#64748b;">
                    <?= $isEditMode
                        ? 'Update customer measurements or change the assigned rack.'
                        : 'Enter customer measurements.' ?>
                </p>

            </div>

        </div>

        <form id="measurementForm" method="POST" data-workflow-status="<?= htmlspecialchars($workflowStatus) ?>">

            <input type="hidden" name="category_id" value="<?= $categoryId ?>">

            <input type="hidden" name="sub_category_id" value="<?= $subCategoryId ?>">

            <div style="
display:grid;
grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
gap:15px;
">
                <?php foreach ($measurementFields as $field): ?>

                    <?php
                    $fieldName = str_replace(' ', '_', $field['key_name']);

                    $value = $savedMeasurements[$field['key_name']]
                        ?? $savedMeasurements[$fieldName]
                        ?? '';
                    ?>

                    <div>

                        <label class="input-label">
                            <?= htmlspecialchars($field['label']) ?>
                        </label>

                        <?php if ($field['input_type'] == 'checkbox'): ?>

                            <input type="checkbox" name="<?= $fieldName ?>" value="1" <?= !empty($value) ? 'checked' : '' ?>>

                        <?php elseif ($field['input_type'] == 'select'): ?>

                            <select name="<?= $fieldName ?>" class="form-input">

                                <option value="">Select</option>

                                <option value="yes" <?= $value == 'yes' ? 'selected' : '' ?>>
                                    Yes
                                </option>

                                <option value="no" <?= $value == 'no' ? 'selected' : '' ?>>
                                    No
                                </option>

                            </select>

                        <?php else: ?>

                            <input type="number" step="0.1" class="form-input" name="<?= $fieldName ?>"
                                value="<?= htmlspecialchars($value) ?>">

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            </div>

            <div class="rack-section">

                <?php if (!$isEditMode): ?>

                    <?php if (empty($measurementId)): ?>

                        <button type="button" id="saveMeasurementsBtn" class="assign-rack-btn">

                            <i class="ri-save-line"></i>

                            <span>Save Measurements</span>

                        </button>

                    <?php else: ?>

                        <button type="button" id="visitCompletedBtn" class="assign-rack-btn">

                            <i class="ri-checkbox-circle-line"></i>

                            <span>Visit Completed</span>

                        </button>

                    <?php endif; ?>

                    <div id="fabricSourceSection" class="fabric-source-options" style="display:none;">

                        <span class="current-rack-text">Fabric Source:</span>

                        <label><input type="radio" name="fabric_source" value="customer"> Customer Fabric</label>

                        <label><input type="radio" name="fabric_source" value="boutique"> Boutique Fabric</label>

                        <div id="customerFabricDetails" class="customer-fabric-details" style="display:none;">

                            <div>
                                <?php if (!empty($appointment['material_image'])): ?>

                                    <?php
                                    $materialImagePreview = strpos($appointment['material_image'], '/') === false
                                        ? '../customer/uploads/' . $appointment['material_image']
                                        : '../' . $appointment['material_image'];
                                    ?>

                                    <label class="input-label">Current Fabric Image</label>

                                    <img src="<?= htmlspecialchars($materialImagePreview) ?>" alt="Current Fabric"
                                        style="width:100%; max-width:220px; max-height:160px; object-fit:contain; border-radius:8px; background:#f1f5f9; margin-bottom:8px;">

                                    <label class="input-label">Replace Fabric Image (Optional)</label>

                                <?php else: ?>

                                    <label class="input-label">Fabric Image</label>

                                <?php endif; ?>

                                <input type="file" name="fabric_image" class="form-input"
                                    accept="image/jpeg,image/png,image/webp">
                            </div>

                        </div>

                        <button type="button" id="continueFabricBtn" class="assign-rack-btn">

                            <i class="ri-arrow-right-line"></i>

                            <span>Continue</span>

                        </button>

                    </div>

                    <button type="button" id="assignRackBtn" class="assign-rack-btn" onclick="openRackModal()"
                        style="display:none;">

                        <i class="ri-stack-line"></i>

                        <span>Assign Rack</span>

                    </button>

                <?php else: ?>

                    <button type="button" id="updateMeasurementBtn" class="assign-rack-btn">

                        <i class="ri-edit-line"></i>

                        <span>Update Measurements</span>

                    </button>

                    <?php if (!empty($currentRackId)): ?>

                        <button type="button" class="assign-rack-btn" onclick="openRackModal()">

                            <i class="ri-exchange-line"></i>

                            <span>Change Rack</span>

                        </button>

                        <?php if (!empty($currentRackName)): ?>

                            <span class="current-rack-text">

                                Current Rack:
                                <strong>
                                    <?= htmlspecialchars($currentRackName) ?>
                                </strong>

                            </span>

                        <?php endif; ?>

                    <?php endif; ?>

                <?php endif; ?>

            </div>

        </form>

    </div>

</div>
<div id="rackModal" class="rack-modal">

    <div class="rack-modal-card">

        <div class="rack-modal-header">

            <h3>
                <i class="ri-stack-line"></i>
                <?= $isEditMode ? 'Change Rack' : 'Assign Rack' ?>
            </h3>

            <button type="button" onclick="closeRackModal()" class="rack-modal-close">

                <i class="ri-close-line"></i>

            </button>

        </div>

        <label class="input-label">
            <?= $isEditMode ? 'Choose New Rack' : 'Choose Rack' ?>
        </label>

        <select id="rackSelect" class="form-input">

            <option value="">Select Rack</option>

            <?php foreach ($racks as $rack): ?>

                <option value="<?= $rack['id'] ?>" <?= (
                      $rack['status'] !== 'Available' ||
                      ($isEditMode && (int) $rack['id'] === (int) $currentRackId)
                  ) ? 'disabled' : '' ?>>

                    <?= htmlspecialchars($rack['rack_name']) ?>

                    <?= $rack['status'] !== 'Available'
                        ? ' (' . htmlspecialchars($rack['status']) . ')'
                        : '' ?>

                </option>

            <?php endforeach; ?>

        </select>

        <div class="rack-modal-actions">

            <button type="button" class="rack-cancel-btn" onclick="closeRackModal()">

                Cancel

            </button>

            <button type="button" id="confirmRackBtn" class="rack-confirm-btn">

                <i class="ri-stack-line"></i>

                <?= $isEditMode ? 'Change Rack' : 'Assign Rack' ?>

            </button>

        </div>

    </div>

</div>
<style>
    .input-label {

        display: block;

        margin-bottom: 6px;

        font-weight: 600;

    }

    .form-input {

        width: 100%;

        padding: 10px;

        border: 1px solid #ddd;

        border-radius: 8px;

    }

    .rack-section {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #fce7f3;
    }

    .assign-rack-btn {
        border: 1px solid #fecdd3;
        background: #fce7f3;
        color: #be185d;
        padding: 9px 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        transition: 0.2s;
    }

    .assign-rack-btn:hover {
        background: #fbcfe8;
        border-color: #f9a8d4;
    }

    .rack-modal {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 9999;
        background: rgba(15, 23, 42, 0.55);
        backdrop-filter: blur(3px);
        align-items: center;
        justify-content: center;
    }

    .rack-modal-card {
        background: #fff;
        width: 90%;
        max-width: 430px;
        border: 1px solid #fbcfe8;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 20px 40px rgba(190, 24, 93, 0.15);
    }

    .rack-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
    }

    .rack-modal-header h3 {
        margin: 0;
        color: #831843;
        font-size: 18px;
        display: flex;
        align-items: center;
        gap: 7px;
    }

    .rack-modal-close {
        border: none;
        background: transparent;
        color: #be185d;
        font-size: 20px;
        cursor: pointer;
    }

    .rack-modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 20px;
    }

    .rack-cancel-btn {
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #64748b;
        padding: 9px 15px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
    }

    .rack-confirm-btn {
        border: 1px solid #fecdd3;
        background: #fce7f3;
        color: #be185d;
        padding: 9px 15px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .rack-confirm-btn:hover {
        background: #fbcfe8;
    }

    .current-rack-text {
        margin-left: 12px;
        font-size: 13px;
        color: #64748b;
    }

    .current-rack-text strong {
        color: #be185d;
    }

    .fabric-source-options {
        display: inline-flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
    }

    .fabric-source-options label {
        font-size: 13px;
        color: #475569;
        cursor: pointer;
    }

    .customer-fabric-details {
        width: 100%;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
        padding: 14px;
        border: 1px solid #fce7f3;
        border-radius: 8px;
        background: #fffafb;
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function submitWorkflowRequest(data, onSuccess) {

        fetch(window.location.href, {

            method: "POST",

            body: data

        })

            .then(r => r.json())

            .then(res => {

                if (res.success) {

                    onSuccess(res);

                    return;

                }

                let errorTitle = "Error";

                if (res.error_type === "MEASUREMENTS_EMPTY") {

                    errorTitle = "Measurements Required";

                } else if (res.error_type === "FABRIC_SOURCE_REQUIRED") {

                    errorTitle = "Fabric Source Required";

                } else if (res.error_type === "FABRIC_IMAGE_REQUIRED") {

                    errorTitle = "Fabric Image Required";

                } else if (res.error_type === "RACK_REQUIRED") {

                    errorTitle = "Rack Required";

                } else if (res.message && res.message.includes("no longer available")) {

                    errorTitle = "Rack Already Occupied";

                } else if (res.message && res.message.includes("complete the visit")) {

                    errorTitle = "Visit Not Completed";

                }

                Swal.fire({
                    icon: "error",
                    title: errorTitle,
                    text: res.message,
                    confirmButtonColor: "#be185d"
                });

            })

            .catch(() => {

                Swal.fire({
                    icon: "error",
                    title: "Server Error",
                    text: "Something went wrong.",
                    confirmButtonColor: "#be185d"
                });

            });

    }

    const measurementForm = document.getElementById("measurementForm");
    const fabricSourceSection = document.getElementById("fabricSourceSection");
    const customerFabricDetails = document.getElementById("customerFabricDetails");
    const assignRackBtn = document.getElementById("assignRackBtn");
    const fabricSourceInputs = document.querySelectorAll('input[name="fabric_source"]');

    function showFabricSourceSection() {

        if (fabricSourceSection) {

            fabricSourceSection.style.display = "inline-flex";

        }

    }

    function hideFabricSourceSection() {

        if (fabricSourceSection) {

            fabricSourceSection.style.display = "none";

        }

    }

    function showAssignRackButton() {

        if (assignRackBtn) {

            assignRackBtn.style.display = "inline-flex";

        }

    }

    fabricSourceInputs.forEach(function (input) {

        input.addEventListener("change", function () {

            if (customerFabricDetails) {

                customerFabricDetails.style.display = this.value === "customer" ? "grid" : "none";

            }

        });

    });

    if (measurementForm && ["appointment_confirmed", "visit_completed", "fabric_received", "order_created"].includes(measurementForm.dataset.workflowStatus)) {

        const savedVisitCompletedBtn = document.getElementById("visitCompletedBtn");

        if (
            savedVisitCompletedBtn &&
            measurementForm.dataset.workflowStatus !== "appointment_confirmed"
        ) {
            savedVisitCompletedBtn.style.display = "none";
        }
        if (measurementForm.dataset.workflowStatus === "appointment_confirmed") {

            hideFabricSourceSection();

        }

        if (measurementForm.dataset.workflowStatus === "visit_completed") {

            showFabricSourceSection();

        } else if (measurementForm.dataset.workflowStatus === "fabric_received") {

            hideFabricSourceSection();

            showAssignRackButton();

        }

    }

    const saveMeasurementsBtn = document.getElementById("saveMeasurementsBtn");

    if (saveMeasurementsBtn) {

        saveMeasurementsBtn.onclick = function () {

            let data = new FormData(document.getElementById("measurementForm"));

            data.append("action", "save_measurements");

            submitWorkflowRequest(data, () => {

                Swal.fire({
                    icon: "success",
                    title: "Measurements Saved",
                    text: "Customer measurements saved successfully.",
                    confirmButtonColor: "#be185d",
                    timer: 1800,
                    showConfirmButton: false
                }).then(() => {

                    window.location.reload();

                });

            });

        };

    }

    const visitCompletedBtn = document.getElementById("visitCompletedBtn");

    if (visitCompletedBtn) {

        visitCompletedBtn.onclick = function () {

            let data = new FormData(document.getElementById("measurementForm"));

            data.append("action", "visit_completed");

            submitWorkflowRequest(data, () => {

                visitCompletedBtn.style.display = "none";

                showFabricSourceSection();

                Swal.fire({
                    icon: "success",
                    title: "Visit Completed",
                    text: "Please select the fabric source.",
                    confirmButtonColor: "#be185d",
                    timer: 1800,
                    showConfirmButton: false
                });

            });

        };

    }

    const continueFabricBtn = document.getElementById("continueFabricBtn");

    if (continueFabricBtn) {

        continueFabricBtn.onclick = function () {

            const selectedFabric = document.querySelector('input[name="fabric_source"]:checked');

            if (!selectedFabric) {

                Swal.fire({
                    icon: "error",
                    title: "Fabric Source Required",
                    text: "Please select a fabric source.",
                    confirmButtonColor: "#be185d"
                });

                return;

            }

            let data = new FormData(document.getElementById("measurementForm"));

            if (selectedFabric.value === "customer") {

                data.append("action", "select_fabric_source");

                submitWorkflowRequest(data, () => {

                    Swal.fire({
                        icon: "success",
                        title: "Fabric Received",
                        text: "Fabric details saved successfully.",
                        confirmButtonColor: "#be185d",
                        timer: 1800,
                        showConfirmButton: false
                    }).then(() => {

                        hideFabricSourceSection();

                        showAssignRackButton();

                        openRackModal();

                    });

                });

                return;

            }

            data.append("action", "create_order");

            submitWorkflowRequest(data, (res) => {

                Swal.fire({
                    icon: "success",
                    title: "Success!",
                    text: "Measurements saved and order created successfully.",
                    confirmButtonColor: "#be185d",
                    timer: 1800,
                    showConfirmButton: false
                }).then(() => {

                    window.location.href = res.redirect;

                });

            });

        };

    }

    <?php if ($isEditMode): ?>

        document.getElementById("updateMeasurementBtn").onclick = function () {

            let form = document.getElementById("measurementForm");

            let data = new FormData(form);

            data.append("action", "update_measurements");

            fetch(window.location.href, {

                method: "POST",

                body: data

            })

                .then(r => r.json())

                .then(res => {

                    if (res.success) {

                        Swal.fire({
                            icon: "success",
                            title: "Measurements Updated",
                            text: "Customer measurements updated successfully.",
                            confirmButtonColor: "#be185d",
                            timer: 1800,
                            showConfirmButton: false
                        }).then(() => {

                            window.location.href = res.redirect;

                        });

                    } else {

                        let errorTitle = "Error";

                        if (res.error_type === "MEASUREMENTS_EMPTY") {

                            errorTitle = "Measurements Required";

                        } else if (res.error_type === "MEASUREMENT_REQUIRED") {

                            errorTitle = "Measurement Required";

                        } else if (res.error_type === "INVALID_MEASUREMENT") {

                            errorTitle = "Invalid Measurement";

                        }

                        Swal.fire({
                            icon: "error",
                            title: errorTitle,
                            text: res.message,
                            confirmButtonColor: "#be185d"
                        });

                    }

                })

                .catch(() => {

                    Swal.fire({
                        icon: "error",
                        title: "Server Error",
                        text: "Something went wrong.",
                        confirmButtonColor: "#be185d"
                    });

                });

        };

    <?php endif; ?>

    function openRackModal() {

        document.getElementById("rackModal").style.display = "flex";

    }

    function closeRackModal() {

        document.getElementById("rackModal").style.display = "none";

    }

    document.getElementById("rackModal").addEventListener("click", function (e) {

        if (e.target === this) {

            closeRackModal();

        }

    });

    document.getElementById("confirmRackBtn").onclick = function () {

        let rackId = document.getElementById("rackSelect").value;

        let form = document.getElementById("measurementForm");

        let data = new FormData(form);

        data.append("rack_id", rackId);
        data.append(
            "action",
            <?= $isEditMode
                ? '"change_rack"'
                : '"create_order"' ?>
        );

        <?php if (!$isEditMode): ?>
            data.set("fabric_source", "customer");
        <?php endif; ?>

        fetch(window.location.href, {

            method: "POST",

            body: data

        })

            .then(r => r.json())

            .then(res => {

                if (res.success) {

                    closeRackModal();

                    Swal.fire({
                        icon: "success",
                        title: "Success!",
                        text: <?= $isEditMode
                            ? '"Rack changed successfully"'
                            : '"Measurements saved, rack assigned and order created successfully"' ?>,
                        confirmButtonColor: "#be185d",
                        timer: 1800,
                        showConfirmButton: false
                    }).then(() => {

                        window.location.href = res.redirect;

                    });

                } else {

                    closeRackModal();

                    let errorTitle = "Error";

                    if (res.error_type === "MEASUREMENTS_EMPTY") {

                        errorTitle = "Measurements Required";

                    } else if (res.error_type === "MEASUREMENT_REQUIRED") {

                        errorTitle = "Measurement Required";

                    } else if (res.error_type === "INVALID_MEASUREMENT") {

                        errorTitle = "Invalid Measurement";

                    } else if (res.error_type === "RACK_REQUIRED") {

                        errorTitle = "Rack Required";

                    } else if (res.message && res.message.includes("no longer available")) {

                        errorTitle = "Rack Already Occupied";

                    }

                    Swal.fire({
                        icon: "error",
                        title: errorTitle,
                        text: res.message,
                        confirmButtonColor: "#be185d"
                    });

                }

            })

            .catch(() => {

                Swal.fire({
                    icon: "error",
                    title: "Server Error",
                    text: "Something went wrong.",
                    confirmButtonColor: "#be185d"
                });

            });

    };

</script>

<?php include 'includes/bottom-nav.php'; ?>