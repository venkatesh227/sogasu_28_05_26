<?php
session_start();
require_once '../includes/db.php';
$selected_date = $_GET['date'] ?? date('Y-m-d');

// validate date format
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $selected_date)) {
    $selected_date = date('Y-m-d');
}

// prevent past date
if (strtotime($selected_date) < strtotime(date('Y-m-d'))) {
    $selected_date = date('Y-m-d');
}

// Handle supervisor assignment to appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_supervisor') {
    $appointment_id = $_POST['appointment_id'] ?? null;
    $supervisor_id = $_POST['supervisor_id'] ?: null;

    if ($appointment_id) {
        try {
            $stmt = $pdo->prepare("
                UPDATE appointments
                SET
                    supervisor_id = ?,
                    workflow_status='supervisor_assigned',
                    updated_at=?
                WHERE id=?
            ");
            $stmt->execute([
                $supervisor_id,
                date('Y-m-d H:i:s'),
                $appointment_id
            ]);
            $_SESSION['success'] = "Supervisor assigned successfully";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
    }
    header("Location: appointments.php?date=" . $selected_date);
    exit();
}
// Fetch all supervisors
$supervisors = $pdo->query("
    SELECT id, CONCAT(first_name, ' ', last_name) as name 
    FROM employees 
    WHERE is_deleted=0 AND job_role = 'Supervisor'
    ORDER BY first_name
")->fetchAll(PDO::FETCH_ASSOC);

// Get selected supervisor
$selected_supervisor = $_GET['supervisor'] ?? null;
$query = "
SELECT
    a.id,
    a.customer_name,
    a.customer_phone,
    a.appointment_date,
    a.appointment_time,
    a.visit_type,
    a.status,
    a.cancel_reason,
    a.workflow_status,
a.appointment_source,
a.user_id,
    a.supervisor_id,
    a.assigned_employee_id,
    a.sub_category_id,
    sc.name AS garment,
    sup.first_name AS sup_first,
    sup.last_name AS sup_last,
    emp.first_name AS emp_first,
    emp.last_name AS emp_last
FROM appointments a
LEFT JOIN sub_categories sc
    ON sc.id = a.sub_category_id
LEFT JOIN employees sup
    ON sup.id = a.supervisor_id
LEFT JOIN employees emp
    ON emp.id = a.assigned_employee_id
WHERE
    a.is_deleted = 0
";

if ($selected_supervisor) {
    $query .= " AND a.supervisor_id=" . intval($selected_supervisor);
}
$query .= " AND a.appointment_date = :selected_date";

$query .= " ORDER BY a.appointment_date DESC,a.appointment_time ASC";
$stmt = $pdo->prepare($query);
$stmt->bindValue(':selected_date', $selected_date);
$stmt->execute();
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch booked dates for calendar highlight
$bookedStmt = $pdo->prepare("
SELECT DISTINCT appointment_date
FROM appointments
WHERE is_deleted = 0
");
$bookedStmt->execute();
$bookedDates = $bookedStmt->fetchAll(PDO::FETCH_COLUMN);

// Get all employees for assignment (excluding supervisors)
$all_employees = $pdo->query("
    SELECT id, CONCAT(first_name, ' ', last_name) as name 
    FROM employees 
    WHERE is_deleted=0 
    AND status = 1
    AND job_role != 'Supervisor'
    ORDER BY first_name
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Appointments - Sogasu";
$activePage = "appointments";
include 'includes/header.php';
?>

<main class="main-content">
    <header class="top-header"
        style="justify-content: space-between; gap: 1rem; flex-wrap: nowrap; align-items: center;">
        <i class="ri-menu-line mobile-toggle" onclick="toggleSidebar()"></i>

        <!-- Left Section: Search & Filters -->
        <div style="display: flex; align-items: center; gap: 1rem; flex: 1;">
            <div class="search-bar" style="width: 220px; min-width: 200px;">
                <i class="ri-search-line"></i>
                <input type="text" placeholder="Search appointments...">
            </div>
        </div>

        <!-- Right Section: User Profile -->
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

    <div style="margin-bottom: 2rem;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Appointments</h2>
                <p class="text-muted">Manage trials, consultations and measurements</p>
            </div>
            <button class="btn btn-primary" onclick="window.location.href='add-appointment-order.php'"><i
                    class="ri-calendar-check-line"></i> New Appointment</button>
        </div>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>

        <script>
            document.addEventListener('DOMContentLoaded', function () {

                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: '<?= addslashes($_SESSION['success']) ?>',
                    confirmButtonColor: '#6366f1'
                });

            });
        </script>

        <?php unset($_SESSION['success']); endif; ?>


    <?php if (!empty($_SESSION['error'])): ?>

        <script>
            document.addEventListener('DOMContentLoaded', function () {

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: '<?= addslashes($_SESSION['error']) ?>',
                    confirmButtonColor: '#ef4444'
                });

            });
        </script>

        <?php unset($_SESSION['error']); endif; ?>

    <!-- Supervisor Filter -->
    <div
        style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 250px;">
                <!-- <label style="font-size: 0.85rem; font-weight: 600; color: #475569; display: block; margin-bottom: 0.5rem;">
                    <i class="ri-user-star-line" style="margin-right: 0.5rem;"></i>Filter by Supervisor
                </label> -->
                <select id="supervisorFilter"
                    onchange="window.location.href='appointments.php?supervisor=' + this.value + '&date=<?= $selected_date ?>'"
                    style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; outline: none;">
                    <option value="">-- All Appointments --</option>
                    <?php foreach ($supervisors as $sup): ?>
                        <option value="<?= $sup['id'] ?>" <?= $selected_supervisor == $sup['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sup['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($selected_supervisor): ?>
                <a href="appointments.php?date=<?= $selected_date ?>"
                    style="padding: 0.75rem 1.25rem; background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; border-radius: 8px; text-decoration: none; font-weight: 600; align-self: flex-end;">
                    <i class="ri-close-line"></i> Clear Filter
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 3fr 1fr; gap: 1.5rem;">

        <!-- Appointments List -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">

            <!-- Section Header -->
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b;">Upcoming Schedule</h3>
                <div style="display: flex; gap: 0.5rem;">
                    <button class="btn-filter active">Upcoming</button>
                    <button class="btn-filter">History</button>
                </div>
            </div>
            <?php if (!empty($appointments)): ?>

                <?php foreach ($appointments as $row): ?>
                    <div class="appointment-card">
                        <div class="appointment-date-box">
                            <div class="month"><?= strtoupper(date('M', strtotime($row['appointment_date']))) ?></div>
                            <div class="day"><?= date('d', strtotime($row['appointment_date'])) ?></div>
                        </div>

                        <div style="flex: 1;">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div>
                                    <h4 class="appointment-title">
                                        <?= htmlspecialchars($row['customer_name']) ?> -
                                        <?= htmlspecialchars($row['garment'] ?? 'General') ?>
                                    </h4>

                                    <div class="appointment-meta">
                                        <span>
                                            <i class="ri-time-line"></i>
                                            <?= date('h:i A', strtotime($row['appointment_time'])) ?>
                                        </span>
                                        <span>
                                            <i class="ri-phone-line"></i>
                                            <?= htmlspecialchars($row['customer_phone'] ?: 'No phone') ?>
                                        </span>
                                        <span>
                                            <i class="ri-tag-line"></i>
                                            <?= htmlspecialchars($row['visit_type'] ?? 'Appointment') ?>
                                        </span>
                                    </div>

                                    <!-- Supervisor & Employee Info -->
                                    <div style="margin-top: 0.5rem; display: flex; gap: 1rem; flex-wrap: wrap;">
                                        <?php if ($row['supervisor_id']): ?>
                                            <span
                                                style="background: #ecfdf5; color: #059669; padding: 0.25rem 0.75rem; border-radius: 6px; font-size: 0.8rem; font-weight: 600;">
                                                <i class="ri-user-star-line"></i>
                                                <?= htmlspecialchars($row['sup_first'] . ' ' . ($row['sup_last'] ?? '')) ?>
                                            </span>
                                        <?php else: ?>
                                            <span
                                                style="background: #fef3c7; color: #b45309; padding: 0.25rem 0.75rem; border-radius: 6px; font-size: 0.8rem; font-weight: 600;">
                                                <i class="ri-alert-line"></i> No Supervisor
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($row['assigned_employee_id']): ?>
                                            <span
                                                style="background: #f0fdf4; color: #166534; padding: 0.25rem 0.75rem; border-radius: 6px; font-size: 0.8rem; font-weight: 600;">
                                                <i class="ri-user-3-line"></i>
                                                <?= htmlspecialchars($row['emp_first'] . ' ' . ($row['emp_last'] ?? '')) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div style="display:flex;align-items:center;gap:10px;">
                                    <?php if (($row['appointment_source'] ?? '') === 'customer') { ?>

                                        <span class="badge badge-primary">
                                            Customer Appointment
                                        </span>

                                    <?php } else { ?>

                                        <span class="badge badge-primary">
                                            Appointment
                                        </span>

                                    <?php } ?>

                                    <!-- 3 DOT MENU -->
                                    <button class="btn-icon-only action-btn" data-id="<?= $row['id'] ?>"
                                        data-source="appointments"
                                        style="border:none;background:none;cursor:pointer;padding:5px;">

                                        <i class="ri-more-2-fill" style="font-size:20px;"></i>

                                    </button>

                                </div>
                            </div>
                        </div>

                        <div style="display: flex; gap: 0.5rem; flex-direction: column;">

                            <?php if (($row['visit_type'] ?? '') !== 'store'): ?>

                                <button
                                    onclick="openSupervisorModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['customer_name']) ?>', <?= $row['supervisor_id'] ?? 'null' ?>)"
                                    class="btn btn-sm"
                                    style="background:#f8fafc;color:#f59e0b;border:1px solid #e2e8f0;padding:5px 10px;border-radius:6px;cursor:pointer;font-size:0.8rem;text-decoration:none;display:inline-flex;align-items:center;gap:0.3rem;white-space:nowrap;">
                                    <i class="ri-user-star-line"></i>
                                    <?= $row['supervisor_id'] ? 'Change Supervisor' : 'Assign Supervisor' ?>
                                </button>

                            <?php endif; ?>

                            <?php if ($row['status'] == 'cancelled'): ?>

                                <span style="
                                background:#fee2e2;
                                color:#dc2626;
                                padding:5px 10px;
                                border-radius:6px;
                                font-size:13px;
                                font-weight:600;
                                display:inline-block;">
                                    Cancelled
                                </span>

                                <div style="
                                    background:#fff7ed;
                                    border-left:4px solid #f97316;
                                    padding:8px 10px;
                                    border-radius:6px;
                                    font-size:13px;
                                    color:#374151;">
                                    <strong>Reason:</strong><br>
                                    <?= htmlspecialchars($row['cancel_reason']) ?>
                                </div>

                            <?php endif; ?>

                        </div>
                    </div>
                <?php endforeach; ?>

            <?php else: ?>

                <div
                    style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 3rem; text-align: center; color: #64748b;">
                    <i class="ri-calendar-blank-line"
                        style="font-size: 3rem; color: #cbd5e1; display: block; margin-bottom: 1rem;"></i>
                    <p style="font-size: 1.1rem; font-weight: 600;">No appointments found</p>
                    <p style="font-size: 0.9rem; margin-top: 0.5rem;">
                        <?= $selected_supervisor ? 'This supervisor has no appointments.' : 'No appointments found for the selected date.' ?>
                    </p>
                </div>

            <?php endif; ?>
        </div>

        <!-- Sidebar: Calendar & Stats -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">

            <!-- Calendar Widget -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h3 style="font-size: 1rem; font-weight: 700; color: #1e293b;">
                        <?= date('F Y', strtotime($selected_date)) ?>
                    </h3>
                    <div style="display: flex; gap: 0.5rem;">
                        <i class="ri-arrow-left-s-line" style="cursor: pointer;"></i>
                        <i class="ri-arrow-right-s-line" style="cursor: pointer;"></i>
                    </div>
                </div>
                <div class="calendar-grid">
                    <div class="day-name">S</div>
                    <div class="day-name">M</div>
                    <div class="day-name">T</div>
                    <div class="day-name">W</div>
                    <div class="day-name">T</div>
                    <div class="day-name">F</div>
                    <div class="day-name">S</div>

                    <?php
                    $daysInMonth = date('t', strtotime($selected_date));
                    $currentMonth = date('Y-m', strtotime($selected_date));
                    $firstDayOfMonth = date(
                        'w',
                        strtotime($currentMonth . '-01')
                    );
                    ?>

                    <?php for ($i = 0; $i < $firstDayOfMonth; $i++): ?>
                        <div class="day empty"></div>
                    <?php endfor; ?>



                    <?php for ($d = 1; $d <= $daysInMonth; $d++):

                        $date = $currentMonth . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
                        $isPast = false;
                        ?>

                        <div class="day 
                        <?= ($date == $selected_date) ? 'active' : '' ?> 
                        <?= in_array($date, $bookedDates) ? 'has-booking' : '' ?>
                        <?= $isPast ? 'empty' : '' ?>" onclick="window.location.href='?date=<?= $date ?>'">
                            <?= $d ?>
                        </div>

                    <?php endfor; ?>
                </div>
                <div style="margin-top: 1rem; font-size: 0.75rem; color: #64748b;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                        <span style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%;"></span>
                        Booked Slots
                    </div>
                </div>
            </div>

            <!-- Today's Summary -->
            <div
                style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem; text-align: center;">
                <h3
                    style="font-size: 0.9rem; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;">
                    Today's Count</h3>
                <div style="font-size: 3.5rem; font-weight: 700; color: var(--primary); line-height: 1;"><?= count(array_filter($appointments, function ($a) use ($selected_date) {
                    return $a['appointment_date'] == $selected_date;
                })) ?></div>
                <div style="color: #475569; font-size: 0.9rem; margin-top: 0.5rem;">Appointments Scheduled</div>
            </div>

        </div>

    </div>

</main>

<style>
    .appointment-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 1.25rem;
        display: flex;
        gap: 1.25rem;
        align-items: center;
        transition: box-shadow 0.2s;
    }

    .appointment-card:hover {
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }

    .appointment-date-box {
        text-align: center;
        padding: 0.75rem 0.5rem;
        background: #f8fafc;
        border-radius: 8px;
        min-width: 70px;
        border: 1px solid #f1f5f9;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .appointment-date-box .month {
        font-size: 0.75rem;
        color: #64748b;
        text-transform: uppercase;
        font-weight: 600;
        margin-bottom: 0.25rem;
    }

    .appointment-date-box .day {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
        line-height: 1;
    }

    .appointment-title {
        font-size: 1.05rem;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 0.35rem;
    }

    .appointment-meta {
        font-size: 0.85rem;
        color: #64748b;
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .appointment-meta span {
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }

    .badge {
        padding: 0.25rem 0.75rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .badge-blue {
        background: #eef2ff;
        color: #4338ca;
    }

    .badge-orange {
        background: #fff7ed;
        color: #c2410c;
    }

    .badge-green {
        background: #f0fdf4;
        color: #15803d;
    }

    .btn-icon-only {
        background: transparent;
        border: none;
        color: #94a3b8;
        cursor: pointer;
        padding: 0.5rem;
        border-radius: 50%;
        transition: background 0.2s;
        align-self: flex-start;
    }

    .btn-icon-only:hover {
        background: #f1f5f9;
        color: #475569;
    }

    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 4px;
        text-align: center;
    }

    .day-name {
        font-size: 0.7rem;
        font-weight: 700;
        color: #94a3b8;
        padding-bottom: 0.5rem;
    }

    .day {
        font-size: 0.8rem;
        padding: 0.5rem 0;
        border-radius: 4px;
        color: #475569;
        cursor: pointer;
    }

    .day:hover {
        background: #f1f5f9;
    }

    .day.active {
        background: var(--primary);
        color: white !important;
    }

    .day.has-booking:not(.active) {
        font-weight: 700;
        color: var(--primary);
        text-decoration: underline;
    }

    .day.empty {
        cursor: default;
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php if (!empty($_SESSION['success'])): ?>
    <script>
        let msg = "Appointment created";

        if ("<?= $_SESSION['success'] ?>" === "updated") msg = "Appointment updated";
        if ("<?= $_SESSION['success'] ?>" === "deleted") msg = "Appointment deleted";

        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: msg
        });
    </script>
    <?php unset($_SESSION['success']); endif; ?>
