<?php
session_start();
require_once '../includes/db.php';
$stmt = $pdo->prepare("
    SELECT job_role
    FROM employees
    WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);

$role_name = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT permission_key
    FROM role_permissions
    WHERE role_name = ?
");
$stmt->execute([$role_name]);

$permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (
    isset($_SESSION['role']) &&
    $_SESSION['role'] === 'employee' &&
    !in_array('hr_view', $permissions)
) {
    header("Location: profile.php");
    exit();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch employee ID
$stmt = $pdo->prepare("SELECT id FROM employees WHERE user_id = ? AND is_deleted = 0");
$stmt->execute([$user_id]);
$emp = $stmt->fetch();
$employee_id = $emp['id'];
$today = date('Y-m-d');

// Fetch today's summary
$stmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
$stmt->execute([$employee_id, $today]);
$today_att = $stmt->fetch();

// Fetch today's shift
$shiftStmt = $pdo->prepare("
    SELECT st.* 
    FROM shift_roster sr
    JOIN shift_types st ON sr.shift_type_id = st.id
    WHERE sr.employee_id = ? AND sr.roster_date = ?
");
$shiftStmt->execute([$employee_id, $today]);
$today_shift = $shiftStmt->fetch();

// Handle Punch In/Out
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $time = date('H:i:s');
    $log_type = ($action === 'punch_in') ? 'In' : 'Out';
    
    // Check shift if punching in
    if ($action === 'punch_in') {
        if (!$today_shift || strpos(strtolower($today_shift['name']), 'off') !== false) {
            echo json_encode(['success' => false, 'error' => 'You do not have an active shift assigned for today.']);
            exit;
        }
    }
    
    try {
        $pdo->beginTransaction();

        // 1. Insert Log
        $logStmt = $pdo->prepare("INSERT INTO attendance_logs (employee_id, log_date, log_time, log_type) VALUES (?, ?, ?, ?)");
        $logStmt->execute([$employee_id, $today, $time, $log_type]);

        // 2. Update/Insert Summary in attendance table
        if ($action === 'punch_in') {
            $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, attendance_date, status, check_in, working_from) 
                                 VALUES (?, ?, 'Present', ?, 'Office') 
                                 ON DUPLICATE KEY UPDATE status = 'Present'");
            $stmt->execute([$employee_id, $today, $time]);
        } else {
            $stmt = $pdo->prepare("UPDATE attendance SET check_out = ? WHERE employee_id = ? AND attendance_date = ?");
            $stmt->execute([$time, $employee_id, $today]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'time' => date('h:i A')]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Fetch today's last log
$stmt = $pdo->prepare("SELECT log_type FROM attendance_logs WHERE employee_id = ? AND log_date = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$employee_id, $today]);
$last_log = $stmt->fetchColumn();

// Fetch recent history
$stmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? ORDER BY attendance_date DESC LIMIT 5");
$stmt->execute([$employee_id]);
$history = $stmt->fetchAll();

$pageTitle = "Attendance - Sogasu";
$headerTitle = "Attendance";
$activePage = "attendance";
include 'includes/header.php';
?>

<div class="container">
    <div class="card" style="text-align: center; padding: 2rem 1rem;">
        <div style="font-size: 0.9rem; color: #64748b; margin-bottom: 0.5rem;"><?= date('l, d M Y') ?></div>
        <div id="current-time" style="font-size: 2.5rem; font-weight: 800; color: #1e293b; margin-bottom: 0.5rem;">00:00:00</div>
        
        <?php if ($today_shift): ?>
            <div style="margin-bottom: 2rem; display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.25rem 0.75rem; background: <?= $today_shift['color'] ?>15; color: <?= $today_shift['color'] ?>; border-radius: 20px; font-size: 0.75rem; font-weight: 700;">
                <i class="ri-time-line"></i> <?= htmlspecialchars($today_shift['name']) ?>: <?= date('h:i A', strtotime($today_shift['start_time'])) ?> - <?= date('h:i A', strtotime($today_shift['end_time'])) ?>
            </div>
        <?php else: ?>
            <div style="margin-bottom: 2rem; display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.25rem 0.75rem; background: #fee2e2; color: #ef4444; border-radius: 20px; font-size: 0.75rem; font-weight: 700;">
                <i class="ri-error-warning-line"></i> No shift assigned for today
            </div>
        <?php endif; ?>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
            <div style="background: #f8fafc; padding: 1rem; border-radius: 12px; border: 1px solid #e2e8f0;">
                <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.25rem;">Check In</div>
                <div style="font-weight: 700; color: #1e293b; font-size: 1.1rem;"><?= (!empty($today_att) && $today_att['check_in']) ? date('h:i A', strtotime($today_att['check_in'])) : '--:--' ?></div>
            </div>
            <div style="background: #f8fafc; padding: 1rem; border-radius: 12px; border: 1px solid #e2e8f0;">
                <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.25rem;">Check Out</div>
                <div style="font-weight: 700; color: #1e293b; font-size: 1.1rem;"><?= (!empty($today_att) && $today_att['check_out']) ? date('h:i A', strtotime($today_att['check_out'])) : '--:--' ?></div>
            </div>
        </div>
<?php if (in_array('hr_create', $permissions)): ?>
        <?php 
        $canPunchIn = ($today_shift && strpos(strtolower($today_shift['name']), 'off') === false);
        if (!$last_log || $last_log === 'Out'): ?>
            <button onclick="<?= $canPunchIn ? "punch('punch_in')" : "Swal.fire('No Shift', 'You cannot punch in because you have no shift assigned today.', 'warning')" ?>" 
                class="punch-btn" 
                style="width: 100%; background: <?= $canPunchIn ? '#059669' : '#94a3b8' ?>; color: white; border: none; padding: 1.25rem; border-radius: 16px; font-weight: 700; font-size: 1.1rem; display: flex; align-items: center; justify-content: center; gap: 0.75rem; box-shadow: <?= $canPunchIn ? '0 10px 15px -3px rgba(5, 150, 105, 0.3)' : 'none' ?>;">
                <i class="ri-login-circle-line"></i> PUNCH IN
            </button>
        <?php else: ?>
            <button onclick="punch('punch_out')" class="punch-btn" style="width: 100%; background: #e11d48; color: white; border: none; padding: 1.25rem; border-radius: 16px; font-weight: 700; font-size: 1.1rem; display: flex; align-items: center; justify-content: center; gap: 0.75rem; box-shadow: 0 10px 15px -3px rgba(225, 29, 72, 0.3);">
                <i class="ri-logout-circle-line"></i> PUNCH OUT
            </button>
        <?php endif; ?>
<?php endif; ?>
    </div>

    <div class="section-title" style="margin-top: 1.5rem;">Recent Logs</div>
    <div class="card" style="padding: 0;">
        <?php foreach ($history as $index => $log): ?>
            <div style="padding: 1rem; display: flex; justify-content: space-between; align-items: center; <?= $index < count($history)-1 ? 'border-bottom: 1px solid #f1f5f9;' : '' ?>">
                <div>
                    <div style="font-weight: 600; color: #1e293b; font-size: 0.9rem;"><?= date('d M, Y', strtotime($log['attendance_date'])) ?></div>
                    <div style="font-size: 0.75rem; color: #94a3b8;">Status: <span style="color: #059669; font-weight: 600;"><?= $log['status'] ?></span></div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 0.8rem; font-weight: 700; color: #475569;"><?= $log['check_in'] ? date('h:i A', strtotime($log['check_in'])) : '-' ?></div>
                    <div style="font-size: 0.8rem; font-weight: 700; color: #94a3b8;"><?= $log['check_out'] ? date('h:i A', strtotime($log['check_out'])) : '-' ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('alert') === 'punchin_required') {
        Swal.fire({
            icon: 'warning',
            title: 'Action Blocked',
            text: 'You must punch in your attendance before you can perform any work actions.',
            confirmButtonColor: '#059669'
        });
        // Remove the parameter from URL without reloading
        if (window.history.replaceState) {
            const newUrl = window.location.pathname;
            window.history.replaceState({}, '', newUrl);
        }
    }
});

function updateTime() {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
    document.getElementById('current-time').innerText = timeStr;
}
setInterval(updateTime, 1000);
updateTime();

function punch(action) {
    const title = action === 'punch_in' ? 'Punch In?' : 'Punch Out?';
    Swal.fire({
        title: title,
        text: "Are you sure you want to mark your attendance?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: action === 'punch_in' ? '#059669' : '#e11d48',
        confirmButtonText: 'Yes, Confirm'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('attendance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=' + action
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: 'Attendance recorded at ' + data.time,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', data.error, 'error');
                }
            });
        }
    });
}
</script>

<?php include 'includes/bottom-nav.php'; ?>
