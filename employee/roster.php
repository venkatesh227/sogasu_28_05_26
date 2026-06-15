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

if (!in_array('appointments_view', $permissions)) {
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

// Handle AJAX: Submit Shift Change Request
if (isset($_POST['action']) && $_POST['action'] === 'request_shift_change') {
    header('Content-Type: application/json');
    try {
        $date = $_POST['request_date'];
        $new_shift_id = $_POST['requested_shift_id'];
        $reason = $_POST['reason'];
        
        $stmt = $pdo->prepare("INSERT INTO shift_requests (employee_id, request_date, requested_shift_id, reason) VALUES (?, ?, ?, ?)");
        $stmt->execute([$employee_id, $date, $new_shift_id, $reason]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Get current week dates
$monday = date('Y-m-d', strtotime('monday this week'));
$week_dates = [];
for ($i = 0; $i < 7; $i++) {
    $week_dates[] = date('Y-m-d', strtotime("$monday +$i days"));
}

// Fetch roster for the week
$stmt = $pdo->prepare("
    SELECT r.roster_date, s.name as shift_name, s.start_time, s.end_time, s.color, s.short_code
    FROM shift_roster r
    JOIN shift_types s ON r.shift_type_id = s.id
    WHERE r.employee_id = ? AND r.roster_date BETWEEN ? AND ?
");
$stmt->execute([$employee_id, $week_dates[0], $week_dates[6]]);
$roster_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$roster = [];
foreach ($roster_data as $row) {
    $roster[$row['roster_date']] = $row;
}

// Fetch available shift types for the request modal
$shift_types = $pdo->query("SELECT id, name, start_time, end_time FROM shift_types ORDER BY name ASC")->fetchAll();

$pageTitle = "Shift Roster - Sogasu";
$headerTitle = "My Schedule";
$activePage = "roster";
include 'includes/header.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <div class="section-title" style="margin-bottom: 0;">This Week</div>
        <div style="font-size: 0.8rem; color: #64748b; font-weight: 600;"><?= date('M d', strtotime($week_dates[0])) ?> - <?= date('d, Y', strtotime($week_dates[6])) ?></div>
    </div>

    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
        <?php foreach ($week_dates as $date): 
            $day_name = date('D', strtotime($date));
            $day_num = date('d', strtotime($date));
            $is_today = ($date === date('Y-m-d'));
            $shift = $roster[$date] ?? null;
        ?>
            <div class="card" style="padding: 0; overflow: hidden; border-left: 4px solid <?= $shift ? $shift['color'] : '#e2e8f0' ?>; <?= $is_today ? 'background: #fff; box-shadow: 0 4px 12px rgba(219, 39, 119, 0.1); border-color: #db2777;' : '' ?>">
                <div style="display: flex; align-items: center;">
                    <!-- Date Column -->
                    <div style="width: 65px; padding: 1rem; text-align: center; border-right: 1px solid #f1f5f9; background: <?= $is_today ? '#fdf2f8' : 'transparent' ?>;">
                        <div style="font-size: 0.7rem; font-weight: 700; color: <?= $is_today ? '#db2777' : '#94a3b8' ?>; text-transform: uppercase;"><?= $day_name ?></div>
                        <div style="font-size: 1.25rem; font-weight: 800; color: <?= $is_today ? '#db2777' : '#1e293b' ?>;"><?= $day_num ?></div>
                    </div>

                    <!-- Shift Column -->
                    <div style="padding: 1rem; flex-grow: 1;">
                        <?php if ($shift): ?>
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <div>
                                    <div style="font-weight: 700; color: #1e293b; font-size: 1rem; margin-bottom: 0.25rem;"><?= htmlspecialchars($shift['shift_name']) ?></div>
                                    <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; color: #64748b; font-weight: 600;">
                                        <i class="ri-time-line"></i>
                                        <?= date('h:i A', strtotime($shift['start_time'])) ?> - <?= date('h:i A', strtotime($shift['end_time'])) ?>
                                    </div>
                                </div>
                                <div style="background: <?= $shift['color'] ?>20; color: <?= $shift['color'] ?>; padding: 4px 10px; border-radius: 6px; font-size: 0.7rem; font-weight: 800;">
                                    <?= $shift['short_code'] ?>
                                </div>
                            </div>
                            <!-- Request Change Link -->
                            <div style="margin-top: 0.5rem; text-align: right;">
<?php if (in_array('appointments_create', $permissions)): ?>

<button onclick="openRequestModal('<?= $date ?>', '<?= htmlspecialchars($shift['shift_name']) ?>')" style="background: none; border: none; color: #db2777;">
    <i class="ri-edit-line"></i> Request Change
</button>

<?php endif; ?>                            </div>
                        <?php else: ?>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div style="color: #94a3b8; font-size: 0.85rem; font-style: italic;">No shift assigned / Day Off</div>
                                <button onclick="openRequestModal('<?= $date ?>', 'None')" style="background: #f1f5f9; border: none; color: #475569; font-size: 0.65rem; font-weight: 700; padding: 4px 8px; border-radius: 4px; cursor: pointer;">
                                    Assign Shift
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php if (in_array('appointments_create', $permissions)): ?>
<!-- Request Change Modal -->
<div id="requestModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; align-items: flex-end;">
    <div style="background: white; width: 100%; border-radius: 24px 24px 0 0; padding: 1.5rem; animation: slideUp 0.3s ease-out;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: #1e293b;">Shift Change Request</h3>
            <button onclick="closeRequestModal()" style="border: none; background: #f1f5f9; width: 32px; height: 32px; border-radius: 50%; color: #64748b;">&times;</button>
        </div>
      
        <form id="requestForm" onsubmit="event.preventDefault(); submitRequest()">
            <input type="hidden" name="request_date" id="modal-date">
            
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #64748b; margin-bottom: 0.5rem;">Date</label>
                <div id="modal-date-display" style="font-weight: 700; color: #1e293b;">-</div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #64748b; margin-bottom: 0.5rem;">Desired Shift <span style="color: #ef4444;">*</span></label>
                <select name="requested_shift_id" required style="width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 12px; outline: none; background: #f8fafc; font-size: 0.95rem;">
                    <?php foreach ($shift_types as $st): ?>
                        <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['name']) ?> (<?= date('H:i', strtotime($st['start_time'])) ?> - <?= date('H:i', strtotime($st['end_time'])) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #64748b; margin-bottom: 0.5rem;">Reason for Change</label>
                <textarea name="reason" placeholder="e.g. Personal emergency, Transportation issues..." rows="3" style="width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 12px; outline: none; resize: none; font-size: 0.95rem;"></textarea>
            </div>

            <button type="submit" style="width: 100%; background: #db2777; color: white; border: none; padding: 1rem; border-radius: 16px; font-weight: 700; font-size: 1rem; box-shadow: 0 10px 15px -3px rgba(219, 39, 119, 0.3);">Submit Request</button>
        </form>
    </div>
</div>
  <?php endif; ?>
<style>
    @keyframes slideUp {
        from { transform: translateY(100%); }
        to { transform: translateY(0); }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function openRequestModal(date, shiftName) {
    document.getElementById('modal-date').value = date;
    const d = new Date(date);
    document.getElementById('modal-date-display').innerText = d.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
    document.getElementById('requestModal').style.display = 'flex';
}

function closeRequestModal() {
    document.getElementById('requestModal').style.display = 'none';
}

function submitRequest() {
    const form = document.getElementById('requestForm');
    const formData = new FormData(form);
    formData.append('action', 'request_shift_change');

    fetch('roster.php', {
        method: 'POST',
        body: new URLSearchParams(formData)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Request Sent',
                text: 'Your shift change request has been submitted to admin.',
                timer: 2000,
                showConfirmButton: false
            }).then(() => closeRequestModal());
        } else {
            Swal.fire('Error', data.error, 'error');
        }
    });
}
</script>

<?php include 'includes/bottom-nav.php'; ?>