<script>
    document.querySelectorAll('.action-btn').forEach(function (btn) {

        btn.addEventListener("click", function (e) {

            e.preventDefault();
            e.stopPropagation();

            let id = this.dataset.id;

            Swal.fire({

                title: "Choose Action",
                icon: "question",

                showCancelButton: true,
                showDenyButton: true,

                confirmButtonText: "Edit",
                denyButtonText: "Delete",
                cancelButtonText: "Cancel"

            }).then((result) => {

                // EDIT
                if (result.isConfirmed) {

                    let source = this.dataset.source;

                    window.location.href =
                        "add-appointment-order.php?id=" + id + "&source=" + source;
                }

                // DELETE
                if (result.isDenied) {

                    Swal.fire({

                        title: "Delete Appointment?",
                        text: "Are you sure you want to delete this appointment?",
                        icon: "warning",

                        showCancelButton: true,

                        confirmButtonText: "Yes Delete",
                        cancelButtonText: "Cancel"

                    }).then((res) => {

                        if (res.isConfirmed) {

                            window.location.href = "delete-appointment.php?id=" + id;

                        }

                    });

                }

                // CANCEL -> popup closes automatically

            });

        });

    });
</script>

<!-- Supervisor Assignment Modal -->
<div id="supervisorModal"
    style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center;">
    <div
        style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; max-width: 400px; width: 90%; padding: 1.5rem; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h3
                style="font-size: 1.1rem; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 0.5rem; margin: 0;">
                <i class="ri-user-star-line" style="color: #4f46e5;"></i> Assign Supervisor
            </h3>
            <button onclick="closeSupervisorModal()"
                style="border: none; background: transparent; color: #64748b; font-size: 1.2rem; cursor: pointer;">
                <i class="ri-close-line"></i>
            </button>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="assign_supervisor">
            <input type="hidden" name="appointment_id" id="modalAppointmentId">

            <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 1.25rem;">
                Assign a supervisor to appointment for <strong id="modalCustomerName" style="color: #4f46e5;"></strong>.
            </p>

            <div style="margin-bottom: 1rem;">
                <label
                    style="font-size: 0.8rem; font-weight: 600; color: #475569; display: block; margin-bottom: 0.5rem;">Supervisor</label>
                <select name="supervisor_id" id="modalSupervisorSelect"
                    style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; outline: none;">
                    <option value="">-- Select Supervisor --</option>
                    <?php foreach ($supervisors as $sup): ?>
                        <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button type="button" onclick="closeSupervisorModal()"
                    style="padding: 0.625rem 1rem; border: 1px solid #e2e8f0; background: white; color: #475569; font-weight: 600; border-radius: 8px; cursor: pointer;">Cancel</button>
                <button type="submit"
                    style="padding: 0.625rem 1.25rem; border: none; background: #4f46e5; color: white; font-weight: 600; border-radius: 8px; cursor: pointer;">Assign</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openSupervisorModal(appointmentId, customerName, currentSupervisorId) {
        document.getElementById('modalAppointmentId').value = appointmentId;
        document.getElementById('modalCustomerName').textContent = customerName;
        document.getElementById('modalSupervisorSelect').value = currentSupervisorId || '';
        document.getElementById('supervisorModal').style.display = 'flex';
    }

    function closeSupervisorModal() {
        document.getElementById('supervisorModal').style.display = 'none';
    }
    // Close modals when clicking outside
    document.getElementById('supervisorModal')?.addEventListener('click', function (e) {
        if (e.target === this) closeSupervisorModal();
    });

</script>

<?php include 'includes/footer.php'; ?>