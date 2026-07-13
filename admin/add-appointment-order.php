<?php
session_start();
require_once '../includes/db.php';
require 'check-slot.php';
require 'find-next-slot.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$appointmentId = $_GET['id'] ?? null;
$isEdit = ctype_digit((string) $appointmentId)
    && (int) $appointmentId > 0;

$pageTitle = $isEdit
    ? 'Edit Appointment - Sogasu'
    : 'Add Appointment - Sogasu';

$headerTitle = $isEdit
    ? 'Edit Appointment'
    : 'Add Appointment';

$activePage = 'appointments';

$errors = [];
$appointment = null;

$categories = $pdo->query("
    SELECT id, category_name
    FROM categories
    WHERE status = 'active'
      AND is_deleted = 0
    ORDER BY category_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$subCategories = $pdo->query("
    SELECT id, category_id, name
    FROM sub_categories
    WHERE status = 'active'
      AND is_deleted = 0
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);


function validId($value): bool
{
    return ctype_digit((string) $value) && (int) $value > 0;
}

function getOrCreateCustomer(PDO $pdo, string $customerName, string $customerPhone): array
{
    $stmt = $pdo->prepare("
        SELECT id, username, mobile
        FROM users
        WHERE mobile = ?
          AND role = 'customer'
          AND status = 1
        LIMIT 1
    ");
    $stmt->execute([$customerPhone]);

    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($customer) {
        return $customer;
    }

    $stmt = $pdo->prepare("
        INSERT INTO users
        (
            username,
            mobile,
            role,
            status,
            is_registered
        )
        VALUES (?, ?, 'customer', 1, 0)
    ");

    $stmt->execute([
        $customerName,
        $customerPhone
    ]);

    $userId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare("
        INSERT INTO customers
        (
            user_id,
            first_name,
            phone,
            address,
            city,
            status
        )
        VALUES (?, ?, ?, '', '', 1)
    ");

    $stmt->execute([
        $userId,
        $customerName,
        $customerPhone
    ]);

    return [
        'id' => $userId,
        'username' => $customerName,
        'mobile' => $customerPhone
    ];
}
if ($isEdit) {
    $stmt = $pdo->prepare("
        SELECT
            id,
            user_id,
            customer_name,
            customer_phone,
            category_id,
            sub_category_id,
            appointment_date,
            appointment_time,
            visit_type,
            delivery_type,
            delivery_method,
            notes
        FROM appointments
        WHERE id = ?
          AND is_deleted = 0
        LIMIT 1
    ");

    $stmt->execute([
        (int) $appointmentId
    ]);

    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        $_SESSION['error'] = 'Appointment not found.';
        header('Location: appointments.php');
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerName = trim($_POST['customer_name'] ?? '');
    $customerPhone = trim($_POST['customer_phone'] ?? '');
    $categoryId = $_POST['category_id'] ?? '';
    $subCategoryId = $_POST['sub_category_id'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    $deliveryType = $_POST['delivery_type'] ?? 'normal';
    $deliveryMethod = $_POST['delivery_method'] ?? 'store_pickup';

    if ($customerName === '') {
        $errors[] = 'Customer name is required.';
    }

    if ($customerPhone === '') {
        $errors[] = 'Customer mobile number is required.';
    } elseif (!preg_match('/^[0-9]{10}$/', $customerPhone)) {
        $errors[] = 'Customer mobile number must contain exactly 10 digits.';
    }

    if (!validId($categoryId)) {
        $errors[] = 'Select a valid category.';
    }

    if (!validId($subCategoryId)) {
        $errors[] = 'Select a valid sub category.';
    }

    if (!in_array($deliveryType, ['normal', 'emergency'], true)) {
        $errors[] = 'Invalid delivery type.';
    }

    if (!in_array($deliveryMethod, ['store_pickup', 'home_delivery'], true)) {
        $errors[] = 'Invalid delivery method.';
    }

    $customer = null;

    if ($customerPhone !== '' && preg_match('/^[0-9]{10}$/', $customerPhone)) {
        $stmt = $pdo->prepare("
        SELECT id, username, mobile
        FROM users
        WHERE mobile = ?
          AND role = 'customer'
          AND status = 1
        LIMIT 1
    ");
        $stmt->execute([$customerPhone]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($customer) {
            $customerPhone = $customer['mobile'];
        }
    }

    if (validId($categoryId)) {
        $stmt = $pdo->prepare("
            SELECT id
            FROM categories
            WHERE id = ?
              AND status = 'active'
              AND is_deleted = 0
            LIMIT 1
        ");
        $stmt->execute([(int) $categoryId]);

        if (!$stmt->fetchColumn()) {
            $errors[] = 'Selected category is invalid.';
        }
    }

    if (validId($subCategoryId) && validId($categoryId)) {
        $stmt = $pdo->prepare("
            SELECT id
            FROM sub_categories
            WHERE id = ?
              AND category_id = ?
              AND status = 'active'
              AND is_deleted = 0
            LIMIT 1
        ");
        $stmt->execute([(int) $subCategoryId, (int) $categoryId]);

        if (!$stmt->fetchColumn()) {
            $errors[] = 'Selected sub category does not belong to the selected category.';
        }
    }
    $appointmentDate = $_POST['appointment_date'] ?? '';
    $appointmentTime = $_POST['appointment_time'] ?? '';
    $visitType = $_POST['visit_type'] ?? '';

    if ($appointmentDate === '') {
        $errors[] = 'Appointment date is required.';
    } elseif ($appointmentDate < date('Y-m-d')) {
        $errors[] = 'Past appointment date is not allowed.';
    }

    if ($appointmentTime === '') {
        $errors[] = 'Select appointment time.';
    } elseif (
        $appointmentDate !== ''
        && $appointmentDate === date('Y-m-d')
        && strtotime($appointmentDate . ' ' . $appointmentTime) <= time()
    ) {
        $errors[] = 'Past appointment time is not allowed.';
    }

    if (!in_array($visitType, ['home', 'store'], true)) {
        $errors[] = 'Select a valid visit type.';
    }

    /*
    |--------------------------------------------------------------------------
    | SUNDAY + HOLIDAY VALIDATION
    |--------------------------------------------------------------------------
    */

    if (!$errors && $appointmentDate !== '') {
        $selectedDay = date('w', strtotime($appointmentDate));

        if ($selectedDay == 0) {
            $errors[] = 'Appointments cannot be booked on Sundays.';
        } else {
            $holidayStmt = $pdo->prepare("
            SELECT *
            FROM holidays
            WHERE DATE(holiday_date) = ?
            LIMIT 1
        ");

            $holidayStmt->execute([
                $appointmentDate
            ]);

            $holiday = $holidayStmt->fetch(PDO::FETCH_ASSOC);

            if ($holiday) {
                $holidayType = ucfirst($holiday['type']);

                $errors[] =
                    $holidayType
                    . ' holiday: appointments are not allowed on this date.';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | BOUTIQUE TIMING VALIDATION
    |--------------------------------------------------------------------------
    */

    if (!$errors) {
        $timingStmt = $pdo->prepare("
        SELECT *
        FROM boutique_timing_settings
        WHERE effective_from <= ?
        ORDER BY effective_from DESC
        LIMIT 1
    ");

        $timingStmt->execute([
            $appointmentDate
        ]);

        $activeTiming = $timingStmt->fetch(PDO::FETCH_ASSOC);

        if (!$activeTiming) {
            $errors[] = 'Boutique appointment timings are not configured for the selected date.';
        } else {
            $selectedTime = strtotime($appointmentTime);
            $startLimit = strtotime($activeTiming['start_time']);
            $endLimit = strtotime($activeTiming['end_time']);

            if (
                $selectedTime < $startLimit ||
                $selectedTime >= $endLimit
            ) {
                $errors[] =
                    'Appointments allowed only between '
                    . date('h:i A', $startLimit)
                    . ' and '
                    . date('h:i A', $endLimit)
                    . '.';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SLOT CONFLICT VALIDATION
    |--------------------------------------------------------------------------
    */

    if (!$errors) {
        $normalisedTime = date(
            'H:i:s',
            strtotime($appointmentTime)
        );

        $conflict = checkSlotConflict(
            $pdo,
            $appointmentDate,
            $normalisedTime,
            $isEdit ? (int) $appointmentId : null
        );

        if ($conflict) {
            $nextSlot = findNextAvailableSlot(
                $pdo,
                $appointmentDate,
                $normalisedTime
            );

            if ($nextSlot) {
                $errors[] =
                    'Selected slot already booked. Try after '
                    . date('h:i A', strtotime($nextSlot))
                    . '.';
            } else {
                $errors[] =
                    'No slots available for selected date.';
            }
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $customer = getOrCreateCustomer(
                $pdo,
                $customerName,
                $customerPhone
            );

            $normalisedTime = date('H:i:s', strtotime($appointmentTime));

            $stmt = $pdo->prepare("
                SELECT id
                FROM appointments
                WHERE appointment_date = ?
                AND appointment_time = ?
                AND is_deleted = 0
                AND workflow_status NOT IN ('cancelled', 'rescheduled')
                AND (? IS NULL OR id != ?)
                LIMIT 1
                FOR UPDATE
            ");

            $excludeId = $isEdit ? (int) $appointmentId : null;

            $stmt->execute([
                $appointmentDate,
                $normalisedTime,
                $excludeId,
                $excludeId
            ]);

            if ($stmt->fetchColumn()) {
                $nextSlot = findNextAvailableSlot(
                    $pdo,
                    $appointmentDate,
                    $normalisedTime
                );

                if ($nextSlot) {
                    throw new RuntimeException(
                        'Selected slot already booked. Try after '
                        . date('h:i A', strtotime($nextSlot))
                        . '.'
                    );
                }

                throw new RuntimeException(
                    'No slots available for selected date.'
                );
            }

            if ($isEdit) {
                $stmt = $pdo->prepare("
        UPDATE appointments
        SET
            user_id = ?,
            customer_name = ?,
            customer_phone = ?,
            category_id = ?,
            sub_category_id = ?,
            appointment_date = ?,
            appointment_time = ?,
            visit_type = ?,
            delivery_type = ?,
            delivery_method = ?,
            notes = ?,
            updated_at = NOW()
        WHERE id = ?
          AND is_deleted = 0
    ");

                $stmt->execute([
                    (int) $customer['id'],
                    $customerName,
                    $customerPhone,
                    (int) $categoryId,
                    (int) $subCategoryId,
                    $appointmentDate,
                    $normalisedTime,
                    $visitType,
                    $deliveryType,
                    $deliveryMethod,
                    $notes,
                    (int) $appointmentId
                ]);
            } else {
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
                    delivery_type,
                    delivery_method,
                    appointment_source,
                    workflow_status,
                    status,
                    notes,
                    created_by,
                    created_at
                )
                VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    'admin',
                    'pending',
                    'scheduled',
                    ?, ?,
                    NOW()
                )
            ");

                $stmt->execute([
                    (int) $customer['id'],
                    $customerName,
                    $customerPhone,
                    (int) $categoryId,
                    (int) $subCategoryId,
                    $appointmentDate,
                    $normalisedTime,
                    $visitType,
                    $deliveryType,
                    $deliveryMethod,
                    $notes,
                    (int) $_SESSION['user_id']
                ]);
            }

            $pdo->commit();
            $_SESSION['appointment_success'] = $isEdit
                ? 'Appointment updated successfully.'
                : 'Appointment created successfully.';
            header('Location: appointments.php');
            exit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $e instanceof RuntimeException
                ? $e->getMessage()
                : ($isEdit
                    ? 'Unable to update appointment.'
                    : 'Unable to create appointment.');
        }
    }
}


include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="margin-bottom:1.5rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <div>
                <h2 style="font-size:1.5rem;font-weight:700;color:#1e293b;margin:0;">
                    <?= $isEdit ? 'Edit Appointment' : 'Add Appointment' ?>
                </h2>

                <p style="color:#64748b;margin:0.25rem 0 0;">
                    <?= $isEdit
                        ? 'Update the customer appointment details.'
                        : 'Create a customer appointment and schedule the visit.'
                        ?>
                </p>
            </div>

            <button type="button" class="btn" onclick="window.location.href='appointments.php'"
                style="background:white;border:1px solid #e2e8f0;color:#64748b;">
                <i class="ri-arrow-left-line"></i>
                Cancel
            </button>
        </div>
    </div>

    <div class="appointment-order-layout">
        <div class="appointment-order-main">

            <?php if ($errors): ?>
                <div style="padding:14px;border-radius:10px;background:#fff1f2;color:#9f1239;margin-bottom:18px;">
                    <?php foreach ($errors as $error): ?>
                        <div><?= htmlspecialchars($error) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="mainForm">

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">
                            Customer Name <span class="required-star">*</span>
                        </label>
                        <input type="text" name="customer_name" id="customerName" class="form-control" maxlength="150"
                            value="<?= htmlspecialchars($_POST['customer_name'] ?? $appointment['customer_name'] ?? '') ?>"
                            placeholder="Enter customer name">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Mobile Number <span class="required-star">*</span>
                        </label>
                        <input type="text" name="customer_phone" id="customerPhone" class="form-control" maxlength="10"
                            value="<?= htmlspecialchars($_POST['customer_phone'] ?? $appointment['customer_phone'] ?? '') ?>"
                            placeholder="Enter 10 digit mobile number">
                    </div>


                    <div class="form-group">
                        <label class="form-label">
                            Category <span class="required-star">*</span>
                        </label>

                        <select name="category_id" id="categoryId" class="form-control">
                            <option value="">Select Category</option>

                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['id'] ?>" <?= (string) ($_POST['category_id'] ?? $appointment['category_id'] ?? '') === (string) $category['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Sub Category <span class="required-star">*</span>
                        </label>
                        <select name="sub_category_id" id="subCategoryId" class="form-control">
                            <option value="">Select Sub Category</option>
                        </select>
                    </div>
                </div>
                <div id="appointmentSection">
                    <h3 class="section-title">Appointment Details</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">
                                Appointment Date <span class="required-star">*</span>
                            </label>
                            <input type="date" name="appointment_date" class="form-control" min="<?= date('Y-m-d') ?>"
                                value="<?= htmlspecialchars($_POST['appointment_date'] ?? $appointment['appointment_date'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                Appointment Time <span class="required-star">*</span>
                            </label>
                            <input type="time" name="appointment_time" class="form-control" value="<?= htmlspecialchars(
                                $_POST['appointment_time']
                                ?? $appointment['appointment_time']
                                ?? ''
                            ) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                Visit Type <span class="required-star">*</span>
                            </label>
                            <select name="visit_type" class="form-control">
                                <option value="">Select Visit Type</option>
                                <option value="home" <?= ($_POST['visit_type'] ?? $appointment['visit_type'] ?? '') === 'home' ? 'selected' : '' ?>>
                                    Home
                                </option>
                                <option value="store" <?= ($_POST['visit_type'] ?? $appointment['visit_type'] ?? '') === 'store' ? 'selected' : '' ?>>
                                    Store
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <h3 class="section-title">Delivery & Notes</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">
                            Delivery Type <span class="required-star">*</span>
                        </label>
                        <select name="delivery_type" class="form-control">
                            <option value="normal" <?= ($_POST['delivery_type'] ?? $appointment['delivery_type'] ?? 'normal') === 'normal' ? 'selected' : '' ?>>
                                Normal</option>
                            <option value="emergency" <?= ($_POST['delivery_type'] ?? $appointment['delivery_type'] ?? '') === 'emergency' ? 'selected' : '' ?>>
                                Emergency</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            Delivery Method <span class="required-star">*</span>
                        </label>
                        <select name="delivery_method" class="form-control">
                            <option value="store_pickup" <?= ($_POST['delivery_method'] ?? $appointment['delivery_method'] ?? 'store_pickup') === 'store_pickup' ? 'selected' : '' ?>>Store Pickup</option>
                            <option value="home_delivery" <?= ($_POST['delivery_method'] ?? $appointment['delivery_method'] ?? '') === 'home_delivery' ? 'selected' : '' ?>>Home
                                Delivery</option>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control"
                            rows="4"><?= htmlspecialchars($_POST['notes'] ?? $appointment['notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;margin-top:24px;">
                    <button type="submit" class="btn"
                        style="background:var(--primary);color:#fff;border:none;padding:12px 24px;">
                        <i class="ri-save-line"></i>
                        <?= $isEdit ? 'Update Appointment' : 'Create Appointment' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<style>
    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px
    }

    .form-group.full {
        grid-column: 1/-1
    }

    .section-title {
        font-size: 1rem;
        margin: 24px 0 14px;
        color: #334155;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 10px
    }

    @media(max-width:768px) {
        .form-grid {
            grid-template-columns: 1fr
        }

        .form-group.full {
            grid-column: auto
        }
    }

    .appointment-order-layout {
        width: 100%;
    }

    .appointment-order-main {
        width: 100%;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 1.5rem;
        box-sizing: border-box;
    }

    .required-star {
        color: #dc2626;
        font-weight: 700;
    }

    #mainForm {
        width: 100%;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }

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

    .form-control {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 0.95rem;
        outline: none;
        box-sizing: border-box;
        font-family: inherit;
    }

    .form-control:focus {
        border-color: var(--primary);
    }

    .form-group.full {
        grid-column: 1 / -1;
    }

    .section-title {
        font-size: 1rem;
        font-weight: 600;
        margin: 1.5rem 0 1rem;
        color: #334155;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 0.75rem;
    }

    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }

        .form-group.full {
            grid-column: auto;
        }

        .appointment-order-main {
            padding: 1rem;
        }
    }
</style>

<script>
    const subCategories = <?= json_encode($subCategories, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    const oldSubCategory = <?= json_encode(
        (string) ($_POST['sub_category_id'] ?? $appointment['sub_category_id'] ?? '')
    ) ?>;

    function loadSubCategories() {
        const categoryId = document.getElementById('categoryId').value;
        const select = document.getElementById('subCategoryId');
        const current = select.value || oldSubCategory;
        select.innerHTML = '<option value="">Select Sub Category</option>';

        subCategories.filter(item => String(item.category_id) === String(categoryId)).forEach(item => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.name;
            option.selected = String(item.id) === String(current);
            select.appendChild(option);
        });
    }
    document.getElementById('categoryId').addEventListener('change', loadSubCategories);

    loadSubCategories();
</script>

<?php include 'includes/footer.php'; ?>