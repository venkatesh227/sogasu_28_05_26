<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id FROM employees WHERE user_id = ? AND is_deleted = 0");
$stmt->execute([$user_id]);
$employee = $stmt->fetch();
$employee_id = $employee['id'];
$current_year = date('Y');

// Ensure balances are initialized for this year
$types = $pdo->query("SELECT * FROM leave_types")->fetchAll();
foreach ($types as $type) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO employee_leave_balances (employee_id, leave_type_id, balance, year) VALUES (?, ?, ?, ?)");
    $stmt->execute([$employee_id, $type['id'], $type['default_allowance'], $current_year]);
}

// Handle Form Submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply') {
    $type_id = $_POST['leave_type_id'];
    $start = $_POST['start_date'];
    $end = $_POST['end_date'];
    $reason = $_POST['reason'];
    
    // Calculate days (simple diff)
    $d1 = new DateTime($start);
    $d2 = new DateTime($end);
    $total_days = $d1->diff($d2)->days + 1;
    
    // Check balance
    $bStmt = $pdo->prepare("SELECT balance FROM employee_leave_balances WHERE employee_id = ? AND leave_type_id = ? AND year = ?");
    $bStmt->execute([$employee_id, $type_id, $current_year]);
    $current_bal = $bStmt->fetchColumn();
    
    if ($total_days > $current_bal) {
        $message = "Insufficient balance. You only have $current_bal days left.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, total_days, reason) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$employee_id, $type_id, $start, $end, $total_days, $reason])) {
            header("Location: leaves.php?success=1");
            exit;
        }
    }
}

// Fetch Balances
$stmt = $pdo->prepare("
    SELECT lb.*, lt.name, lt.color 
    FROM employee_leave_balances lb 
    JOIN leave_types lt ON lb.leave_type_id = lt.id 
    WHERE lb.employee_id = ? AND lb.year = ?
");
$stmt->execute([$employee_id, $current_year]);
$balances = $stmt->fetchAll();

// Fetch Requests
$stmt = $pdo->prepare("
    SELECT lr.*, lt.name as type_name, lt.color 
    FROM leave_requests lr 
    JOIN leave_types lt ON lr.leave_type_id = lt.id 
    WHERE lr.employee_id = ? 
    ORDER BY lr.applied_at DESC
");
$stmt->execute([$employee_id]);
$requests = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT permission_key
    FROM role_permissions
    WHERE role_name='Supervisor'
");
$stmt->execute();
$permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
$pageTitle = "Leave Management - Sogasu";
$headerTitle = "Leave Management";
$activePage = "leaves";
include 'includes/header.php';
?>

<div class="container">
    <!-- Balances Section -->
    <div class="section-title">Leave Balance (<?= $current_year ?>)</div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 0.75rem; margin-bottom: 1.5rem;">
        <?php foreach ($balances as $b): ?>
            <div class="card" style="padding: 1rem; text-align: center; border-left: 4px solid <?= $b['color'] ?>;">
                <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.25rem;"><?= $b['name'] ?></div>
                <div style="font-size: 1.25rem; font-weight: 800; color: #1e293b;"><?= number_format($b['balance'], 1) ?> <span style="font-size: 0.8rem; font-weight: 400;">days</span></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Apply Button -->
<?php if(in_array('leave_applications_create', $permissions)): ?>

<button onclick="document.getElementById('applyModal').style.display='flex'"
class="punch-btn"
style="width: 100%; background: #4338ca; color: white; border: none; padding: 1rem; border-radius: 12px; font-weight: 700; margin-bottom: 2rem;">
    <i class="ri-add-circle-line"></i> Apply New Leave
</button>

<?php endif; ?>
    <!-- Recent Requests -->
    <div class="section-title">History</div>
    <?php if (empty($requests)): ?>
        <div class="card" style="text-align: center; color: #94a3b8; padding: 2rem;">No leave history found.</div>
    <?php else: ?>
        <?php foreach ($requests as $r): ?>
            <div class="card" style="margin-bottom: 0.75rem; padding: 1rem; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                        <span style="font-size: 0.85rem; font-weight: 700; color: #1e293b;"><?= date('d M', strtotime($r['start_date'])) ?> - <?= date('d M', strtotime($r['end_date'])) ?></span>
                        <span style="font-size: 0.7rem; background: <?= $r['color'] ?>15; color: <?= $r['color'] ?>; padding: 0.1rem 0.4rem; border-radius: 4px; font-weight: 600;"><?= $r['type_name'] ?></span>
                    </div>
                    <div style="font-size: 0.75rem; color: #64748b;"><?= $r['total_days'] ?> day(s) • <?= htmlspecialchars($r['reason']) ?></div>
                </div>
                <div>
                    <?php 
                        $statusColor = $r['status'] === 'Approved' ? '#059669' : ($r['status'] === 'Rejected' ? '#e11d48' : '#d97706');
                        $statusBg = $statusColor . '15';
                    ?>
                    <span style="font-size: 0.75rem; background: <?= $statusBg ?>; color: <?= $statusColor ?>; padding: 0.25rem 0.6rem; border-radius: 20px; font-weight: 700;">
                        <?= strtoupper($r['status']) ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if(in_array('leave_applications_create', $permissions)): ?>

<!-- Apply Modal -->
<div id="applyModal" style="display: <?= isset($_GET['success']) || !empty($message) ? 'none' : 'none' ?>; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px); padding: 1rem;">
    <div style="background: white; width: 100%; max-width: 400px; border-radius: 16px; overflow: hidden; animation: slideUp 0.3s ease-out;">
        <div style="padding: 1.25rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700;">Apply for Leave</h3>
            <button onclick="document.getElementById('applyModal').style.display='none'" style="background: none; border: none; color: #94a3b8; font-size: 1.5rem;"><i class="ri-close-line"></i></button>
        </div>
        <form method="POST" style="padding: 1.25rem;">
            <input type="hidden" name="action" value="apply">
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.8rem; color: #64748b; margin-bottom: 0.4rem;">Leave Type</label>
                <select name="leave_type_id" required style="width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc;">
                    <?php foreach ($balances as $b): ?>
                        <option value="<?= $b['leave_type_id'] ?>"><?= $b['name'] ?> (<?= $b['balance'] ?> left)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="display: block; font-size: 0.8rem; color: #64748b; margin-bottom: 0.4rem;">From</label>
                    <input type="date" name="start_date" required min="<?= date('Y-m-d') ?>" style="width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 10px;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.8rem; color: #64748b; margin-bottom: 0.4rem;">To</label>
                    <input type="date" name="end_date" required min="<?= date('Y-m-d') ?>" style="width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 10px;">
                </div>
            </div>
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.8rem; color: #64748b; margin-bottom: 0.4rem;">Reason</label>
                <textarea name="reason" rows="3" required placeholder="Brief reason for leave..." style="width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 10px; font-family: inherit;"></textarea>
            </div>
            <button type="submit" class="punch-btn" style="width: 100%; background: #4338ca; color: white; border: none; padding: 1rem; border-radius: 12px; font-weight: 700;">Submit Application</button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if (isset($_GET['success'])): ?>
<script>Swal.fire({ icon: 'success', title: 'Applied!', text: 'Your leave request has been submitted.', timer: 2000, showConfirmButton: false });</script>
<?php endif; ?>
<?php if (!empty($message)): ?>
<script>Swal.fire({ icon: 'error', title: 'Oops!', text: '<?= $message ?>' });</script>
<?php endif; ?>

<style>
@keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
</style>
<?php endif; ?>
<?php include 'includes/bottom-nav.php'; ?>
