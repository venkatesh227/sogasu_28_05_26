<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in and is supervisor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

// Fetch employee data to verify supervisor role
$stmt = $pdo->prepare("
    SELECT id, first_name, last_name, job_role 
    FROM employees 
    WHERE user_id = ? AND is_deleted = 0
");
$stmt->execute([$_SESSION['user_id']]);
$emp = $stmt->fetch();

if (!$emp || $emp['job_role'] !== 'Supervisor') {
    header("Location: dashboard.php");
    exit();
}

$employee_id = $emp['id'];
$activePage = 'supervisor-appointments';

// Handle employee assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_employee') {
    $appointment_id = $_POST['appointment_id'] ?? null;
    $assign_employee_id = $_POST['assign_employee_id'] ?? null;
    $source = $_POST['source'] ?? 'customer_orders';

    if ($appointment_id) {
        try {
            if ($source === 'appointments') {
                $upd_stmt = $pdo->prepare(
                    "UPDATE appointments SET assigned_employee_id = ? WHERE id = ? AND supervisor_id = ?"
                );
            } else {
                $upd_stmt = $pdo->prepare(
                    "UPDATE customer_orders SET assigned_employee_id = ? WHERE id = ? AND supervisor_id = ?"
                );
            }

            $upd_stmt->execute([$assign_employee_id, $appointment_id, $employee_id]);
            $_SESSION['success'] = "Employee assigned successfully";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error assigning employee";
        }
        header("Location: supervisor-appointments.php");
        exit();
    }
}

// Fetch appointments for this supervisor (include both admin `appointments` and `customer_orders`)
$sql = <<<'SQL'
SELECT
    a.id,
    a.customer_name AS cust_first,
    '' AS cust_last,
    a.customer_phone AS cust_phone,
    sc.name AS garment,
    a.appointment_date,
    a.appointment_time,
    a.visit_type,
    a.status,
    a.workflow_status,
    a.appointment_source,
    a.user_id,
    a.supervisor_id,
    a.assigned_employee_id,
    e.first_name AS emp_first,
    e.last_name AS emp_last,
    'appointments' AS source
FROM appointments a
LEFT JOIN sub_categories sc ON sc.id = a.sub_category_id
LEFT JOIN employees e ON e.id = a.assigned_employee_id
WHERE a.supervisor_id = ?
AND a.is_deleted = 0
AND a.visit_type = 'home'
ORDER BY a.appointment_date DESC, a.appointment_time ASC
SQL;

