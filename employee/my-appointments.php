<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in                        
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

// Fetch employee data
$stmt = $pdo->prepare("
    SELECT id, first_name, last_name, job_role 
    FROM employees 
    WHERE user_id = ? AND is_deleted = 0
");
$stmt->execute([$_SESSION['user_id']]);
$emp = $stmt->fetch();

if (!$emp) {
    header("Location: login.php");
    exit();
}

$employee_id = $emp['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $appointmentId = intval($_POST['appointment_id'] ?? 0);

    if ($appointmentId > 0) {
        try {
            $pdo->beginTransaction();

            if ($action === 'schedule') {
                $newDate = $_POST['appointment_date'] ?? '';
                $newTime = $_POST['appointment_time'] ?? '';
                $stmt = $pdo->prepare("UPDATE appointments SET appointment_date = ?, appointment_time = ?, updated_at = NOW() WHERE id = ? AND assigned_employee_id = ?");
                $stmt->execute([$newDate, $newTime, $appointmentId, $employee_id]);
                $_SESSION['success'] = 'Appointment schedule updated successfully.';
            } elseif ($action === 'cancel') {

                $reason = trim($_POST['cancel_reason']);

                $stmt = $pdo->prepare("
        UPDATE appointments
        SET
            status='cancelled',
            workflow_status='cancelled',
            cancel_reason=?,
            updated_at=NOW()
        WHERE id=? AND assigned_employee_id=?
    ");

                $stmt->execute([
                    $reason,
                    $appointmentId,
                    $employee_id
                ]);

                $_SESSION['success'] = 'Appointment cancelled successfully.';
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = 'Action failed: ' . $e->getMessage();
        }
    }

    header('Location: my-appointments.php');
    exit();
}

$activePage = 'my-appointments';

// Fetch appointments assigned to this employee                   
$stmt = $pdo->prepare(" 
SELECT
    a.id,
    a.visit_type,
    a.appointment_date,
    a.appointment_time,
a.status,
a.cancel_reason,
    a.notes,
    a.order_id,
    c.first_name AS cust_first,
    c.last_name AS cust_last,
    c.phone AS cust_phone,
    c.email AS cust_email,
    sc.name AS garment,
    sc.image AS garment_img,
    o.order_code,
    o.total_amount
FROM appointments a
LEFT JOIN customers c
    ON c.user_id = a.user_id
LEFT JOIN sub_categories sc
    ON sc.id = a.sub_category_id
LEFT JOIN orders o
    ON o.order_code = a.order_id
WHERE a.assigned_employee_id = ?
AND a.is_deleted = 0
ORDER BY a.appointment_date DESC, a.appointment_time ASC
");
$stmt->execute([$employee_id]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "My Appointments";
$headerTitle = "Appointments";
include 'includes/header.php';
?>

<div style="padding: 1.25rem; max-width: 1200px; margin: 0 auto;">

    <!-- Header -->
    <!-- Header -->
    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem;">

        <a href="dashboard.php" style="
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            color: #831843;
            text-decoration: none;
            font-size: 1.25rem;
            flex-shrink: 0;
       ">
            <i class="ri-arrow-left-line"></i>
        </a>

        <div>
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">
                My Appointments
            </h2>

            <p style="color: #64748b; margin-top: 0.25rem;">
                Customer appointments assigned to you
            </p>
        </div>

    </div>

    <!-- Appointments List -->
    <div class="appointments-table-card"
        style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
        <?php if (empty($appointments)): ?>
            <div style="padding: 3rem; text-align: center; color: #64748b;">
                <i class="ri-calendar-blank-line"
                    style="font-size: 3rem; color: #cbd5e1; display: block; margin-bottom: 1rem;"></i>
                <p style="font-size: 1.1rem; font-weight: 600;">No appointments assigned</p>
                <p style="font-size: 0.9rem; margin-top: 0.5rem;">You don't have any customer appointments assigned yet.</p>
            </div>
        <?php else: ?>
            <div class="appointments-table-scroll">
                <table class="appointments-table" style="width: 100%; border-collapse: collapse;">
                    <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <tr>
                            <th
                                style="padding: 1rem; text-align: left; font-weight: 600; color: #475569; font-size: 0.9rem;">
                                Customer</th>
                            <th
                                style="padding: 1rem; text-align: left; font-weight: 600; color: #475569; font-size: 0.9rem;">
                                Garment</th>
                            <th
                                style="padding: 1rem; text-align: left; font-weight: 600; color: #475569; font-size: 0.9rem;">
                                Appointment Date & Time</th>
                            <th
                                style="padding: 1rem; text-align: left; font-weight: 600; color: #475569; font-size: 0.9rem;">
                                Status</th>
                            <th
                                style="padding: 1rem; text-align: left; font-weight: 600; color: #475569; font-size: 0.9rem;">
                                Visit Type</th>

                            <!-- <th style="padding: 1rem; text-align: left; font-weight: 600; color: #475569; font-size: 0.9rem;">Amount</th> -->
                            <th
                                style="padding: 1rem; text-align: center; font-weight: 600; color: #475569; font-size: 0.9rem;">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $apt):
                            // Color coding for status
                            $status_color = '#64748b';
                            $status_bg = '#f1f5f9';
                            if ($apt['status'] === 'confirmed') {
                                $status_color = '#059669';
                                $status_bg = '#f0fdf4';
                            } elseif ($apt['status'] === 'pending') {
                                $status_color = '#b45309';
                                $status_bg = '#fef3c7';
                            } elseif ($apt['status'] === 'completed') {
                                $status_color = '#0891b2';
                                $status_bg = '#ecf7ff';
                            }
                            ?>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td data-label="Customer" style="padding: 1rem; color: #1e293b; font-weight: 600;">
                                    <?= htmlspecialchars($apt['cust_first'] . ' ' . ($apt['cust_last'] ?? '')) ?>
                                    <br><span style="font-size: 0.8rem; color: #64748b; font-weight: 400;">
                                        <i class="ri-phone-line" style="font-size: 0.75rem;"></i>
                                        <?= htmlspecialchars($apt['cust_phone'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td data-label="Garment" style="padding: 1rem; color: #64748b;">
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <?php if (!empty($apt['garment_img'])): ?>
                                            <img src="../admin/<?= htmlspecialchars($apt['garment_img']) ?>" alt="Garment"
                                                style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px;">
                                        <?php else: ?>
                                            <div
                                                style="width: 40px; height: 40px; background: #f1f5f9; border-radius: 6px; display: flex; align-items: center; justify-content: center;">
                                                <i class="ri-shirt-line" style="color: #cbd5e1;"></i>
                                            </div>
                                        <?php endif; ?>
                                        <span><?= htmlspecialchars($apt['garment'] ?? 'General') ?></span>
                                    </div>
                                </td>
                                <td data-label="Appointment" style="padding: 1rem; color: #64748b;">
                                    <strong style="color: #1e293b;">
                                        <?= date('d M Y', strtotime($apt['appointment_date'])) ?>
                                    </strong>
                                    <br><span style="font-size: 0.85rem;">
                                        <i class="ri-time-line"></i> <?= substr($apt['appointment_time'], 0, 5) ?>
                                    </span>
                                </td>
                                <td data-label="Status" style="padding: 1rem;">
                                    <span
                                        style="background: <?= $status_bg ?>; color: <?= $status_color ?>; padding: 0.4rem 0.8rem; border-radius: 6px; font-size: 0.85rem; font-weight: 600; text-transform: capitalize;">
                                        <?= htmlspecialchars($apt['status'] ?? 'Pending') ?>
                                    </span>
                                </td>

                                <td data-label="Visit Type" style="padding:1rem;">
                                    <span style="
                                    background:#eef2ff;
                                    color:#4f46e5;
                                    padding:0.4rem 0.8rem;
                                    border-radius:6px;
                                    font-size:0.85rem;
                                    font-weight:600;
                                    text-transform:capitalize;">
                                        <?= htmlspecialchars($apt['visit_type']) ?>
                                    </span>
                                </td>

                                <!-- <td style="padding: 1rem; color: #1e293b; font-weight: 600;">
                                    ₹<?= number_format($apt['total_amount'] ?? 0, 2) ?>
                            </td> -->
                                <td data-label="Actions" class="appointment-actions" style="padding: 1rem; text-align: center;">
                                    <div class="appointment-actions-list"
                                        style="display: flex; flex-wrap: wrap; gap: 0.5rem; justify-content: center;">
                                        <button onclick="openDetailsModal(
                                        <?= $apt['id'] ?>,
                                        '<?= htmlspecialchars(json_encode($apt), ENT_QUOTES) ?>'
                                    )"
                                            style="background: #f8fafc; color: #4f46e5; border: 1px solid #e2e8f0; padding: 0.45rem 0.8rem; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.78rem; transition: all 0.2s;">
                                            <i class="ri-eye-line"></i> View
                                        </button>
                                        <button
                                            onclick="openScheduleModal(<?= $apt['id'] ?>, '<?= $apt['appointment_date'] ?>', '<?= substr($apt['appointment_time'], 0, 5) ?>')"
                                            style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; padding: 0.45rem 0.8rem; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.78rem;">
                                            <i class="ri-calendar-event-line"></i> Schedule
                                        </button>
                                        <button
                                            onclick="window.location.href='add-measurement.php?appointment_id=<?= $apt['id'] ?>'"
                                            style="background: #fce7f3; color: #be185d; border: 1px solid #fecdd3; padding: 0.45rem 0.8rem; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.78rem;">
                                            <i class="ri-ruler-line"></i> Measurements
                                        </button>
                                        <button onclick="confirmCancel(<?= $apt['id'] ?>)"
                                            style="background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; padding: 0.45rem 0.8rem; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.78rem;">
                                            <i class="ri-close-circle-line"></i> Cancel
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Details Modal -->
<div id="detailsModal"
    style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center; overflow-y: auto;">
    <div
        style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; max-width: 500px; width: 90%; margin: 2rem auto; padding: 1.5rem; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: #0f172a; margin: 0;">
                <i class="ri-calendar-event-line" style="margin-right: 0.5rem; color: #4f46e5;"></i>Appointment
                Details
            </h3>
            <button onclick="closeDetailsModal()"
                style="border: none; background: transparent; color: #64748b; font-size: 1.2rem; cursor: pointer;">
                <i class="ri-close-line"></i>
            </button>
        </div>

        <div id="detailsContent" style="color: #475569;">

            <!-- Content will be populated via JavaScript -->
        </div>

        <div
            style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
            <button onclick="closeDetailsModal()"
                style="padding: 0.625rem 1rem; border: 1px solid #e2e8f0; background: white; color: #475569; font-weight: 600; border-radius: 8px; cursor: pointer;">
                Close
            </button>
        </div>
    </div>
</div>
<style>
    .appointments-table-card {
        width: 100%;
        max-width: 100%;
    }

    .appointments-table-scroll {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }

    .appointments-table-scroll::-webkit-scrollbar {
        display: none;
    }

    .appointments-table {
        width: 100%;
        border-collapse: collapse;
    }

    .appointment-actions-list {
        display: flex !important;
        flex-wrap: wrap !important;
        gap: 0.5rem !important;
        justify-content: center;
    }

    @media (max-width: 768px) {
        .appointments-table-card {
            overflow: hidden !important;
        }

        .appointments-table-scroll {
            overflow-x: auto !important;
            overflow-y: hidden !important;
            touch-action: pan-x;
        }

        .appointments-table {
            width: max-content !important;
            min-width: 900px !important;
            table-layout: auto;
        }

        .appointments-table th,
        .appointments-table td {
            padding: 0.75rem !important;
            font-size: 0.78rem !important;
            white-space: nowrap !important;
            vertical-align: middle;
        }

        .appointments-table th:nth-child(1),
        .appointments-table td:nth-child(1) {
            min-width: 145px;
        }

        .appointments-table th:nth-child(2),
        .appointments-table td:nth-child(2) {
            min-width: 130px;
        }

        .appointments-table th:nth-child(3),
        .appointments-table td:nth-child(3) {
            min-width: 170px;
        }

        .appointments-table th:nth-child(4),
        .appointments-table td:nth-child(4) {
            min-width: 110px;
        }

        .appointments-table th:nth-child(5),
        .appointments-table td:nth-child(5) {
            min-width: 120px;
        }

        .appointments-table th:nth-child(6),
        .appointments-table td:nth-child(6) {
            min-width: 260px;
        }

        .appointment-actions-list {
            min-width: 250px;
            flex-wrap: wrap !important;
        }

        .appointment-actions-list button {
            font-size: 0.72rem !important;
            padding: 0.4rem 0.6rem !important;
            white-space: nowrap;
        }
    }

    @media (max-width: 480px) {
        .appointments-table {
            min-width: 900px !important;
        }

        .appointments-table th,
        .appointments-table td {
            padding: 0.65rem !important;
            font-size: 0.74rem !important;
        }
    }
</style>

<script>
    function openDetailsModal(appointmentId, appointmentJson) {
        const apt = JSON.parse(appointmentJson);
        const content = `
        <div style="display: grid; gap: 1rem;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div>
                    <p style="font-size: 0.8rem; color: #64748b; margin-bottom: 0.25rem; font-weight: 600;">Customer Name</p>
                    <p style="color: #1e293b; font-weight: 600;">${apt.cust_first} ${apt.cust_last || ''}</p>
                </div>
                <div>
                    <p style="font-size: 0.8rem; color: #64748b; margin-bottom: 0.25rem; font-weight: 600;">Contact</p>
                    <p style="color: #1e293b; font-weight: 600;">${apt.cust_phone || 'N/A'}</p>
                </div>
            </div>
            <div>
                <p style="font-size: 0.8rem; color: #64748b; margin-bottom: 0.25rem; font-weight: 600;">Email</p>
                <p style="color: #1e293b; font-weight: 600;">${apt.cust_email || 'N/A'}</p>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div>
                    <p style="font-size: 0.8rem; color: #64748b; margin-bottom: 0.25rem; font-weight: 600;">Garment Type</p>
                    <p style="color: #1e293b; font-weight: 600;">${apt.garment || 'General'}</p>
                </div>
                <div>
                    <p style="font-size: 0.8rem; color: #64748b; margin-bottom: 0.25rem; font-weight: 600;">Order Code</p>
                    <p style="color: #4f46e5; font-weight: 600;">#${apt.order_code}</p>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div>
                    <p style="font-size: 0.8rem; color: #64748b; margin-bottom: 0.25rem; font-weight: 600;">Date</p>
                    <p style="color: #1e293b; font-weight: 600;">${new Date(apt.appointment_date).toLocaleDateString('en-IN', { year: 'numeric', month: 'short', day: '2-digit' })}</p>
                </div>
                <div>
                    <p style="font-size: 0.8rem; color: #64748b; margin-bottom: 0.25rem; font-weight: 600;">Time</p>
                    <p style="color: #1e293b; font-weight: 600;">${apt.appointment_time.substring(0, 5)}</p>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">

                <div>
                    <p style="font-size: 0.8rem; color: #64748b; margin-bottom: 0.25rem; font-weight: 600;">Status</p>
                    <span style="background: ${apt.status === 'confirmed' ? '#f0fdf4' : apt.status === 'completed' ? '#ecf7ff' : '#fef3c7'}; color: ${apt.status === 'confirmed' ? '#059669' : apt.status === 'completed' ? '#0891b2' : '#b45309'}; padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.8rem; font-weight: 600; text-transform: capitalize;">
                        ${apt.status}
                    </span>
                </div>
            </div>
            <div style="background: #f8fafc; padding: 0.75rem; border-radius: 8px; border-left: 3px solid #4f46e5;">
                <p style="font-size: 0.8rem; color: #64748b; margin-bottom: 0.25rem; font-weight: 600;">Note</p>
                <p style="color: #1e293b; margin: 0; font-size: 0.9rem;">Please confirm the appointment details with the customer. Contact the supervisor if you have any questions.</p>
            </div>
        </div>
    `;
        document.getElementById('detailsContent').innerHTML = content;
        document.getElementById('detailsModal').style.display = 'flex';
    }

    function closeDetailsModal() {
        document.getElementById('detailsModal').style.display = 'none';
    }

    document.getElementById('detailsModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeDetailsModal();
        }
    });

    function openScheduleModal(appointmentId, appointmentDate, appointmentTime) {
        document.getElementById('scheduleAppointmentId').value = appointmentId;
        document.getElementById('scheduleDate').value = appointmentDate;
        document.getElementById('scheduleTime').value = appointmentTime;
        document.getElementById('scheduleModal').style.display = 'flex';
    }

    function closeScheduleModal() {
        document.getElementById('scheduleModal').style.display = 'none';
    }

    function confirmCancel(appointmentId) {

        Swal.fire({
            title: 'Cancel Appointment',
            input: 'textarea',
            inputLabel: 'Reason',
            inputPlaceholder: 'Enter cancellation reason...',
            inputAttributes: {
                maxlength: 300
            },
            inputValidator: (value) => {
                if (!value) {
                    return 'Please enter a reason.';
                }
            },
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Cancel Appointment',
            cancelButtonText: 'Close'
        }).then((result) => {

            if (result.isConfirmed) {

                document.getElementById('cancelAppointmentId').value = appointmentId;

                if (!document.getElementById('cancelReason')) {
                    let input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'cancel_reason';
                    input.id = 'cancelReason';
                    document.getElementById('cancelForm').appendChild(input);
                }

                document.getElementById('cancelReason').value = result.value;

                document.getElementById('cancelForm').submit();

            }

        });

    }
</script>

<!-- Schedule Modal -->
<div id="scheduleModal"
    style="display: none; position: fixed; inset: 0; z-index: 9998; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center; overflow-y: auto;">
    <div
        style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; max-width: 440px; width: 90%; margin: 2rem auto; padding: 1.5rem; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: #0f172a; margin: 0;">Reschedule Appointment</h3>
            <button onclick="closeScheduleModal()"
                style="border: none; background: transparent; color: #64748b; font-size: 1.2rem; cursor: pointer;"><i
                    class="ri-close-line"></i></button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="schedule">
            <input type="hidden" name="appointment_id" id="scheduleAppointmentId">
            <div style="display: grid; gap: 1rem;">
                <label style="font-size: 0.9rem; color: #475569; font-weight: 600;">Date</label>
                <input id="scheduleDate" type="date" name="appointment_date" required
                    style="width: 100%; padding: 0.85rem 1rem; border: 1px solid #cbd5e1; border-radius: 8px; outline: none;">
                <label style="font-size: 0.9rem; color: #475569; font-weight: 600;">Time</label>
                <input id="scheduleTime" type="time" name="appointment_time" required
                    style="width: 100%; padding: 0.85rem 1rem; border: 1px solid #cbd5e1; border-radius: 8px; outline: none;">
                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 0.5rem;">
                    <button type="button" onclick="closeScheduleModal()"
                        style="padding: 0.75rem 1rem; border: 1px solid #e2e8f0; background: white; color: #475569; border-radius: 8px; cursor: pointer;">Cancel</button>
                    <button type="submit"
                        style="padding: 0.75rem 1rem; border: none; background: #4f46e5; color: white; border-radius: 8px; cursor: pointer;">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>
<form id="cancelForm" method="post" style="display: none;">
    <input type="hidden" name="action" value="cancel">
    <input type="hidden" name="appointment_id" id="cancelAppointmentId">
</form>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php if (!empty($_SESSION['success'])): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: <?= json_encode($_SESSION['success']) ?>,
            confirmButtonColor: '#4f46e5',
            timer: 1800,
            showConfirmButton: false
        });
    </script>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['error'])): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: <?= json_encode($_SESSION['error']) ?>,
            confirmButtonColor: '#ef4444'
        });
    </script>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php include 'includes/bottom-nav.php'; ?>