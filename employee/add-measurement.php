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
            'update_measurements',
            'change_rack'
        ];

        if (!in_array($action, $allowedActions, true)) {
            throw new Exception('Invalid action.');
        }

        $rackId = intval($_POST['rack_id'] ?? 0);
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
        /*
        |--------------------------------------------------------------------------
        | Assign Rack And Create Order
        |--------------------------------------------------------------------------
        */
        if ($action === 'create_order' && $rackId <= 0) {

            throw new Exception(
                'RACK_REQUIRED|Please choose a rack.'
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
                    supervisor_id,
                    assigned_employee_id,
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
                    ?, ?, ?,
                    0.00,
                    0.00,
                    0.00,
                    0.00,
                    NULL,
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
                $lockedAppointment['supervisor_id'],
                $lockedAppointment['assigned_employee_id'],
                $rackId
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

        $stmt = $pdo->prepare("
            UPDATE racks
            SET status = 'Occupied'
            WHERE id = ?
        ");

        $stmt->execute([$rackId]);

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

        <form id="measurementForm" method="POST">

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

                    <button type="button" id="assignRackBtn" class="assign-rack-btn" onclick="openRackModal()">

                        <i class="ri-stack-line"></i>

                        <span>Assign Rack</span>

                    </button>

                <?php else: ?>

                    <button type="button" id="updateMeasurementBtn" class="assign-rack-btn">

                        <i class="ri-edit-line"></i>

                        <span>Update Measurements</span>

                    </button>

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
</style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
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