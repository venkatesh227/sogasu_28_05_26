<?php
session_start();
include '../includes/db.php';

$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';

// Days in month
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);

// Fetch Job Roles
$roles = $pdo->query("SELECT DISTINCT role_name FROM job_roles WHERE is_deleted = 0 ORDER BY role_name ASC")->fetchAll();

// Fetch Employees
$query = "SELECT id, first_name, last_name, job_role 
          FROM employees 
          WHERE is_deleted = 0
          AND employee_type = 'inhouse'";
$params = [];
if (!empty($role_filter)) {
    $query .= " AND job_role = ?";
    $params[] = $role_filter;
}
$query .= " ORDER BY first_name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll();

// ================= AUTO ABSENT LOGIC START =================
$today = date('Y-m-d');
$currentTime = date('H:i:s');

foreach ($employees as $emp) {
    $employeeId = $emp['id'];

    // Get today's shift for employee
    $shiftStmt = $pdo->prepare("
        SELECT st.start_time, st.end_time, st.max_checkin
        FROM shift_roster sr
        JOIN shift_types st ON sr.shift_type_id = st.id
        WHERE sr.employee_id = ?
        AND sr.roster_date = ?
        LIMIT 1
    ");
    $shiftStmt->execute([$employeeId, $today]);
    $shift = $shiftStmt->fetch(PDO::FETCH_ASSOC);

    if (!$shift) {
        continue; // no shift assigned
    }

    // Calculate absent cutoff time
    $cutoffTime = date(
        'H:i:s',
        strtotime($shift['start_time'] . " +{$shift['max_checkin']} hours")
    );

    // If current time crossed cutoff
    if ($currentTime > $shift['end_time']) {

        // Check if attendance already exists
        $attendanceCheck = $pdo->prepare("
            SELECT id
            FROM attendance
            WHERE employee_id = ?
            AND attendance_date = ?
            LIMIT 1
        ");
        $attendanceCheck->execute([$employeeId, $today]);

        if (!$attendanceCheck->fetch()) {

            // Insert absent record
            $insertAbsent = $pdo->prepare("
                INSERT INTO attendance (
                    employee_id,
                    attendance_date,
                    status
                ) VALUES (?, ?, 'Absent')
            ");

            $insertAbsent->execute([$employeeId, $today]);
        }
    }
}
// Fetch Monthly Attendance Data
$att_stmt = $pdo->prepare("SELECT employee_id, DAY(attendance_date) as day, status FROM attendance WHERE MONTH(attendance_date) = ? AND YEAR(attendance_date) = ?");
$att_stmt->execute([$month, $year]);
$att_data = $att_stmt->fetchAll();

// AJAX: Mark Attendance
if (isset($_POST['action']) && $_POST['action'] === 'bulk_mark_attendance') {
    header('Content-Type: application/json');
    try {
        $employee_ids = explode(',', $_POST['employee_ids']);
        $mark_by = $_POST['mark_by'];
        $year = $_POST['year'];
        $month = $_POST['month'];
        $attendance_date = $_POST['attendance_date'];
        
        $check_in = $_POST['check_in'];
        $check_out = $_POST['check_out'];
        $in_loc = $_POST['check_in_location'];
        $out_loc = $_POST['check_out_location'];
        $work_from = $_POST['working_from'];
        $is_late = $_POST['is_late'] === 'Yes' ? 1 : 0;
        $is_half_day = $_POST['is_half_day'] === 'Yes' ? 1 : 0;
        $status = $_POST['status'] ?? 'Present';
        $overwrite = isset($_POST['overwrite']) ? true : false;

        $dates = [];
        if ($mark_by === 'Month') {
            $days = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            for ($d = 1; $d <= $days; $d++) {
                $date_str = sprintf('%04d-%02d-%02d', $year, $month, $d);
                if (date('N', strtotime($date_str)) != 7) { // Skip Sundays by default for bulk month
                    $dates[] = $date_str;
                }
            }
        } else {
            $dates[] = $attendance_date;
        }

        $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, attendance_date, status, check_in, check_out, check_in_location, check_out_location, working_from, is_late, is_half_day) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
                             ON DUPLICATE KEY UPDATE 
                                status = IF(?, ?, status),
                                check_in = IF(?, VALUES(check_in), check_in),
                                check_out = IF(?, VALUES(check_out), check_out),
                                check_in_location = IF(?, VALUES(check_in_location), check_in_location),
                                check_out_location = IF(?, VALUES(check_out_location), check_out_location),
                                working_from = IF(?, VALUES(working_from), working_from),
                                is_late = IF(?, VALUES(is_late), is_late),
                                is_half_day = IF(?, VALUES(is_half_day), is_half_day)");

        foreach ($employee_ids as $emp_id) {
            foreach ($dates as $date) {
                $stmt->execute([
                    $emp_id, $date, $status, $check_in, $check_out, $in_loc, $out_loc, $work_from, $is_late, $is_half_day,
                    $overwrite, $status, $overwrite, $overwrite, $overwrite, $overwrite, $overwrite, $overwrite, $overwrite
                ]);
            }
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

$attendance = [];
foreach ($att_data as $row) {
    $attendance[$row['employee_id']][$row['day']] = $row['status'];
}

$pageTitle = "Attendance Sheet - Sogasu";
$activePage = "attendance";
include 'includes/header.php';
?>

<style>
    .att-icon { font-size: 0.9rem; }
    .status-Present { color: #059669; } /* Green Check */
    .status-Absent { color: #ef4444; }  /* Red X */
    .status-Late { color: #d97706; }    /* Yellow ! */
    .status-HalfDay { color: #f59e0b; } /* Orange Star */
    .status-OnLeave { color: #7c3aed; } /* Purple Plane */
    .status-Holiday { color: #3b82f6; } /* Blue Star */
    
    .grid-cell {
        width: 30px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-right: 1px solid #f1f5f9;
        font-size: 0.75rem;
    }

    .emp-row-link:hover .emp-row-avatar { transform: scale(1.08); background: #4f46e5 !important; color: white !important; }
    .emp-row-link:hover i { opacity: 1 !important; }
</style>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <!-- Standard Header Area -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem; width: 100%; max-width: 100%; overflow: hidden;">
        
        <!-- Standard Header -->
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Attendance Analytics</h2>
                <p style="color: #64748b; margin-top: 0.25rem;">Monitor staff presence, late arrivals, and leave patterns in real-time.</p>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <button onclick="showMarkAttendanceModal()" class="btn btn-primary" style="background: #4f46e5; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; color: white; display: inline-flex; align-items: center; gap: 0.5rem;">
                    <i class="ri-add-line"></i> Mark Attendance
                </button>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="table-container" style="padding: 1.25rem; margin-top: 0; display: flex; gap: 2rem; align-items: center; flex-wrap: wrap;">
            <div class="filter-item">
                <span class="label">Department</span>
                <select onchange="window.location.href='?month=<?= $month ?>&year=<?= $year ?>&role='+this.value">
                    <option value="">All Staff</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= htmlspecialchars($role['role_name']) ?>" <?= $role_filter == $role['role_name'] ? 'selected' : '' ?>><?= htmlspecialchars($role['role_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-item">
                <span class="label">Timeline</span>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <select onchange="window.location.href='?month='+this.value+'&year=<?= $year ?>&role=<?= $role_filter ?>'">
                        <?php for($m=1; $m<=12; $m++): ?>
                            <option value="<?= $m ?>" <?= $month == $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                        <?php endfor; ?>
                    </select>
                    <select onchange="window.location.href='?month=<?= $month ?>&year='+this.value+'&role=<?= $role_filter ?>'">
                        <?php for($y=2024; $y<=2030; $y++): ?>
                            <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            
            <div style="flex: 1;"></div>

            <!-- Legend -->
            <div class="attendance-legend">
                <div class="legend-item"><i class="ri-checkbox-circle-fill" style="color: var(--success);"></i> <span>Present</span></div>
                <div class="legend-item"><i class="ri-close-circle-fill" style="color: var(--danger);"></i> <span>Absent</span></div>
                <div class="legend-item"><i class="ri-error-warning-fill" style="color: var(--warning);"></i> <span>Late</span></div>
                <div class="legend-item"><i class="ri-plane-fill" style="color: #7c3aed;"></i> <span>Leave</span></div>
            </div>
        </div>

        <style>
            .filter-item { display: flex; flex-direction: column; gap: 0.25rem; }
            .filter-item .label { font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }
            .filter-item select { border: none; background: transparent; font-weight: 700; color: var(--text-dark); outline: none; cursor: pointer; font-size: 1rem; }
            
            .attendance-legend { display: flex; gap: 1rem; background: #f8fafc; padding: 0.5rem 1rem; border-radius: 12px; }
            .legend-item { display: flex; align-items: center; gap: 0.25rem; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); }
            
            .att-grid-table {
                width: max-content !important;
                min-width: 100% !important;
                table-layout: fixed !important;
            }
            .att-grid-table th, .att-grid-table td {
                padding: 6px 4px !important;
                font-size: 0.75rem !important;
                text-align: center !important;
                height: 48px;
                vertical-align: middle;
            }
            .att-grid-table th:not(.sticky-col):not(.score-col), .att-grid-table td:not(.sticky-col):not(.score-col) {
                width: 38px !important;
                min-width: 38px !important;
                max-width: 38px !important;
            }
            .att-icon { font-size: 1rem; }
            
            .sticky-col {
                position: sticky;
                left: 0;
                background: white !important;
                z-index: 5;
                border-right: 2px solid #e2e8f0 !important;
                width: 200px !important;
                min-width: 200px !important;
                max-width: 200px !important;
                text-align: left !important;
            }
            .sticky-header { position: sticky; top: 0; z-index: 10; }
            .score-col {
                width: 70px !important;
                min-width: 70px !important;
                max-width: 70px !important;
            }
        </style>

        <!-- Attendance Grid Container -->
        <div class="table-container" style="padding: 1.5rem;">
            <div style="overflow-x: auto; max-height: 600px;">
                <table class="table att-grid-table" style="border-collapse: collapse;">
                    <thead class="sticky-header">
                        <tr>
                            <th class="sticky-col">Employee</th>
                            <?php for($d=1; $d<=$days_in_month; $d++): 
                                $date_obj = mktime(0, 0, 0, $month, $d, $year);
                                $day_name = date('D', $date_obj);
                                $is_weekend = ($day_name == 'Sun');
                            ?>
                                <th style="background: <?= $is_weekend ? '#fff1f2' : '#f8fafc' ?>;">
                                    <div style="font-size: 0.8rem; font-weight: 700;"><?= $d ?></div>
                                    <div style="font-size: 0.65rem; opacity: 0.6;"><?= $day_name ?></div>
                                </th>
                            <?php endfor; ?>
                            <th class="score-col" style="background: #eef2ff;">Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $emp): 
                            $present_count = 0;
                        ?>
                            <tr>
                                <td class="sticky-col" style="text-align: left !important; padding: 0 !important;">
                                    <a href="employee-attendance.php?id=<?= $emp['id'] ?>&month=<?= $month ?>&year=<?= $year ?>" class="emp-row-link" style="text-decoration: none; display: flex; align-items: center; gap: 0.75rem; padding: 6px 15px; color: inherit; width: 100%; height: 100%;" title="View Individual Calendar">
                                        <div class="emp-row-avatar" style="width: 36px; height: 36px; border-radius: 50%; font-size: 0.8rem; background: #e0e7ff; color: #4f46e5; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: all 0.2s;">
                                            <?= strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 0.25rem;">
                                                <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                                                <i class="ri-calendar-2-line" style="font-size: 0.85rem; color: #4f46e5; opacity: 0; transition: opacity 0.2s;" class="emp-cal-icon"></i>
                                            </div>
                                            <div style="font-size: 0.7rem; color: #64748b;"><?= htmlspecialchars($emp['job_role']) ?></div>
                                        </div>
                                    </a>
                                </td>
                                <?php for($d=1; $d<=$days_in_month; $d++): 
                                    $status = $attendance[$emp['id']][$d] ?? null;
                                    if ($status == 'Present') $present_count++;
                                    $is_sun = date('N', mktime(0,0,0,$month,$d,$year)) == 7;
                                ?>
                                    <td>
                                        <?php if ($status): ?>
                                            <?php if ($status == 'Present'): ?><i class="ri-checkbox-circle-fill att-icon" style="color: var(--success);"></i>
                                            <?php elseif ($status == 'Absent'): ?><i class="ri-close-circle-fill att-icon" style="color: var(--danger);"></i>
                                            <?php elseif ($status == 'Late'): ?><i class="ri-error-warning-fill att-icon" style="color: var(--warning);"></i>
                                            <?php elseif ($status == 'Half Day'): ?><i class="ri-star-half-fill att-icon" style="color: #f59e0b;"></i>
                                            <?php elseif ($status == 'On Leave'): ?><i class="ri-plane-fill att-icon" style="color: #7c3aed;"></i>
                                            <?php endif; ?>
                                        <?php elseif ($is_sun): ?>
                                            <i class="ri-calendar-event-line att-icon" style="color: var(--danger); opacity: 0.2;"></i>
                                        <?php else: ?>
                                            <span style="color: #e2e8f0;">-</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endfor; ?>
                                <td class="score-col" style="background: #f8fafc; font-weight: 800; color: var(--primary);">
                                    <?= $present_count ?> / <?= $days_in_month ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Mark Attendance Modal -->
<div id="markAttendanceModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 1001; align-items: center; justify-content: center; backdrop-filter: blur(8px);">
    <div class="table-container animate-fade-in" style="width: 850px; padding: 0 !important; background: white !important; border: 1px solid #e2e8f0 !important; border-radius: 12px !important; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1) !important; overflow: hidden;">
        <div style="padding: 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.5);">
            <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text-dark);">Mark Staff Attendance</h3>
            <button onclick="closeMarkAttendanceModal()" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.5rem;"><i class="ri-close-line"></i></button>
        </div>

        <div style="padding: 2.5rem; max-height: 85vh; overflow-y: auto;">
            <form id="markAttendanceForm" onsubmit="event.preventDefault(); submitMarkAttendance()">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                    <div class="form-group-p">
                        <label>Department / Role</label>
                        <select onchange="filterEmployeesByRole(this.value)" class="premium-input">
                            <option value="">All Departments</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= htmlspecialchars($role['role_name']) ?>"><?= htmlspecialchars($role['role_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group-p">
                        <label>Employee(s)</label>
                        <select name="employee_id" id="employee-dropdown" class="premium-input">
                            <option value="all">All Visible Employees</option>
                            <?php foreach ($employees as $e): ?>
                                <option class="emp-option" value="<?= $e['id'] ?>" data-role="<?= htmlspecialchars($e['job_role']) ?>">
                                    <?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1.5fr 1fr; gap: 2rem; margin-bottom: 2rem; align-items: center;">
                    <div class="form-group-p">
                        <label>Range Type</label>
                        <div class="radio-group-p">
                            <label><input type="radio" name="mark_by" value="Month" checked onchange="toggleMarkBy(this.value)"> <span>Month</span></label>
                            <label><input type="radio" name="mark_by" value="Date" onchange="toggleMarkBy(this.value)"> <span>Date</span></label>
                        </div>
                    </div>
                    <div id="mark-by-month-group" style="display: flex; gap: 1rem;">
                        <div style="flex: 1;">
                            <label class="label-p">Year</label>
                            <select name="year" class="premium-input">
                                <?php for($y=2024; $y<=2030; $y++): ?>
                                    <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div style="flex: 1.5;">
                            <label class="label-p">Month</label>
                            <select name="month" class="premium-input">
                                <?php for($m=1; $m<=12; $m++): ?>
                                    <option value="<?= $m ?>" <?= $month == $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div id="mark-by-date-group" style="display: none; flex: 1;">
                        <label class="label-p">Select Date</label>
                        <input type="date" name="attendance_date" value="<?= date('Y-m-d') ?>" class="premium-input">
                    </div>
                    <div class="form-group-p">
                        <label>Status</label>
                        <select name="status" class="premium-input" style="color: var(--primary); font-weight: 800;">
                            <option value="Present">Present</option>
                            <option value="Late">Late Arrival</option>
                            <option value="Half Day">Half Day</option>
                            <option value="Absent">Absent</option>
                            <option value="On Leave">On Leave</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; margin-bottom: 2.5rem; background: #f8fafc; padding: 1.5rem; border-radius: 16px;">
                    <div class="form-group-p">
                        <label>Shift Timing</label>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="time" name="check_in" value="09:00" class="premium-input" style="padding: 0.5rem;">
                            <span>to</span>
                            <input type="time" name="check_out" value="18:00" class="premium-input" style="padding: 0.5rem;">
                        </div>
                    </div>
                    <div class="form-group-p">
                        <label>Work Mode</label>
                        <select name="working_from" class="premium-input">
                            <option value="Office">At Office</option>
                            <option value="Home">Remote / Home</option>
                            <option value="On-Site">On-Site Client</option>
                        </select>
                    </div>
                    <div class="form-group-p" style="display: flex; align-items: center; justify-content: center; padding-top: 1.5rem;">
                        <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer; font-weight: 700;">
                            <input type="checkbox" name="overwrite" value="1" style="width: 20px; height: 20px; accent-color: var(--primary);">
                            <span>Overwrite Existing</span>
                        </label>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 1rem; border-top: 1px solid #f1f5f9; padding-top: 2rem;">
                    <button type="button" onclick="closeMarkAttendanceModal()" class="btn btn-light" style="padding: 10px 20px; font-weight: 600; border-radius: 8px; background: white; border: 1px solid #e2e8f0; color: #475569;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: #4f46e5; border: none; padding: 10px 30px; border-radius: 8px; font-weight: 600; color: white;">Submit Attendance</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .form-group-p { display: flex; flex-direction: column; gap: 0.5rem; }
    .form-group-p label, .label-p { font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
    .premium-input { width: 100%; padding: 0.8rem 1rem; border: 1px solid #e2e8f0; border-radius: 12px; outline: none; transition: all 0.2s; background: white; font-size: 0.95rem; font-weight: 600; }
    .premium-input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); }
    
    .radio-group-p { display: flex; gap: 1.5rem; padding: 0.5rem 0; }
    .radio-group-p label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; text-transform: none; color: var(--text-dark); font-size: 0.9rem; }
    .radio-group-p input { width: 18px; height: 18px; accent-color: var(--primary); }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function showMarkAttendanceModal() { document.getElementById('markAttendanceModal').style.display = 'flex'; }
function closeMarkAttendanceModal() { document.getElementById('markAttendanceModal').style.display = 'none'; }

function filterEmployeesByRole(role) {
    const dropdown = document.getElementById('employee-dropdown');
    const options = dropdown.querySelectorAll('.emp-option');
    dropdown.value = 'all';
    options.forEach(opt => opt.style.display = (!role || opt.dataset.role === role) ? 'block' : 'none');
}

function toggleMarkBy(val) {
    document.getElementById('mark-by-month-group').style.display = (val === 'Month' ? 'flex' : 'none');
    document.getElementById('mark-by-date-group').style.display = (val === 'Date' ? 'block' : 'none');
}

function submitMarkAttendance() {
    const form = document.getElementById('markAttendanceForm');
    const formData = new FormData(form);
    const employee_id = document.getElementById('employee-dropdown').value;
    const params = new URLSearchParams(formData);
    params.set('action', 'bulk_mark_attendance');
    
    if (employee_id === 'all') {
        const visibleIds = Array.from(document.querySelectorAll('.emp-option'))
            .filter(opt => opt.style.display !== 'none')
            .map(opt => opt.value);
        params.set('employee_ids', visibleIds.join(','));
    } else {
        params.set('employee_ids', employee_id);
    }

    fetch('attendance.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            Swal.fire({ icon: 'success', title: 'Attendance Marked', text: 'Entries processed successfully', timer: 1500, showConfirmButton: false })
            .then(() => location.reload());
        } else {
            Swal.fire('Error', data.error || 'Submission failed', 'error');
        }
    });
}

window.onclick = e => { if (e.target == document.getElementById('markAttendanceModal')) closeMarkAttendanceModal(); }
</script>

<?php include 'includes/footer.php'; ?>
