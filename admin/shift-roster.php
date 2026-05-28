<?php
session_start();
include '../includes/db.php';

// Handle AJAX shift updates
if (isset($_POST['action']) && $_POST['action'] === 'update_shift') {
    header('Content-Type: application/json');
    try {
        $emp_id = $_POST['employee_id'];
        $date = $_POST['date'];
        $shift_type_id = $_POST['shift_type_id'];

        // Validation: Cannot edit past dates
        if ($date < date('Y-m-d')) {
            echo json_encode(['success' => false, 'error' => 'Cannot edit shifts for past dates.']);
            exit;
        }

        if ($shift_type_id == 0) {
            // Remove shift
            $stmt = $pdo->prepare("DELETE FROM shift_roster WHERE employee_id = ? AND roster_date = ?");
            $stmt->execute([$emp_id, $date]);
        } else {
            // Upsert shift
            $stmt = $pdo->prepare("INSERT INTO shift_roster (employee_id, roster_date, shift_type_id) 
                                 VALUES (?, ?, ?) 
                                 ON DUPLICATE KEY UPDATE shift_type_id = VALUES(shift_type_id)");
            $stmt->execute([$emp_id, $date, $shift_type_id]);
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle AJAX shift type updates
if (isset($_POST['action']) && $_POST['action'] === 'update_shift_type') {
    header('Content-Type: application/json');
    try {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $start = $_POST['start_time'];
        $end = $_POST['end_time'];
        $color = $_POST['color'];
        $short_code = $_POST['short_code'];
        $auto_clock_out = $_POST['auto_clock_out'];
        $half_day_mark = $_POST['half_day_mark_time'];
        $early_clock_in = $_POST['early_clock_in'];
        $late_mark = $_POST['late_mark_after'];
        $max_checkin = $_POST['max_checkin'];
        $office_days = isset($_POST['office_days']) ? implode(',', $_POST['office_days']) : '';

        $stmt = $pdo->prepare("UPDATE shift_types SET name = ?, start_time = ?, end_time = ?, color = ?, short_code = ?, auto_clock_out = ?, half_day_mark_time = ?, early_clock_in = ?, late_mark_after = ?, max_checkin = ?, office_opens_on = ? WHERE id = ?");
        $stmt->execute([$name, $start, $end, $color, $short_code, $auto_clock_out, $half_day_mark, $early_clock_in, $late_mark, $max_checkin, $office_days, $id]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle AJAX: Approve/Reject Shift Request
if (isset($_POST['action']) && $_POST['action'] === 'process_shift_request') {
    header('Content-Type: application/json');
    try {
        $request_id = $_POST['id'];
        $status = $_POST['status']; // 'Approved' or 'Rejected'
        $remark = $_POST['remark'];

        // Start transaction
        $pdo->beginTransaction();

        // Update request status and trigger notification
        $stmt = $pdo->prepare("UPDATE shift_requests SET status = ?, admin_remark = ?, is_notified = 0 WHERE id = ?");
        $stmt->execute([$status, $remark, $request_id]);

        if ($status === 'Approved') {
            // Fetch request details to update roster
            $stmt = $pdo->prepare("SELECT * FROM shift_requests WHERE id = ?");
            $stmt->execute([$request_id]);
            $req = $stmt->fetch();

            if ($req) {
                // Update shift_roster
                $stmt = $pdo->prepare("INSERT INTO shift_roster (employee_id, roster_date, shift_type_id) 
                                     VALUES (?, ?, ?) 
                                     ON DUPLICATE KEY UPDATE shift_type_id = VALUES(shift_type_id)");
                $stmt->execute([$req['employee_id'], $req['request_date'], $req['requested_shift_id']]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Date Navigation
$current_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$week_start = date('Y-m-d', strtotime('monday this week', strtotime($current_date)));
$week_end = date('Y-m-d', strtotime('sunday this week', strtotime($current_date)));

$prev_week = date('Y-m-d', strtotime('-1 week', strtotime($week_start)));
$next_week = date('Y-m-d', strtotime('+1 week', strtotime($week_start)));

// Fetch Shift Types
$shift_types = $pdo->query("SELECT * FROM shift_types ORDER BY id")->fetchAll();

// Fetch Roles for filter
$roles = $pdo->query("SELECT DISTINCT role_name FROM job_roles WHERE is_deleted = 0 ORDER BY role_name ASC")->fetchAll();

// Filters
$filter_name = isset($_GET['employee_name']) ? trim($_GET['employee_name']) : '';
$filter_role = isset($_GET['role']) ? trim($_GET['role']) : '';

// Fetch Employees with filters
$query = "SELECT id, first_name, last_name, job_role FROM employees WHERE is_deleted = 0";
$params = [];

if ($filter_name !== '') {
    $query .= " AND (first_name LIKE ? OR last_name LIKE ?)";
    $params[] = "%$filter_name%";
    $params[] = "%$filter_name%";
}

if ($filter_role !== '') {
    $query .= " AND job_role = ?";
    $params[] = $filter_role;
}

$query .= " ORDER BY first_name ASC";
$emp_stmt = $pdo->prepare($query);
$emp_stmt->execute($params);
$employees = $emp_stmt->fetchAll();

// Fetch Pending Shift Requests
$pending_requests = $pdo->query("
    SELECT r.*, e.first_name, e.last_name, st.name as requested_shift_name
    FROM shift_requests r
    JOIN employees e ON r.employee_id = e.id
    JOIN shift_types st ON r.requested_shift_id = st.id
    WHERE r.status = 'Pending'
    ORDER BY r.request_date ASC
")->fetchAll();

// Fetch Roster Data for the week
$roster_stmt = $pdo->prepare("
    SELECT r.*, s.name as shift_name, s.color, s.start_time, s.end_time
    FROM shift_roster r 
    JOIN shift_types s ON r.shift_type_id = s.id 
    WHERE r.roster_date BETWEEN ? AND ?
");
$roster_stmt->execute([$week_start, $week_end]);
$roster_data = $roster_stmt->fetchAll();

// Organize roster by employee and date
$roster = [];
foreach ($roster_data as $row) {
    $roster[$row['employee_id']][$row['roster_date']] = $row;
}

// Generate week days
$days = [];
for ($i = 0; $i < 7; $i++) {
    $date = date('Y-m-d', strtotime("+$i days", strtotime($week_start)));
    $days[] = [
        'date' => $date,
        'day_name' => date('D', strtotime($date)),
        'day_num' => date('d M', strtotime($date))
    ];
}

$pageTitle = "Shift Roster - Sogasu";
$activePage = "shift-roster";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div >
        
        <!-- Standard Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; ">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Shift Roster</h2>
                <p style="color: #64748b; margin-top: 0.25rem;">Manage staff schedules and approve shift change requests.</p>
            </div>
            
            <div style="display: flex; gap: 0.75rem; align-items: center;">
                <div style="display: flex; align-items: center; background: white; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; height: 38px;">
                    <a href="?date=<?= $prev_week ?>" style="padding: 0 12px; color: #64748b; text-decoration: none; border-right: 1px solid #e2e8f0; display: flex; align-items: center; height: 100%;"><i class="ri-arrow-left-s-line"></i></a>
                    <div style="font-weight: 600; color: #334155; font-size: 0.85rem; padding: 0 15px; text-align: center; background: #f8fafc; display: flex; align-items: center; height: 100%;">
                        <?= date('d M', strtotime($week_start)) ?> - <?= date('d M Y', strtotime($week_end)) ?>
                    </div>
                    <a href="?date=<?= $next_week ?>" style="padding: 0 12px; color: #64748b; text-decoration: none; border-left: 1px solid #e2e8f0; display: flex; align-items: center; height: 100%;"><i class="ri-arrow-right-s-line"></i></a>
                </div>
                
                <button onclick="window.location.href='?date=<?= date('Y-m-d') ?>'" class="btn btn-light" style="height: 38px;">Today</button>
                
                <?php if (count($pending_requests) > 0): ?>
                    <button onclick="showRequestsModal()" class="btn" style="background: #fffbeb; border: 1px solid #fcd34d; color: #b45309; height: 38px; position: relative; font-weight: 600;">
                        <i class="ri-notification-3-line"></i> Requests
                        <span style="position: absolute; top: -6px; right: -6px; background: #ef4444; color: white; width: 18px; height: 18px; border-radius: 50%; font-size: 0.65rem; display: flex; align-items: center; justify-content: center; font-weight: 700;"><?= count($pending_requests) ?></span>
                    </button>
                <?php endif; ?>
                
                <button onclick="showShiftTypeManager()" class="btn btn-primary" style="height: 38px;"><i class="ri-settings-4-line"></i> Configure Shifts</button>
            </div>
        </div>

        <!-- Filters & Legend Container -->
        <div class="table-container" style="padding: 1.25rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; gap: 2rem; flex-wrap: wrap;">
            <form method="GET" style="display: flex; gap: 0.75rem; flex: 1; align-items: center;">
                <input type="hidden" name="date" value="<?= $current_date ?>">
                <div style="position: relative; max-width: 250px; width: 100%;">
                    <i class="ri-search-line" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                    <input type="text" name="employee_name" value="<?= htmlspecialchars($filter_name) ?>" placeholder="Search staff..." class="form-control" style="padding-left: 36px; height: 38px;">
                </div>
                <select name="role" class="form-select" style="max-width: 200px; height: 38px;">
                    <option value="">All Job Roles</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= htmlspecialchars($role['role_name']) ?>" <?= $filter_role == $role['role_name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($role['role_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary" style="height: 38px;">Apply</button>
                <?php if ($filter_name || $filter_role): ?>
                    <a href="shift-roster.php?date=<?= $current_date ?>" class="btn btn-light" style="height: 38px; display: flex; align-items: center;">Reset</a>
                <?php endif; ?>
            </form>

            <div style="display: flex; gap: 1rem; align-items: center; background: #f8fafc; padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                <span style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Legend:</span>
                <?php foreach ($shift_types as $type): ?>
                    <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; font-weight: 600; color: #334155;" title="<?= date('g:i A', strtotime($type['start_time'])) ?> - <?= date('g:i A', strtotime($type['end_time'])) ?>">
                        <span style="width: 12px; height: 12px; border-radius: 3px; background: <?= $type['color'] ?>;"></span>
                        <?= $type['short_code'] ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Roster Table -->
        <div class="table-container" style="overflow: hidden; padding: 0;">
            <div style="overflow-x: auto;">
                <table class="table" style="width: 100%; border-collapse: collapse; min-width: 1000px; margin: 0;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                            <th style="padding: 1.25rem 1rem; text-align: left; color: #64748b; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; width: 250px; position: sticky; left: 0; background: #f8fafc; z-index: 10; border-right: 1px solid #e2e8f0;">Staff Member</th>
                            <?php foreach ($days as $day): 
                                $isToday = $day['date'] == date('Y-m-d');
                            ?>
                                <th style="padding: 1rem 0.5rem; text-align: center; border-right: 1px solid #e2e8f0; <?= $isToday ? 'background: #eef2ff;' : '' ?>; width: 100px;">
                                    <div style="font-size: 0.7rem; font-weight: 700; color: <?= $isToday ? '#4f46e5' : '#64748b' ?>; text-transform: uppercase; margin-bottom: 0.25rem;"><?= $day['day_name'] ?></div>
                                    <div style="font-size: 1rem; font-weight: 800; color: <?= $isToday ? '#4f46e5' : '#1e293b' ?>;"><?= date('d M', strtotime($day['date'])) ?></div>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $emp): 
                            $initials = strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1));
                        ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 1rem; position: sticky; left: 0; background: white; z-index: 5; border-right: 1px solid #e2e8f0;">
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div style="width: 36px; height: 36px; border-radius: 50%; font-size: 0.8rem; background: #f1f5f9; color: #475569; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><?= $initials ?></div>
                                        <div>
                                            <div style="font-weight: 600; color: #1e293b; font-size: 0.9rem; line-height: 1.2;"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></div>
                                            <div style="font-size: 0.75rem; color: #64748b; font-weight: 500; margin-top: 0.1rem;"><?= htmlspecialchars($emp['job_role']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <?php foreach ($days as $day): 
                                    $date = $day['date'];
                                    $is_past = $date < date('Y-m-d');
                                    $isToday = $date == date('Y-m-d');
                                    $assigned = isset($roster[$emp['id']][$date]) ? $roster[$emp['id']][$date] : null;
                                ?>
                                    <td style="padding: 0.5rem; border-right: 1px solid #f1f5f9; vertical-align: middle; <?= $isToday ? 'background: #f8fafc;' : '' ?>">
                                        <div class="shift-cell <?= $is_past ? 'is-past' : '' ?>" 
                                             data-emp-id="<?= $emp['id'] ?>" 
                                             data-date="<?= $date ?>" 
                                             <?= !$is_past ? 'onclick="showShiftSelector(this)"' : '' ?>
                                             style="min-height: 48px; border-radius: 8px; cursor: <?= $is_past ? 'not-allowed' : 'pointer' ?>; display: flex; align-items: center; justify-content: center; transition: all 0.2s; border: 1px dashed <?= $assigned ? 'transparent' : '#cbd5e1' ?>; <?= $assigned ? "background: {$assigned['color']}15; color: {$assigned['color']}; border: 1px solid {$assigned['color']}40;" : 'background: transparent; color: #94a3b8;' ?>; position: relative;">
                                            
                                            <?php if ($assigned): ?>
                                                <div style="text-align: center; z-index: 2;">
                                                    <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase;"><?= $assigned['shift_name'] ?></div>
                                                    <div style="font-size: 0.65rem; font-weight: 600; opacity: 0.8; margin-top: 0.1rem;">
                                                        <?= date('H:i', strtotime($assigned['start_time'])) ?>-<?= date('H:i', strtotime($assigned['end_time'])) ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <i class="<?= $is_past ? 'ri-subtract-line' : 'ri-add-line' ?>" style="font-size: 1.2rem; opacity: 0.5;"></i>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Modals -->
<div id="shiftModal" style="display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.4); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; width: 100%; max-width: 400px; border-radius: 12px; padding: 1.5rem; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="margin: 0; font-size: 1.25rem; font-weight: 700; color: #1e293b;">Assign Shift</h3>
            <button onclick="closeShiftModal()" style="background: none; border: none; color: #64748b; cursor: pointer; font-size: 1.25rem;"><i class="ri-close-line"></i></button>
        </div>
        
        <div id="modal-details" style="margin-bottom: 1.5rem; padding: 1rem; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
            <!-- Content will be injected by JS -->
        </div>

        <div style="display: grid; grid-template-columns: 1fr; gap: 0.75rem;">
            <?php foreach ($shift_types as $type): ?>
                <button onclick="updateShift(<?= $type['id'] ?>)" style="padding: 1rem; border: 1px solid #e2e8f0; border-radius: 8px; background: white; cursor: pointer; text-align: left; display: flex; align-items: center; gap: 1rem; transition: border-color 0.2s;">
                    <div style="width: 12px; height: 12px; border-radius: 3px; background: <?= $type['color'] ?>;"></div>
                    <div>
                        <div style="font-weight: 600; color: #1e293b; font-size: 0.9rem;"><?= $type['name'] ?></div>
                        <div style="font-size: 0.75rem; color: #64748b;"><?= date('g:i A', strtotime($type['start_time'])) ?> - <?= date('g:i A', strtotime($type['end_time'])) ?></div>
                    </div>
                </button>
            <?php endforeach; ?>
            <button onclick="updateShift(0)" style="margin-top: 0.5rem; padding: 1rem; border: 1px dashed #ef4444; border-radius: 8px; background: #fef2f2; color: #ef4444; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                <i class="ri-delete-bin-line"></i> Remove Assignment
            </button>
        </div>
    </div>
</div>

<div id="shiftTypeModal" style="display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.4); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; width: 100%; max-width: 800px; border-radius: 12px; padding: 1.5rem; box-shadow: 0 10px 25px rgba(0,0,0,0.1); display: flex; flex-direction: column; max-height: 90vh;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 1rem;">
            <h3 style="margin: 0; font-size: 1.25rem; font-weight: 700; color: #1e293b;">Configure Shift Types</h3>
            <button onclick="closeShiftTypeModal()" style="background: none; border: none; color: #64748b; cursor: pointer; font-size: 1.25rem;"><i class="ri-close-line"></i></button>
        </div>
        
        <div style="display: flex; gap: 1.5rem; flex: 1; overflow: hidden; min-height: 450px;">
            <!-- Left Panel: Shift Type List -->
            <div style="width: 220px; border-right: 1px solid #e2e8f0; padding-right: 1rem; display: flex; flex-direction: column; gap: 0.5rem; overflow-y: auto;">
                <?php foreach ($shift_types as $type): ?>
                    <button class="shift-type-tab" id="tab-<?= $type['id'] ?>" onclick="selectShiftType(<?= htmlspecialchars(json_encode($type)) ?>)" style="padding: 0.75rem 1rem; border: 1px solid #e2e8f0; border-radius: 8px; background: white; cursor: pointer; text-align: left; transition: all 0.2s; display: flex; align-items: center; gap: 0.75rem; width: 100%;">
                        <div style="width: 10px; height: 10px; border-radius: 30%; background: <?= $type['color'] ?>;"></div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; color: #1e293b; font-size: 0.85rem;"><?= htmlspecialchars($type['name']) ?></div>
                            <div style="font-size: 0.7rem; color: #64748b;"><?= date('H:i', strtotime($type['start_time'])) ?> - <?= date('H:i', strtotime($type['end_time'])) ?></div>
                        </div>
                    </button>
                <?php endforeach; ?>
            </div>
            
            <!-- Right Panel: Shift Type Form -->
            <form id="shiftTypeForm" onsubmit="event.preventDefault(); submitShiftTypeUpdate();" style="flex: 1; overflow-y: auto; padding-right: 0.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <input type="hidden" name="action" value="update_shift_type">
                <input type="hidden" name="id" id="edit-shift-id">
                
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">Shift Name</label>
                        <input type="text" name="name" id="edit-shift-name" required class="form-control" style="height: 38px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">Short Code</label>
                        <input type="text" name="short_code" id="edit-shift-short" class="form-control" style="height: 38px;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">Start Time</label>
                        <input type="time" name="start_time" id="edit-shift-start" required class="form-control" style="height: 38px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">End Time</label>
                        <input type="time" name="end_time" id="edit-shift-end" required class="form-control" style="height: 38px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">Color (Hex)</label>
                        <div style="display: flex; gap: 0.25rem;">
                            <input type="color" id="edit-shift-color-picker" oninput="document.getElementById('edit-shift-color').value = this.value" style="width: 38px; height: 38px; border: 1px solid #e2e8f0; border-radius: 6px; padding: 2px; cursor: pointer; background: white;">
                            <input type="text" name="color" id="edit-shift-color" oninput="document.getElementById('edit-shift-color-picker').value = this.value" required class="form-control" style="height: 38px; flex: 1;">
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">Auto Clock Out (Hours)</label>
                        <input type="number" name="auto_clock_out" id="edit-shift-auto-out" class="form-control" style="height: 38px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">Half Day Mark Time</label>
                        <input type="time" name="half_day_mark_time" id="edit-shift-half-time" class="form-control" style="height: 38px;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">Early Clock In (Min)</label>
                        <input type="number" name="early_clock_in" id="edit-shift-early-in" class="form-control" style="height: 38px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">Late Mark After (Min)</label>
                        <input type="number" name="late_mark_after" id="edit-shift-late-after" class="form-control" style="height: 38px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem;">Max Checkin Count</label>
                        <input type="number" name="max_checkin" id="edit-shift-max-check" class="form-control" style="height: 38px;">
                    </div>
                </div>

                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.5rem;">Office Opens On</label>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                        <?php 
                        $weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                        foreach ($weekdays as $day): 
                        ?>
                            <label style="display: flex; align-items: center; gap: 0.35rem; font-size: 0.8rem; color: #334155; cursor: pointer;">
                                <input type="checkbox" name="office_days[]" value="<?= $day ?>" class="office-day-checkbox" style="width: 15px; height: 15px; border-radius: 4px; border: 1px solid #cbd5e1; cursor: pointer;">
                                <?= $day ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="margin-top: auto; display: flex; justify-content: flex-end; gap: 0.75rem; border-top: 1px solid #e2e8f0; padding-top: 1rem;">
                    <button type="button" onclick="closeShiftTypeModal()" class="btn btn-light">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .shift-cell:not(.is-past):hover {
        background: #f1f5f9 !important;
        border-color: #cbd5e1 !important;
    }
    .shift-cell.is-past { opacity: 0.6; }
    
    /* Ensure rows don't hover overlay the sticky column strangely */
    tbody tr:hover td:first-child {
        background: #f8fafc !important;
    }

    .shift-type-tab.active {
        border-color: #4f46e5 !important;
        background: #f5f3ff !important;
    }
    .shift-type-tab:hover:not(.active) {
        background: #f8fafc !important;
    }
</style>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let currentEmpId = null;
let currentDate = null;

function showShiftSelector(el) {
    currentEmpId = el.dataset.empId;
    currentDate = el.dataset.date;
    
    const empName = el.closest('tr').querySelector('td div div div').innerText;
    const formattedDate = new Date(currentDate).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'short', day: 'numeric' });
    
    document.getElementById('modal-details').innerHTML = `
        <div style="font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 0.25rem;">Scheduling For</div>
        <div style="font-weight: 700; color: #1e293b; font-size: 1.1rem; margin-bottom: 0.1rem;">${empName}</div>
        <div style="font-weight: 600; color: #4f46e5; font-size: 0.85rem;">${formattedDate}</div>
    `;
    
    document.getElementById('shiftModal').style.display = 'flex';
}

function closeShiftModal() { document.getElementById('shiftModal').style.display = 'none'; }

function showShiftTypeManager() {
    document.getElementById('shiftTypeModal').style.display = 'flex';
    // Select the first tab automatically
    const firstTab = document.querySelector('.shift-type-tab');
    if (firstTab) {
        firstTab.click();
    }
}

function closeShiftTypeModal() {
    document.getElementById('shiftTypeModal').style.display = 'none';
}

function selectShiftType(type) {
    // Set active tab
    document.querySelectorAll('.shift-type-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    const selectedTab = document.getElementById('tab-' + type.id);
    if (selectedTab) selectedTab.classList.add('active');

    // Populate inputs
    document.getElementById('edit-shift-id').value = type.id;
    document.getElementById('edit-shift-name').value = type.name;
    document.getElementById('edit-shift-short').value = type.short_code || '';
    document.getElementById('edit-shift-start').value = type.start_time;
    document.getElementById('edit-shift-end').value = type.end_time;
    document.getElementById('edit-shift-color').value = type.color || '#3b82f6';
    document.getElementById('edit-shift-color-picker').value = type.color || '#3b82f6';
    document.getElementById('edit-shift-auto-out').value = type.auto_clock_out ?? 1;
    document.getElementById('edit-shift-half-time').value = type.half_day_mark_time || '13:00:00';
    document.getElementById('edit-shift-early-in').value = type.early_clock_in ?? 0;
    document.getElementById('edit-shift-late-after').value = type.late_mark_after ?? 15;
    document.getElementById('edit-shift-max-check').value = type.max_checkin ?? 1;

    // Checkboxes
    const openDays = (type.office_opens_on || '').split(',');
    document.querySelectorAll('.office-day-checkbox').forEach(cb => {
        cb.checked = openDays.includes(cb.value);
    });
}

function submitShiftTypeUpdate() {
    const form = document.getElementById('shiftTypeForm');
    const formData = new FormData(form);

    const loader = Swal.fire({
        title: 'Updating Shift Settings...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    fetch('shift-roster.php', {
        method: 'POST',
        body: new URLSearchParams(formData)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            Swal.fire({ icon: 'success', title: 'Shift Updated', showConfirmButton: false, timer: 1000 }).then(() => location.reload());
        } else {
            Swal.fire('Error', data.error || 'Update failed', 'error');
        }
    });
}

function showRequestsModal() { Swal.fire('Coming Soon', 'Shift requests modal modernization is in progress.', 'info'); }

function updateShift(shiftTypeId) {
    const loader = Swal.fire({
        title: 'Updating Schedule...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    fetch('shift-roster.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=update_shift&employee_id=${currentEmpId}&date=${currentDate}&shift_type_id=${shiftTypeId}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            Swal.fire({ icon: 'success', title: 'Schedule Updated', showConfirmButton: false, timer: 1000 }).then(() => location.reload());
        } else {
            Swal.fire('Error', data.error || 'Update failed', 'error');
        }
    });
}

window.onclick = function(event) {
    if (event.target == document.getElementById('shiftModal')) closeShiftModal();
    if (event.target == document.getElementById('shiftTypeModal')) closeShiftTypeModal();
}
</script>

<?php include 'includes/footer.php'; ?>
