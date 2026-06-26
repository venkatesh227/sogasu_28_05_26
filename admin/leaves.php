<?php
ob_start();
session_start();
include '../includes/db.php';

// Handle Process (Approve/Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process') {
    $req_id = $_POST['request_id'];
    $status = $_POST['status'];
    $admin_note = $_POST['admin_note'];
    $processed_by = $_SESSION['user_id'] ?? 1;

    try {
        $pdo->beginTransaction();
        
        // 1. Get Request Details
        $stmt = $pdo->prepare("SELECT * FROM leave_requests WHERE id = ?");
        $stmt->execute([$req_id]);
        $req = $stmt->fetch();
        
        if ($req && $req['status'] === 'Pending') {
// 2. Update Request
$uStmt = $pdo->prepare("UPDATE leave_requests
    SET status = ?, admin_note = ?, processed_at = CURRENT_TIMESTAMP, processed_by = ?
    WHERE id = ?");
$uStmt->execute([$status, $admin_note, $processed_by, $req_id]);

// Send Notification
if ($status == 'Approved') {

    $title = 'Leave Request Approved';
    $message = "Your leave request from "
        . date('d M Y', strtotime($req['start_date']))
        . " to "
        . date('d M Y', strtotime($req['end_date']))
        . " has been approved.";

} else {

    $title = 'Leave Request Rejected';
    $message = "Your leave request from "
        . date('d M Y', strtotime($req['start_date']))
        . " to "
        . date('d M Y', strtotime($req['end_date']))
        . " has been rejected.";

}

$notify = $pdo->prepare("
    INSERT INTO notifications
    (employee_id, title, message)
    VALUES (?, ?, ?)
");

$notify->execute([
    $req['employee_id'],
    $title,
    $message
]);

// 3. Update Balance and Attendance if Approved
$year = date('Y', strtotime($req['start_date']));
if ($status === 'Approved') {
                
                // Deduct Balance
                $dStmt = $pdo->prepare("UPDATE employee_leave_balances SET balance = balance - ? WHERE employee_id = ? AND leave_type_id = ? AND year = ?");
                $dStmt->execute([$req['total_days'], $req['employee_id'], $req['leave_type_id'], $year]);

                // Mark Attendance
                $begin = new DateTime($req['start_date']);
                $end = new DateTime($req['end_date']);
                $end->modify('+1 day'); // Include end date
                $interval = new DateInterval('P1D');
                $period = new DatePeriod($begin, $interval, $end);

                $attStmt = $pdo->prepare("INSERT INTO attendance (employee_id, attendance_date, status, working_from) VALUES (?, ?, 'On Leave', 'Home') ON DUPLICATE KEY UPDATE status = 'On Leave'");
                foreach ($period as $date) {
                    $attStmt->execute([$req['employee_id'], $date->format('Y-m-d')]);
                }
            }
        }
        
$pdo->commit();

$_SESSION['success'] = "Request processed successfully.";

header("Location: leaves.php");
exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: leaves.php?error=" . urlencode($e->getMessage()));
        exit;
    }
}

// Fetch Pending Requests

$pending = $pdo->query("
    SELECT lr.*, e.first_name, e.last_name, e.job_role, lt.name as type_name, lt.color
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id = e.id
    JOIN leave_types lt ON lr.leave_type_id = lt.id
    WHERE lr.status = 'Pending'
    ORDER BY lr.applied_at ASC
")->fetchAll();

// Fetch All Requests (History)
$history = $pdo->query("
    SELECT lr.*, e.first_name, e.last_name, lt.name as type_name, lt.color
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id = e.id
    JOIN leave_types lt ON lr.leave_type_id = lt.id
    WHERE lr.status != 'Pending'
    ORDER BY lr.processed_at DESC
    LIMIT 100
")->fetchAll();

$pageTitle = "Leave Management - Sogasu";
$activePage = "leaves";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="display: flex; flex-direction: column; gap: 1.5rem; width: 100%; max-width: 100%;">
        
        <!-- Premium Header Area -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 0.5rem;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Leave Requests</h2>
                <p style="color: #64748b; margin-top: 0.25rem; margin-bottom: 0;">Review, approve, and process employee leave applications.</p>
            </div>
            
            <div class="glass-card" style="padding: 0.5rem 1rem; border-radius: 10px; display: flex; align-items: center; gap: 0.75rem; box-shadow: var(--shadow-sm); margin: 0; border: 1px solid #e2e8f0;">
                <span style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Pending Requests</span>
                <span style="font-size: 1.15rem; font-weight: 800; color: var(--primary);"><?= count($pending) ?></span>
            </div>
        </div>

        <!-- Pending Section -->
        <?php if (empty($pending)): ?>
            <div class="glass-card" style="padding: 4rem; text-align: center; border-radius: 16px; border: 1px solid #e2e8f0;">
                <div style="width: 72px; height: 72px; background: #ecfdf5; color: #059669; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; font-size: 2.25rem; box-shadow: var(--shadow-sm);">
                    <i class="ri-checkbox-circle-line"></i>
                </div>
                <h3 style="font-size: 1.35rem; font-weight: 800; color: var(--text-dark); margin: 0 0 0.5rem 0;">All Caught Up!</h3>
                <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0; font-weight: 500;">There are no pending leave requests to process.</p>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 1.5rem;">
                <?php foreach ($pending as $r): 
                    $initials = strtoupper(substr($r['first_name'], 0, 1) . substr($r['last_name'], 0, 1));
                ?>
                    <div class="glass-card" style="padding: 0; overflow: hidden; border-radius: 16px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; height: 100%;">
                        <!-- Card Header -->
                        <div style="padding: 1.25rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; background: #fafbfc;">
                            <div style="display: flex; align-items: center; gap: 0.85rem;">
                                <div class="premium-avatar" style="width: 44px; height: 44px; font-size: 1.1rem; background: #e0e7ff; color: var(--primary); font-weight: 800; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: var(--shadow-sm);">
                                    <?= $initials ?>
                                </div>
                                <div>
                                    <div style="font-weight: 750; color: var(--text-dark); font-size: 1rem; line-height: 1.2;"><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></div>
                                    <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;"><?= htmlspecialchars($r['job_role']) ?></span>
                                </div>
                            </div>
                            <span style="background: <?= $r['color'] ?>15; color: <?= $r['color'] ?>; border: 1px solid <?= $r['color'] ?>30; font-weight: 800; padding: 4px 10px; border-radius: 20px; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.05em;"><?= $r['type_name'] ?></span>
                        </div>
                        
                        <!-- Card Body -->
                        <div style="padding: 1.25rem; display: flex; flex-direction: column; flex: 1;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                                <div>
                                    <div style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem;">Duration</div>
                                    <div style="font-weight: 700; color: var(--text-dark); font-size: 0.85rem; display: flex; align-items: center; gap: 0.35rem;">
                                        <i class="ri-calendar-line" style="color: var(--primary); font-size: 1rem;"></i>
                                        <?= date('d M', strtotime($r['start_date'])) ?> - <?= date('d M', strtotime($r['end_date'])) ?>
                                    </div>
                                </div>
                                <div>
                                    <div style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem;">Total Days</div>
                                    <div style="font-weight: 800; color: var(--primary); font-size: 1.15rem;"><?= $r['total_days'] ?> <?= $r['total_days'] > 1 ? 'Days' : 'Day' ?></div>
                                </div>
                            </div>
                            
                            <div style="background: #f8fafc; padding: 1rem; border-radius: 12px; border: 1px solid #f1f5f9; font-size: 0.85rem; color: #475569; margin-bottom: 1.5rem; line-height: 1.45; flex: 1;">
                                <strong style="color: var(--text-dark); display: block; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem;">Reason:</strong>
                                <?= htmlspecialchars($r['reason']) ?>
                            </div>
                            
                            <!-- Card Actions -->
                            <div style="display: flex; gap: 0.75rem; margin-top: auto;">
<button onclick="openProcessModal(<?= $r['id'] ?>, 'Approved')"
    style="flex:1;background:#22c55e;color:white;border:none;padding:10px;border-radius:8px;font-weight:700;">
    <i class="ri-check-line"></i> Approve
</button>

<button onclick="openProcessModal(<?= $r['id'] ?>, 'Rejected')"
    style="flex:1;background:#ef4444;color:white;border:none;padding:10px;border-radius:8px;font-weight:700;">
    <i class="ri-close-line"></i> Reject
</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- History Section -->
        <div class="table-container" style="padding: 1.5rem; margin-top: 0.5rem;">
            <div style="padding-bottom: 1.25rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-dark); margin: 0;">Processed History</h3>
                <span style="font-size: 0.75rem; background: #f8fafc; color: var(--text-muted); padding: 4px 10px; border-radius: 6px; border: 1px solid #f1f5f9; font-weight: 600;">Last 100 requests</span>
            </div>
            
            <div style="overflow-x: auto;">
                <table id="leaveHistoryTable" class="table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Type</th>
                            <th>Dates</th>
                            <th style="text-align: center;">Days</th>
                            <th>Status</th>
                            <th>Processed At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $h): ?>
                            <tr>
                                <td style="font-weight: 700; color: var(--text-dark);"><?= htmlspecialchars($h['first_name'] . ' ' . $h['last_name']) ?></td>
                                <td>
                                    <span style="font-size: 0.75rem; background: <?= $h['color'] ?>15; color: <?= $h['color'] ?>; padding: 3px 8px; border-radius: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.02em; border: 1px solid <?= $h['color'] ?>30;"><?= $h['type_name'] ?></span>
                                </td>
                                <td style="font-weight: 600; color: var(--text-muted);"><?= date('d M, Y', strtotime($h['start_date'])) ?> - <?= date('d M, Y', strtotime($h['end_date'])) ?></td>
                                <td style="text-align: center; font-weight: 800; color: var(--text-dark);"><?= $h['total_days'] ?></td>
                                <td>
                                    <?php if ($h['status'] === 'Approved'): ?>
                                        <span class="premium-badge badge-active">Approved</span>
                                    <?php else: ?>
                                        <span class="premium-badge badge-delayed">Rejected</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 0.82rem; color: var(--text-muted); font-weight: 500;"><?= date('d M, h:i A', strtotime($h['processed_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>
<!-- Premium Process Modal -->
<div id="processModal" class="premium-modal-overlay">
    <div class="glass-card premium-modal-content" style="max-width: 440px; border-radius: 16px; border: 1px solid #e2e8f0; padding: 1.5rem; background: white; box-shadow: var(--shadow-lg);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
            <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--text-dark);"><span id="modalActionText"></span> Request</h3>
            <button onclick="closeProcessModal()" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 4px; border-radius: 50%; transition: all 0.2s;"><i class="ri-close-line"></i></button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="process">
            <input type="hidden" id="modalRequestId" name="request_id">
            <input type="hidden" id="modalStatus" name="status">
            
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Admin Remarks (Optional)</label>
                <textarea name="admin_note" rows="3" placeholder="Add approval remarks or rejection notes..." style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Roboto', sans-serif; font-size: 0.9rem; outline: none; background: #fafbfc; transition: all 0.2s; resize: none;" onfocus="this.style.borderColor='var(--primary)'; this.style.background='white';"></textarea>
            </div>
            
            <div style="display: flex; gap: 0.75rem;">
                <button type="button" onclick="closeProcessModal()" class="btn btn-secondary" style="flex: 1; padding: 10px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; background: #f1f5f9; color: var(--text-muted); border: none;">Cancel</button>
                <button type="submit" id="modalSubmitBtn" class="btn" style="flex: 1.5; padding: 10px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; color: white;">Confirm Action</button>
            </div>
        </form>
    </div>
</div>

<style>
    .premium-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(8px);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        animation: fadeIn 0.3s ease-out;
    }
    .premium-modal-content {
        width: 100%;
        animation: slideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes slideUp { from { opacity: 0; transform: translateY(20px) scale(0.95); } to { opacity: 1; transform: translateY(0) scale(1); } }
</style>

<?php include __DIR__ . '/includes/datatable.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function() {
        initializeDataTable('leaveHistoryTable', 'Processed Leave History');
    });

    function openProcessModal(id, status) {
        document.getElementById('modalRequestId').value = id;
        document.getElementById('modalStatus').value = status;
        document.getElementById('modalActionText').innerText = status;
        
        const btn = document.getElementById('modalSubmitBtn');
        btn.innerText = 'Confirm ' + status;
        btn.style.background = status === 'Approved' ? '#059669' : '#e11d48';
        btn.style.borderColor = status === 'Approved' ? '#059669' : '#e11d48';
        
        document.getElementById('processModal').style.display = 'flex';
    }

    function closeProcessModal() {
        document.getElementById('processModal').style.display = 'none';
    }

    // Close modal on click outside

    window.onclick = function(event) {
        if (event.target == document.getElementById('processModal')) closeProcessModal();
    }
</script>

<?php if (isset($_SESSION['success'])): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Success',
    text: '<?= $_SESSION['success']; ?>',
    timer: 1500,
    showConfirmButton: false
});
</script>
<?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
