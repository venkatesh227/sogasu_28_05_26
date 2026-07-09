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
$activePage = 'my-appointments';

// Fetch appointments assigned to this employee
$stmt = $pdo->prepare("
SELECT
    co.id,
    co.order_code,
    co.visit_type,
    co.appointment_date,
        co.appointment_time,
        co.status,
        co.slot_status,
        co.total_amount,
        co.sub_category_id,
        co.created_at,
        cu.first_name as cust_first,
        cu.last_name as cust_last,
        cu.phone as cust_phone,
        cu.email as cust_email,
        sc.name as garment,
        sc.image as garment_img,
        sup.first_name as sup_first,
        sup.last_name as sup_last
    FROM customer_orders co
    LEFT JOIN customers cu ON co.user_id = cu.user_id
    LEFT JOIN sub_categories sc ON co.sub_category_id = sc.id
    LEFT JOIN employees sup ON co.supervisor_id = sup.id
    WHERE co.assigned_employee_id = ?
    AND co.is_deleted = 0
    AND (co.slot_status = 'confirmed' OR co.slot_status IS NULL)
    ORDER BY co.appointment_date DESC, co.appointment_time ASC
");
$stmt->execute([$employee_id]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "My Appointments";
$headerTitle = "Appointments";
include 'includes/header.php';
?>

<div style="padding: 1.25rem; max-width: 1200px; margin: 0 auto;">
    
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">My Appointments</h2>
            <p style="color: #64748b; margin-top: 0.25rem;">Customer appointments assigned to you</p>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div style="background: #f0fdf4; color: #166534; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid #bbf7d0;">
            <i class="ri-check-line" style="margin-right: 0.5rem;"></i><?= htmlspecialchars($_SESSION['success']) ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid #fecaca;">
            <i class="ri-error-warning-line" style="margin-right: 0.5rem;"></i><?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Appointments List -->
    <div style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
        <?php if (empty($appointments)): ?>
            <div style="padding: 3rem; text-align: center; color: #64748b;">
                <i class="ri-calendar-blank-line" style="font-size: 3rem; color: #cbd5e1; display: block; margin-bottom: 1rem;"></i>
                <p style="font-size: 1.1rem; font-weight: 600;">No appointments assigned</p>
                <p style="font-size: 0.9rem; margin-top: 0.5rem;">You don't have any customer appointments assigned yet.</p>
            </div>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                    <tr>
                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: #475569; font-size: 0.9rem;">Customer</th>
                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: #475569; font-size: 0.9rem;">Garment</th>
                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: #475569; font-size: 0.9rem;">Appointment Date & Time</th>
                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: #475569; font-size: 0.9rem;">Status</th>
                                                <th style="padding: 1rem; text-align: left; font-weight: 600; color: #475569; font-size: 0.9rem;">Visit Type</th>

                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: #475569; font-size: 0.9rem;">Amount</th>
                        <th style="padding: 1rem; text-align: center; font-weight: 600; color: #475569; font-size: 0.9rem;">Actions</th>
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
                            <td style="padding: 1rem; color: #1e293b; font-weight: 600;">
                                <?= htmlspecialchars($apt['cust_first'] . ' ' . ($apt['cust_last'] ?? '')) ?>
                                <br><span style="font-size: 0.8rem; color: #64748b; font-weight: 400;">
                                    <i class="ri-phone-line" style="font-size: 0.75rem;"></i> <?= htmlspecialchars($apt['cust_phone'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td style="padding: 1rem; color: #64748b;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <?php if (!empty($apt['garment_img'])): ?>
                                        <img src="../admin/<?= htmlspecialchars($apt['garment_img']) ?>" alt="Garment" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px;">
                                    <?php else: ?>
                                        <div style="width: 40px; height: 40px; background: #f1f5f9; border-radius: 6px; display: flex; align-items: center; justify-content: center;">
                                            <i class="ri-shirt-line" style="color: #cbd5e1;"></i>
                                        </div>
                                    <?php endif; ?>
                                    <span><?= htmlspecialchars($apt['garment'] ?? 'General') ?></span>
                                </div>
                            </td>
                            <td style="padding: 1rem; color: #64748b;">
                                <strong style="color: #1e293b;">
                                    <?= date('d M Y', strtotime($apt['appointment_date'])) ?>
                                </strong>
                                <br><span style="font-size: 0.85rem;">
                                    <i class="ri-time-line"></i> <?= substr($apt['appointment_time'], 0, 5) ?>
                                </span>
                            </td>
<td style="padding: 1rem;">
    <span style="background: <?= $status_bg ?>; color: <?= $status_color ?>; padding: 0.4rem 0.8rem; border-radius: 6px; font-size: 0.85rem; font-weight: 600; text-transform: capitalize;">
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
                                    ₹<?= number_format($apt['total_amount'] ?? 0, 2) ?>
                            </td>
                            <td style="padding: 1rem; text-align: center;">
                                <button onclick="openDetailsModal(
                                    <?= $apt['id'] ?>,
                                    '<?= htmlspecialchars(json_encode($apt)) ?>'
                                )" style="background: #f8fafc; color: #4f46e5; border: 1px solid #e2e8f0; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.85rem; transition: all 0.2s;">
                                    <i class="ri-eye-line"></i> View
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Details Modal -->
<div id="detailsModal" style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center; overflow-y: auto;">
    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; max-width: 500px; width: 90%; margin: 2rem auto; padding: 1.5rem; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: #0f172a; margin: 0;">
                <i class="ri-calendar-event-line" style="margin-right: 0.5rem; color: #4f46e5;"></i>Appointment Details
            </h3>
            <button onclick="closeDetailsModal()" style="border: none; background: transparent; color: #64748b; font-size: 1.2rem; cursor: pointer;">
                <i class="ri-close-line"></i>
            </button>
        </div>

        <div id="detailsContent" style="color: #475569;">
            <!-- Content will be populated via JavaScript -->
        </div>

        <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
            <button onclick="closeDetailsModal()" style="padding: 0.625rem 1rem; border: 1px solid #e2e8f0; background: white; color: #475569; font-weight: 600; border-radius: 8px; cursor: pointer;">
                Close
            </button>
        </div>
    </div>
</div>

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
                    <p style="font-size: 0.8rem; color: #64748b; margin-bottom: 0.25rem; font-weight: 600;">Amount</p>
                    <p style="color: #1e293b; font-weight: 600;">₹${parseFloat(apt.total_amount).toFixed(2)}</p>
                </div>
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

document.getElementById('detailsModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDetailsModal();
    }
});
</script>

<?php include 'includes/bottom-nav.php'; ?>
