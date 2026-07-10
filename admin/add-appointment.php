<?php
session_start();
require_once '../includes/db.php';
require 'check-slot.php';
require 'find-next-slot.php';

$errors = [];
$old = [];
$id = $_GET['id'] ?? null;
$source = $_GET['source'] ?? 'appointments';

if ($id) {

    if ($source == 'customer_orders') {

        $stmt = $pdo->prepare("
            SELECT
                co.*,
                u.username AS customer_name,
                u.mobile AS customer_phone,
                'order_booking' AS type,
                co.order_code AS order_id
            FROM customer_orders co
            LEFT JOIN users u
                ON u.id = co.user_id
            WHERE co.id = ?
            AND co.is_deleted = 0
        ");

    } else {

        $stmt = $pdo->prepare("
            SELECT *
            FROM appointments
            WHERE id = ?
            AND is_deleted = 0
        ");

    }

    $stmt->execute([$id]);

    $appointment = $stmt->fetch();

    if (!$appointment) {
        die("Appointment not found");
    }

    $old = $appointment;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['user_id'])) {
        die("Unauthorized");
    }

    $current_user_id = $_SESSION['user_id'];

    // GET DATA
    $old['customer_name'] = trim($_POST['customer_name'] ?? '');
    $old['customer_phone'] = trim($_POST['customer_phone'] ?? '');
    $old['appointment_date'] = $_POST['appointment_date'] ?? '';
    $old['appointment_time'] = !empty($_POST['appointment_time'])
        ? date('H:i:s', strtotime($_POST['appointment_time']))
        : '';
    $old['type'] = $_POST['type'] ?? '';
    $old['notes'] = trim($_POST['notes'] ?? '');
    $old['order_id'] = htmlspecialchars(trim($_POST['order_id'] ?? ''));
    $old['status'] = $_POST['status'] ?? 'scheduled';
    // ===== VALIDATIONS =====

    if ($old['customer_name'] == '') {
        $errors['customer_name'] = "Customer name required";
    } elseif (!preg_match("/^[a-zA-Z .'-]+$/", $old['customer_name'])) {
        $errors['customer_name'] = "Only letters allowed";
    }

    if ($old['customer_phone'] != '') {
        if (!ctype_digit($old['customer_phone']) || strlen($old['customer_phone']) != 10) {
            $errors['customer_phone'] = "Invalid phone number";
        }
    }

    if ($old['appointment_date'] == '') {
        $errors['appointment_date'] = "Date required";
    } elseif ($old['appointment_date'] < date('Y-m-d')) {
        $errors['appointment_date'] = "Past date not allowed";
    }


    if ($old['appointment_time'] == '') {
        $errors['appointment_time'] = "Time required";
    }

    if ($old['type'] == '') {
        $errors['type'] = "Select appointment type";
    } else {
        $allowedTypes = ['measurements', 'trial', 'consultation', 'delivery_pickup'];
        if (!in_array($old['type'], $allowedTypes)) {
            $errors['type'] = "Invalid type selected";
        }
    }
    $allowedStatus = ['scheduled', 'confirmed', 'tentative'];

    if (!in_array($old['status'], $allowedStatus)) {
        $old['status'] = 'scheduled';
    }
    /*
|--------------------------------------------------------------------------
| SUNDAY + HOLIDAY VALIDATION
|--------------------------------------------------------------------------
*/

    if (empty($errors) && !empty($old['appointment_date'])) {

        $selectedDay = date('w', strtotime($old['appointment_date']));

        /*
        |--------------------------------------------------------------------------
        | BLOCK ALL SUNDAYS
        |--------------------------------------------------------------------------
        */

        if ($selectedDay == 0) {

            $errors['appointment_date'] =
                "Appointments cannot be booked on Sundays";

        } else {

            /*
            |--------------------------------------------------------------------------
            | CHECK FIXED HOLIDAYS
            |--------------------------------------------------------------------------
            */

            $holidayStmt = $pdo->prepare("
            SELECT *
            FROM holidays
            WHERE DATE(holiday_date) = ?
            LIMIT 1
        ");

            $holidayStmt->execute([
                $old['appointment_date']
            ]);

            $holiday = $holidayStmt->fetch();

            if ($holiday) {

                $holidayType = ucfirst($holiday['type']);

                $errors['appointment_date'] =
                    $holidayType .
                    " holiday: appointments are not allowed on this date";
            }
        }
    }
    /*
|--------------------------------------------------------------------------
| BOUTIQUE TIMING VALIDATION
|--------------------------------------------------------------------------
*/

    if (empty($errors)) {

        $timingStmt = $pdo->prepare("
        SELECT *
        FROM boutique_timing_settings
        WHERE effective_from <= ?
        ORDER BY effective_from DESC
        LIMIT 1
    ");

        $timingStmt->execute([
            $old['appointment_date']
        ]);

        $activeTiming = $timingStmt->fetch();

        if ($activeTiming) {

            $selectedTime = strtotime(
                $old['appointment_time']
            );

            $startLimit = strtotime(
                $activeTiming['start_time']
            );

            $endLimit = strtotime(
                $activeTiming['end_time']
            );

            /*
            |--------------------------------------------------------------------------
            | VALIDATE SLOT INSIDE BOUTIQUE HOURS
            |--------------------------------------------------------------------------
            */

            if (
                $selectedTime < $startLimit ||
                $selectedTime >= $endLimit
            ) {

                $errors['appointment_time'] =
                    "Appointments allowed only between "
                    . date('h:i A', $startLimit)
                    . " and "
                    . date('h:i A', $endLimit);
            }
        }
    }
    if (empty($errors)) {

        $conflict = checkSlotConflict(
            $pdo,
            $old['appointment_date'],
            $old['appointment_time'],
            $id ?? null
        );

        if ($conflict) {

            $nextSlot = findNextAvailableSlot(
                $pdo,
                $old['appointment_date'],
                $old['appointment_time']
            );

            if ($nextSlot) {

                $errors['appointment_time'] =
                    "Selected slot already booked. Try after "
                    . date('h:i A', strtotime($nextSlot));

            } else {

                $errors['appointment_time'] =
                    "No slots available for selected date";

            }
        }
    }

    // ===== INSERT / UPDATE =====
    if (empty($errors)) {

        if ($id) {

            $phone = $old['customer_phone'] ?: null;

            if ($source == 'customer_orders') {

                $stmt = $pdo->prepare("
            UPDATE customer_orders
            SET
                appointment_date = ?,
                appointment_time = ?,
                status = ?,
                updated_at = NOW(),
                updated_by = ?
            WHERE id = ?
        ");

                $stmt->execute([
                    $old['appointment_date'],
                    $old['appointment_time'],
                    $old['status'],
                    $current_user_id,
                    $id
                ]);

            } else {

                $stmt = $pdo->prepare("
            UPDATE appointments SET
            customer_name=?, customer_phone=?, appointment_date=?, appointment_time=?,
            type=?, notes=?, order_id=?, status=?,
            updated_at=NOW(), updated_by=?
            WHERE id=?
        ");

                $stmt->execute([
                    $old['customer_name'],
                    $phone,
                    $old['appointment_date'],
                    $old['appointment_time'],
                    $old['type'],
                    $old['notes'],
                    $old['order_id'],
                    $old['status'],
                    $current_user_id,
                    $id
                ]);

            }

            $_SESSION['success'] = "Appointment updated successfully";
        } else {

            $phone = $old['customer_phone'] ?: null;

            $stmt = $pdo->prepare("
                INSERT INTO appointments
                    (customer_name, customer_phone, appointment_date, appointment_time,
                    type, notes, order_id, appointment_source, status, created_at, created_by)
                    VALUES (?,?,?,?,?,?,?,'admin',?,NOW(),?)
            ");

            $stmt->execute([
                $old['customer_name'],
                $phone,
                $old['appointment_date'],
                $old['appointment_time'],
                $old['type'],
                $old['notes'],
                $old['order_id'],
                $old['status'],
                $current_user_id
            ]);
            $_SESSION['success'] = "Appointment created successfully";
        }

        header("Location: appointments.php");
        exit;
    }
}
$pageTitle = "New Appointment - Sogasu";
$activePage = "appointments";
include 'includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<main class="main-content">
    <header class="top-header"
        style="justify-content: space-between; gap: 1rem; flex-wrap: nowrap; align-items: center;">
        <i class="ri-menu-line mobile-toggle" onclick="toggleSidebar()"></i>
        <div style="flex: 1;"></div>
        <div class="user-profile-corner" style="display: flex; align-items: center; gap: 1.5rem; margin-bottom: 0;">
            <div style="display: flex; gap: 0.75rem;">
                <button
                    style="border: none; background: transparent; color: #94a3b8; font-size: 1.2rem; cursor: pointer;"><i
                        class="ri-notification-3-line"></i></button>
                <button
                    style="border: none; background: transparent; color: #94a3b8; font-size: 1.2rem; cursor: pointer;"><i
                        class="ri-settings-line"></i></button>
            </div>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div class="profile-info" style="text-align: right;">
                    <div class="profile-name" style="font-weight: 700; color: #0f172a; font-size: 0.9rem;">Sushmita
                    </div>
                    <div class="profile-role" style="font-size: 0.75rem; color: #64748b; text-transform: uppercase;">
                        Admin</div>
                </div>
                <img src="https://ui-avatars.com/api/?name=Sushmita+A&background=random"
                    style="width: 40px; height: 40px; border-radius: 50%;">
            </div>
        </div>
    </header>

    <div style="margin-bottom: 1rem;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">
                    <?= $id ? 'Edit Appointment' : 'New Appointment' ?>
                </h2>
                <p class="text-muted">Schedule a trial, measurement, or consultation.</p>
            </div>
            <button class="btn" onclick="history.back()"
                style="background: white; border: 1px solid #e2e8f0; color: #64748b;">
                <i class="ri-arrow-left-line"></i> Back
            </button>
        </div>
    </div>

    <form method="POST" style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">

        <!-- Left Column: Appointment Details -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">

            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Schedule Details
                </h3>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Customer Name <span style="color:red">*</span></label>
                    <div style="position: relative;">
                        <i class="ri-search-line"
                            style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                        <input type="text" name="customer_name" value="<?= $old['customer_name'] ?? '' ?>"
                            class="form-control" style="padding-left: 2.5rem;"
                            placeholder="Search existing customer...">
                        <?php if (isset($errors['customer_name'])): ?>
                            <small style="color:red"><?= $errors['customer_name'] ?></small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Customer Phone (Optional if selected above)</label>
                    <input type="tel" name="customer_phone" maxlength="10" inputmode="numeric"
                        value="<?= $old['customer_phone'] ?? '' ?>" class="form-control" placeholder="10-digit number">
                    <?php if (isset($errors['customer_phone'])): ?>
                        <small style="color:red"><?= $errors['customer_phone'] ?></small>
                    <?php endif; ?>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Date <span style="color:red">*</span></label>
                        <input type="date" name="appointment_date"
                            value="<?= $old['appointment_date'] ?? date('Y-m-d') ?>" class="form-control">
                        <?php if (isset($errors['appointment_date'])): ?>
                            <small style="color:red"><?= $errors['appointment_date'] ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Time <span style="color:red">*</span></label>
                        <input type="time" name="appointment_time"
                            value="<?= !empty($old['appointment_time']) ? date('H:i', strtotime($old['appointment_time'])) : '' ?>"
                            class="form-control">
                        <?php if (isset($errors['appointment_time'])): ?>
                            <small style="color:red"><?= $errors['appointment_time'] ?></small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Purpose / Type <span style="color:red">*</span></label>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                        <label class="radio-card">
                            <input type="radio" name="type" value="measurements" <?= ($old['type'] ?? '') == 'measurements' ? 'checked' : '' ?>>
                            <i class="ri-ruler-line"></i>
                            <span>Measurements</span>
                        </label>
                        <label class="radio-card">
                            <input type="radio" name="type" value="trial" <?= ($old['type'] ?? '') == 'trial' ? 'checked' : '' ?>>
                            <i class="ri-t-shirt-line"></i>
                            <span>Trial</span>
                        </label>
                        <label class="radio-card">
                            <input type="radio" name="type" value="consultation" <?= ($old['type'] ?? '') == 'consultation' ? 'checked' : '' ?>>
                            <i class="ri-discuss-line"></i>
                            <span>Consultation</span>
                        </label>
                        <label class="radio-card">
                            <input type="radio" name="type" value="delivery_pickup" <?= ($old['type'] ?? '') == 'delivery_pickup' ? 'checked' : '' ?>>
                            <i class="ri-truck-line"></i>
                            <span>Delivery/Pickup</span>
                        </label>
                        <?php if (isset($errors['type'])): ?>
                            <small style="color:red"><?= $errors['type'] ?></small>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Additional Notes
                </h3>

                <div class="form-group">
                    <label class="form-label">Message / Details</label>
                    <textarea name="notes" class="form-control" rows="3"
                        placeholder="Any specific instructions for this appointment..."><?= $old['notes'] ?? '' ?></textarea>

                </div>
            </div>

        </div>

        <!-- Right Column: Linking & Actions -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">

            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Link to Order</h3>

                <div class="form-group">
                    <label class="form-label">Order ID (Optional)</label>
                    <input type="text" name="order_id" value="<?= htmlspecialchars($old['order_id'] ?? '') ?>"
                        class="form-control" placeholder="e.g. #ORD-2458">
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">Links this visit to a specific
                        order</div>
                </div>

            </div>

            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Status</h3>
                <div class="form-group">
                    <label class="form-label">Appointment Status</label>
                    <select name="status">
                        <option value="scheduled" <?= ($old['status'] ?? '') == 'scheduled' ? 'selected' : '' ?>>Scheduled
                        </option>
                        <option value="confirmed" <?= ($old['status'] ?? '') == 'confirmed' ? 'selected' : '' ?>>Confirmed
                        </option>
                        <option value="tentative" <?= ($old['status'] ?? '') == 'tentative' ? 'selected' : '' ?>>Tentative
                        </option>
                    </select>
                </div>
            </div>

            <!-- Actions -->
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Actions</h3>
                <button type="submit" class="btn btn-primary w-full"
                    style="justify-content: center; width: 100%; margin-bottom: 1rem;"><?= $id ? 'Update Appointment' : 'Create Appointment' ?></button>
                <button type="button" onclick="history.back()" class="btn w-full">
                    Cancel
                </button>
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

    .radio-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        color: #64748b;
        text-align: center;
    }

    .radio-card i {
        font-size: 1.25rem;
    }

    .radio-card span {
        font-size: 0.75rem;
        font-weight: 500;
    }

    .radio-card input {
        display: none;
    }

    .radio-card:hover {
        background: #f8fafc;
        border-color: var(--primary);
        color: var(--primary);
    }

    .radio-card:has(input:checked),
    .radio-card.selected {
        background: #eef2ff;
        border-color: var(--primary);
        color: var(--primary);
        font-weight: 600;
    }
</style>

<script>
    // Simple script to toggle selected class for radio buttons styling
    const methods = document.querySelectorAll('.radio-card');
    methods.forEach(method => {
        method.addEventListener('click', () => {
            // Only remove from others if this is a radio group behavior
            const groupName = method.querySelector('input').name;
            document.querySelectorAll(`input[name="${groupName}"]`).forEach(input => {
                input.closest('.radio-card').classList.remove('selected');
            });
            method.classList.add('selected');
        });
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('input[name="type"]:checked').forEach(el => {
            el.closest('.radio-card').classList.add('selected');
        });
    });
</script>
<script>
    document.querySelector('input[name="customer_phone"]').addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 10);
    });
</script>
<?php if (!empty($errors['appointment_date'])): ?>

    <script>

        document.addEventListener('DOMContentLoaded', function () {

            Swal.fire({
                icon: 'warning',
                title: 'Booking Not Allowed',
                text: '<?= addslashes($errors['appointment_date']) ?>',
                confirmButtonColor: '#ef4444'
            });

        });

    </script>

<?php endif; ?>
<?php include 'includes/footer.php'; ?>