$stmt = $pdo->prepare($sql);
$stmt->execute([$employee_id]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all employees (except supervisor) for assignment
$stmt = $pdo->prepare("
    SELECT id, first_name, last_name
    FROM employees
    WHERE is_deleted = 0
    AND status = 1
    AND id != ?
    AND (supervisor_id = ? OR id = ?)
    ORDER BY first_name
");
$stmt->execute([$employee_id, $employee_id, $employee_id]);
$all_employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Supervisor Appointments";
$headerTitle = "Appointments";
include 'includes/header.php';
?>

<div style="padding: 1.25rem; max-width: 1200px; margin: 0 auto;">

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
                Appointments
            </h2>

            <p style="color: #64748b; margin-top: 0.25rem;">
                Manage customer appointments and assign employees
            </p>
        </div>

    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div
            style="background: #f0fdf4; color: #166534; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid #bbf7d0;">
            <i class="ri-check-line" style="margin-right: 0.5rem;"></i><?= htmlspecialchars($_SESSION['success']) ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div
            style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid #fecaca;">
            <i class="ri-error-warning-line" style="margin-right: 0.5rem;"></i><?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Appointments List -->
    <div class="appointments-table-card"
        style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
        <?php if (empty($appointments)): ?>
            <div style="padding: 3rem; text-align: center; color: #64748b;">
                <i class="ri-calendar-blank-line"
                    style="font-size: 3rem; color: #cbd5e1; display: block; margin-bottom: 1rem;"></i>
                <p style="font-size: 1.1rem; font-weight: 600;">No appointments assigned</p>
                <p style="font-size: 0.9rem; margin-top: 0.5rem;">You don't have any appointments to manage.</p>
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
                                Date & Time</th>
                            <th
                                style="padding: 1rem; text-align: left; font-weight: 600; color: #475569; font-size: 0.9rem;">
                                Assigned Employee</th>
                            <th
                                style="padding: 1rem; text-align: left; font-weight: 600; color: #475569; font-size: 0.9rem;">
                                Status</th>
                            <th
                                style="padding: 1rem; text-align: left; font-weight: 600; color: #475569; font-size: 0.9rem;">
                                Visit Type</th>

                            <th
                                style="padding: 1rem; text-align: center; font-weight: 600; color: #475569; font-size: 0.9rem;">
                                Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $apt): ?>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 1rem; color: #1e293b; font-weight: 600;">
                                    <?= htmlspecialchars($apt['cust_first'] . ' ' . ($apt['cust_last'] ?? '')) ?>
                                    <br><span style="font-size: 0.8rem; color: #64748b; font-weight: 400;">
                                        <i class="ri-phone-line" style="font-size: 0.75rem;"></i>
                                        <?= htmlspecialchars($apt['cust_phone'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem; color: #64748b;">
                                    <?= htmlspecialchars($apt['garment'] ?? 'General') ?>
                                </td>
                                <td style="padding: 1rem; color: #64748b;">
                                    <?= date('d M Y', strtotime($apt['appointment_date'])) ?>
                                    <br><span style="font-size: 0.85rem; color: #94a3b8;">
                                        <?= substr($apt['appointment_time'], 0, 5) ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem; color: #64748b;">
                                    <?php if ($apt['assigned_employee_id']): ?>
                                        <span
                                            style="background: #f0fdf4; color: #166534; padding: 0.4rem 0.8rem; border-radius: 6px; font-size: 0.85rem; font-weight: 600;">
                                            <?= htmlspecialchars($apt['emp_first'] . ' ' . ($apt['emp_last'] ?? '')) ?>
                                        </span>
                                    <?php else: ?>
                                        <span
                                            style="background: #fef3c7; color: #b45309; padding: 0.4rem 0.8rem; border-radius: 6px; font-size: 0.85rem; font-weight: 600;">
                                            <i class="ri-alert-line"></i> Unassigned
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <span
                                        style="background: <?= $status_bg ?>; color: <?= $status_color ?>; padding: 0.4rem 0.8rem; border-radius: 6px; font-size: 0.85rem; font-weight: 600; text-transform: capitalize;">
                                        <?= htmlspecialchars($apt['status'] ?? 'Pending') ?>
                                    </span>
                                </td>

                                <td style="padding:1rem;">
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

                                <td style="padding: 1rem; color: #1e293b; font-weight: 600;">
                                    <button onclick="openAssignModal(
                                    <?= $apt['id'] ?>,
                                    '<?= htmlspecialchars($apt['cust_first']) ?>',
                                    <?= $apt['assigned_employee_id'] ?? 'null' ?>,
                                    '<?= htmlspecialchars($apt['source']) ?>'
                                )"
                                        style="background: #f8fafc; color: #4f46e5; border: 1px solid #e2e8f0; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.85rem; transition: all 0.2s;">
                                        <i class="ri-user-add-line"></i> Assign
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Assign Employee Modal -->
<div id="assignModal"
    style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center;">
    <div
        style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; max-width: 400px; width: 90%; padding: 1.5rem; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h3
                style="font-size: 1.1rem; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 0.5rem; margin: 0;">
                <i class="ri-user-add-line" style="color: #4f46e5;"></i> Assign Employee
            </h3>
            <button onclick="closeAssignModal()"
                style="border: none; background: transparent; color: #64748b; font-size: 1.2rem; cursor: pointer;">
                <i class="ri-close-line"></i>
            </button>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="assign_employee">
            <input type="hidden" name="appointment_id" id="modalAppointmentId">
            <input type="hidden" name="source" id="modalSource" value="customer_orders">

            <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 1.25rem;">
                Assign an employee to handle this appointment for <strong id="modalCustomerName"
                    style="color: #4f46e5;"></strong>.
            </p>

            <div style="margin-bottom: 1rem;">
                <label
                    style="font-size: 0.8rem; font-weight: 600; color: #475569; display: block; margin-bottom: 0.5rem;">Select
                    Employee</label>
                <select name="assign_employee_id" id="modalEmployeeSelect"
                    style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; outline: none;">
                    <option value="">-- Choose Employee --</option>
                    <?php foreach ($all_employees as $emp_opt): ?>
                        <option value="<?= $emp_opt['id'] ?>">
                            <?= htmlspecialchars($emp_opt['first_name'] . ' ' . ($emp_opt['last_name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button type="button" onclick="closeAssignModal()"
                    style="padding: 0.625rem 1rem; border: 1px solid #e2e8f0; background: white; color: #475569; font-weight: 600; border-radius: 8px; cursor: pointer;">
                    Cancel
                </button>
                <button type="submit"
                    style="padding: 0.625rem 1.25rem; border: none; background: #4f46e5; color: white; font-weight: 600; border-radius: 8px; cursor: pointer;">
                    Assign
                </button>
            </div>
        </form>
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
            min-width: 950px !important;
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
            min-width: 120px;
        }

        .appointments-table th:nth-child(3),
        .appointments-table td:nth-child(3) {
            min-width: 150px;
        }

        .appointments-table th:nth-child(4),
        .appointments-table td:nth-child(4) {
            min-width: 170px;
        }

        .appointments-table th:nth-child(5),
        .appointments-table td:nth-child(5) {
            min-width: 110px;
        }

        .appointments-table th:nth-child(6),
        .appointments-table td:nth-child(6) {
            min-width: 110px;
        }

        .appointments-table th:nth-child(7),
        .appointments-table td:nth-child(7) {
            min-width: 120px;
        }
    }

    @media (max-width: 480px) {
        .appointments-table {
            min-width: 950px !important;
        }

        .appointments-table th,
        .appointments-table td {
            padding: 0.65rem !important;
            font-size: 0.74rem !important;
        }
    }
</style>

<script>
    function openAssignModal(appointmentId, customerName, currentEmployeeId, source) {
        document.getElementById('modalAppointmentId').value = appointmentId;
        document.getElementById('modalCustomerName').textContent = customerName;
        document.getElementById('modalEmployeeSelect').value = currentEmployeeId || '';
        document.getElementById('modalSource').value = source || 'customer_orders';
        document.getElementById('assignModal').style.display = 'flex';
    }

    function closeAssignModal() {
        document.getElementById('assignModal').style.display = 'none';
    }

    document.getElementById('assignModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeAssignModal();
        }
    });
</script>

<?php include 'includes/bottom-nav.php'; ?>