<?php
session_start();
include '../includes/db.php';

// Authentication and Authorization check
$activePage = "attendance";
$pageTitle = "Employee Attendance Calendar - Sogasu";

// Fetch Timeline
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);

// Fetch Active Employees for switcher
$all_employees = $pdo->query("SELECT id, first_name, last_name, job_role FROM employees WHERE is_deleted = 0 ORDER BY first_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Get Selected Employee
$employee_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$employee_id && !empty($all_employees)) {
    $employee_id = $all_employees[0]['id'];
}

if (!$employee_id) {
    die("No active employees found in the system.");
}

// Fetch Selected Employee Info
$stmt = $pdo->prepare("SELECT e.*, j.role_name FROM employees e LEFT JOIN job_roles j ON e.job_role = j.id WHERE e.id = ? AND e.is_deleted = 0");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$employee) {
    die("Employee not found or is deleted.");
}
// Handle AJAX actions
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    if ($_POST['action'] === 'mark_all_present') {
        try {
            $dates = [];
            for ($d = 1; $d <= $days_in_month; $d++) {
                $date_str = sprintf('%04d-%02d-%02d', $year, $month, $d);
                if (date('N', strtotime($date_str)) != 7) { // Skip Sundays
                    $dates[] = $date_str;
                }
            }

            $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, attendance_date, status, check_in, check_out, working_from, is_late, is_half_day) 
                                 VALUES (?, ?, 'Present', '09:00:00', '18:00:00', 'Office', 0, 0) 
                                 ON DUPLICATE KEY UPDATE 
                                    status = 'Present',
                                    check_in = '09:00:00',
                                    check_out = '18:00:00',
                                    working_from = 'Office',
                                    is_late = 0,
                                    is_half_day = 0");

            foreach ($dates as $date) {
                $stmt->execute([$employee_id, $date]);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    } elseif ($_POST['action'] === 'get_day_details') {
        try {
            $date = $_POST['date'];
            
            // Fetch attendance
            $stmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
            $stmt->execute([$employee_id, $date]);
            $att = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Fetch logs
            $stmt = $pdo->prepare("SELECT * FROM attendance_logs WHERE employee_id = ? AND log_date = ? ORDER BY log_time ASC");
            $stmt->execute([$employee_id, $date]);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format log times
            foreach ($logs as &$log) {
                $log['formatted_time'] = date('h:i A', strtotime($log['log_time']));
            }
            
            // Fetch shift info
            $shiftStmt = $pdo->prepare("
                SELECT st.name 
                FROM shift_roster sr
                JOIN shift_types st ON sr.shift_type_id = st.id
                WHERE sr.employee_id = ? AND sr.roster_date = ?
            ");
            $shiftStmt->execute([$employee_id, $date]);
            $shift_name = $shiftStmt->fetchColumn() ?: 'Morning shift';
            
            echo json_encode([
                'success' => true,
                'attendance' => $att,
                'logs' => $logs,
                'shift_name' => $shift_name
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    } elseif ($_POST['action'] === 'save_day_status') {
        try {
            $date = $_POST['date'];
            $status = $_POST['status'];
            
            $is_half_day = 0;
            $is_late = 0;
            $db_status = 'Present';

            if ($status === 'ABSENT') {
                $db_status = 'Absent';
            } elseif ($status === 'HALF DAY') {
                $db_status = 'Half Day';
                $is_half_day = 1;
            } elseif ($status === 'PRESENT') {
                $db_status = 'Present';
            } elseif ($status === 'WEEK OFF') {
                $db_status = 'Absent'; // represented as Absent in DB enum
            } elseif ($status === 'HOLIDAY') {
                $db_status = 'Absent';
            } elseif ($status === 'PAID LEAVE' || $status === 'UNPAID LEAVE' || $status === 'HALF DAY LEAVE') {
                $db_status = 'On Leave';
                if ($status === 'HALF DAY LEAVE') $is_half_day = 1;
            }

            // Write to database
            $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, attendance_date, status, is_half_day, is_late, check_in, check_out, working_from) 
                                 VALUES (?, ?, ?, ?, ?, '09:00:00', '18:00:00', 'Office') 
                                 ON DUPLICATE KEY UPDATE status = ?, is_half_day = ?, is_late = ?");
            $stmt->execute([$employee_id, $date, $db_status, $is_half_day, $is_late, $db_status, $is_half_day, $is_late]);
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    } elseif ($_POST['action'] === 'add_day_punch') {
        try {
            $date = $_POST['date'];
            $time = $_POST['time'] . ':00';
            $type = $_POST['type']; // In or Out
            
            // Insert log
            $stmt = $pdo->prepare("INSERT INTO attendance_logs (employee_id, log_date, log_time, log_type) VALUES (?, ?, ?, ?)");
            $stmt->execute([$employee_id, $date, $time, $type]);
            
            // Update attendance row check_in / check_out times
            if ($type === 'In') {
                $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, attendance_date, status, check_in, working_from) 
                                     VALUES (?, ?, 'Present', ?, 'Office') 
                                     ON DUPLICATE KEY UPDATE status = 'Present', check_in = ?");
                $stmt->execute([$employee_id, $date, $time, $time]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, attendance_date, status, check_out, working_from) 
                                     VALUES (?, ?, 'Present', ?, 'Office') 
                                     ON DUPLICATE KEY UPDATE status = 'Present', check_out = ?");
                $stmt->execute([$employee_id, $date, $time, $time]);
            }
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    } elseif ($_POST['action'] === 'save_day_note') {
        try {
            $date = $_POST['date'];
            $notes = $_POST['notes'];
            
            $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, attendance_date, status, notes) 
                                 VALUES (?, ?, 'Present', ?) 
                                 ON DUPLICATE KEY UPDATE notes = ?");
            $stmt->execute([$employee_id, $date, $notes, $notes]);
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    } elseif ($_POST['action'] === 'mark_all_absent_as_present') {
        try {
            for ($d = 1; $d <= $days_in_month; $d++) {
                $date_str = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $day_of_week = date('N', strtotime($date_str));
                
                if ($day_of_week == 7) continue; // Skip Sundays
                
                $stmt = $pdo->prepare("SELECT status FROM attendance WHERE employee_id = ? AND attendance_date = ?");
                $stmt->execute([$employee_id, $date_str]);
                $status = $stmt->fetchColumn();
                
                if (!$status || $status === 'Absent') {
                    $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, attendance_date, status, check_in, check_out, working_from) 
                                         VALUES (?, ?, 'Present', '09:00:00', '18:00:00', 'Office') 
                                         ON DUPLICATE KEY UPDATE status = 'Present', check_in = '09:00:00', check_out = '18:00:00'");
                    $stmt->execute([$employee_id, $date_str]);
                }
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

// Fetch Monthly Attendance Data for this Employee
$att_stmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND MONTH(attendance_date) = ? AND YEAR(attendance_date) = ?");
$att_stmt->execute([$employee_id, $month, $year]);
$att_records = $att_stmt->fetchAll(PDO::FETCH_ASSOC);

// Key attendance by day
$attendance = [];
foreach ($att_records as $record) {
    $day = (int)date('j', strtotime($record['attendance_date']));
    $attendance[$day] = $record;
}

// Calculate Summary Metrics
$present_count = 0;
$absent_count = 0;
$half_day_count = 0;
$paid_leave_count = 0.0;
$week_off_count = 0;

for ($d = 1; $d <= $days_in_month; $d++) {
    $date_str = sprintf('%04d-%02d-%02d', $year, $month, $d);
    $day_of_week = date('N', strtotime($date_str));
    
    // Sundays are designated week-offs
    if ($day_of_week == 7) {
        $week_off_count++;
    }
    
    if (isset($attendance[$d])) {
        $record = $attendance[$d];
        $status = $record['status'];
        
        if ($status === 'Present' || $status === 'Late') {
            $present_count++;
        } elseif ($status === 'Absent') {
            $absent_count++;
        } elseif ($status === 'Half Day') {
            $half_day_count++;
        } elseif ($status === 'On Leave') {
            $paid_leave_count += 1.0;
        }
    }
}

// Find first day weekday index (0 = Sun, 6 = Sat)
$first_day_timestamp = mktime(0, 0, 0, $month, 1, $year);
$first_day_of_week = date('w', $first_day_timestamp);

include 'includes/header.php';
?>

<style>
    :root {
        --present-color: #00e676; /* Bright neon green exactly like image present days */
        --absent-color: #ff1744;  /* Bright neon red exactly like image absent days */
        --halfday-color: #ff9100; /* Rich orange/yellow exactly like image late/half days */
        --leave-color: #ec4899;   /* Vibrant pink */
        --weekoff-color: #64748b;
        --unmarked-color: #e2e8f0;
    }

    /* Print styling optimized for A4 download */
    @media print {
        body * {
            visibility: hidden;
        }
        .main-content, .main-content * {
            visibility: visible;
        }
        .main-content {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            padding: 0;
            margin: 0;
        }
        .sidebar, #sidebar, .sidebar-overlay, .topbar, .btn-icon, .no-print, #emp-switcher, .btn-secondary {
            display: none !important;
        }
        .glass-card, .table-container {
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
        }
    }

    /* Beautiful Cream/Gold Header matching the image */
    .cream-header {
        background-color: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 16px;
        padding: 1.25rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: var(--shadow-sm);
    }
    .cream-header-title {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 1.25rem;
        font-weight: 800;
        color: #b45309;
    }
    .timeline-pill {
        background: white;
        border: 1px solid #fde68a;
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 700;
        color: #78350f;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    .timeline-pill:hover {
        border-color: #f59e0b;
        background-color: #fff9e6;
    }

    /* Actions styling */
    .action-list-btn {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        width: 100%;
        text-align: left;
        padding: 0.75rem 1rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        color: #475569;
        font-weight: 700;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    .action-list-btn:hover {
        background: white;
        border-color: var(--primary-light);
        color: var(--primary);
        transform: translateY(-1px);
        box-shadow: var(--shadow-sm);
    }
    .action-list-btn i {
        font-size: 1.2rem;
    }

    /* Calendar Grid styling */
    .calendar-container {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1.25rem;
        box-shadow: var(--shadow-sm);
        width: 100%;
    }
    .weekday-header {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        text-align: center;
        font-weight: 700;
        color: #475569;
        font-size: 0.8rem;
        margin-bottom: 0.75rem;
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 0.5rem;
    }
    .calendar-days-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 0.5rem;
    }
    
    /* Calendar Block Styles */
    .day-block {
        height: 40px;
        border-radius: 8px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        position: relative;
        font-size: 0.9rem;
        font-weight: 800;
        color: white;
        box-shadow: 0 2px 4px rgba(0,0,0,0.03);
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
    }
    .day-block:hover {
        transform: scale(1.06);
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        z-index: 10;
    }
    
    /* Day Block Colors */
    .day-present { background-color: var(--present-color) !important; color: white !important; }
    .day-absent { background-color: var(--absent-color) !important; color: white !important; }
    .day-halfday { background-color: var(--halfday-color) !important; color: white !important; }
    .day-leave { background-color: var(--leave-color) !important; color: white !important; }
    .day-weekoff { background-color: #f1f5f9 !important; color: #64748b !important; }
    .day-unmarked { background-color: #f1f5f9 !important; color: #94a3b8 !important; }
    .day-empty { background: transparent !important; box-shadow: none !important; cursor: default !important; }
    .day-empty:hover { transform: none !important; box-shadow: none !important; }

    /* Late Label Subscript styling */
    .late-badge {
        position: absolute;
        bottom: 3px;
        left: 50%;
        transform: translateX(-50%);
        font-size: 0.5rem;
        font-weight: 800;
        background: rgba(255, 255, 255, 0.25);
        color: white;
        padding: 1px 3px;
        border-radius: 4px;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        border: 1px solid rgba(255,255,255,0.4);
    }
    .day-weekoff .late-badge, .day-unmarked .late-badge {
        background: rgba(0, 0, 0, 0.05);
        color: #475569;
        border: 1px solid rgba(0,0,0,0.1);
    }

    /* Tooltip container */
    .tooltip-content {
        display: none;
        position: absolute;
        bottom: 110%;
        left: 50%;
        transform: translateX(-50%);
        background: #1e293b;
        color: white;
        padding: 0.75rem 1rem;
        border-radius: 8px;
        font-size: 0.75rem;
        width: 180px;
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3);
        z-index: 100;
        pointer-events: none;
        line-height: 1.4;
        text-align: left;
        font-weight: 500;
    }
    .tooltip-content::after {
        content: '';
        position: absolute;
        top: 100%;
        left: 50%;
        transform: translateX(-50%);
        border-width: 6px;
        border-style: solid;
        border-color: #1e293b transparent transparent transparent;
    }
    .day-block:hover .tooltip-content {
        display: block;
    }

    /* Modal Pill Buttons Styling */
    .pill-btn {
        background: white;
        border: 1.5px solid #cbd5e1;
        padding: 6px 14px;
        border-radius: 9999px;
        font-size: 0.72rem;
        font-weight: 800;
        cursor: pointer;
        transition: all 0.2s;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        outline: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 90px;
    }
    .pill-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .pill-absent { border-color: #ff1744; color: #ff1744; background: white; }
    .pill-absent.active { background-color: #ff1744 !important; color: white !important; border-color: #ff1744 !important; }
    
    .pill-halfday { border-color: #ff9100; color: #ff9100; background: white; }
    .pill-halfday.active { background-color: #ff9100 !important; color: white !important; border-color: #ff9100 !important; }
    
    .pill-present { border-color: #00e676; color: #00e676; background: white; }
    .pill-present.active { background-color: #00e676 !important; color: white !important; border-color: #00e676 !important; }
    
    .pill-weekoff { border-color: #64748b; color: #64748b; background: white; }
    .pill-weekoff.active { background-color: #64748b !important; color: white !important; border-color: #64748b !important; }
    
    .pill-holiday { border-color: #475569; color: #475569; background: white; }
    .pill-holiday.active { background-color: #475569 !important; color: white !important; border-color: #475569 !important; }
    
    .pill-leave-paid { border-color: #ec4899; color: #ec4899; background: white; }
    .pill-leave-paid.active { background-color: #ec4899 !important; color: white !important; border-color: #ec4899 !important; }
    
    .pill-leave-half { border-color: #ec4899; color: #ec4899; background: white; }
    .pill-leave-half.active { background-color: #ec4899 !important; color: white !important; border-color: #ec4899 !important; }
    
    .pill-leave-unpaid { border-color: #0ea5e9; color: #0ea5e9; background: white; }
    .pill-leave-unpaid.active { background-color: #0ea5e9 !important; color: white !important; border-color: #0ea5e9 !important; }

    .btn-icon-p {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: #f8fafc;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #f1f5f9;
        color: var(--text-muted);
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        font-size: 1.1rem;
    }
    .btn-icon-p:hover {
        background: white;
        transform: translateY(-2px);
        box-shadow: var(--shadow-sm);
        border-color: var(--primary-light);
        color: var(--primary);
    }
</style>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="display: flex; flex-direction: column; gap: 1.5rem; width: 100%; max-width: 100%;">
        
        <!-- Premium Action Header -->
        <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <a href="attendance.php" class="btn-icon-p" style="text-decoration: none;" title="Back to Attendance Sheet"><i class="ri-arrow-left-line"></i></a>
                <div>
                    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                        <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;"><?= htmlspecialchars(trim($employee['first_name'] . ' ' . $employee['last_name'])) ?></h2>
                        <span style="background: #eef2ff; color: #4338ca; padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;"><?= htmlspecialchars($employee['role_name'] ?? $employee['job_role']) ?></span>
                    </div>
                    <p style="color: #64748b; margin-top: 0.25rem; margin-bottom: 0;">Detailed attendance calendar and analytics monitor</p>
                </div>
            </div>
            
            <div style="display: flex; gap: 0.75rem; align-items: center;">
                <span style="font-size: 0.85rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Switch Employee:</span>
                <!-- Employee Switcher -->
                <select id="emp-switcher" class="premium-input" style="width: 220px; padding: 8px 12px; border-radius: 8px; border: 1px solid #cbd5e1; font-weight: 600; outline: none;" onchange="switchEmployee(this.value)">
                    <?php foreach ($all_employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>" <?= $emp['id'] == $employee_id ? 'selected' : '' ?>><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <!-- Cream Month picker card (100% Width) -->
        <div class="cream-header">
            <div class="cream-header-title">
                <i class="ri-information-line"></i>
                <span>Attendance For</span>
            </div>
            <div class="no-print">
                <div class="timeline-pill" onclick="openDatePicker()">
                    <i class="ri-calendar-line"></i>
                    <span><?= date('F Y', mktime(0,0,0,$month,1,$year)) ?></span>
                    <i class="ri-arrow-down-s-line"></i>
                </div>
                <!-- Hidden input month picker -->
                <input type="month" id="timeline-picker" value="<?= sprintf('%04d-%02d', $year, $month) ?>" style="position: absolute; opacity: 0; pointer-events: none; width: 0; height: 0;" onchange="timelineChanged(this.value)">
            </div>
            <div class="only-print" style="display: none; font-weight: 800; color: #78350f;">
                <?= date('F Y', mktime(0,0,0,$month,1,$year)) ?>
            </div>
        </div>

        <!-- Premium Standard Full-Width Summary Stats -->
        <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 1.25rem;">
            <div class="table-container" style="padding: 1.25rem; margin-top: 0; display: flex; align-items: center; justify-content: space-between; border-left: 4px solid var(--present-color);">
                <div>
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Present</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--present-color); margin-top: 0.5rem;"><?= $present_count ?></div>
                </div>
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(34, 197, 94, 0.1); color: var(--present-color); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    <i class="ri-checkbox-circle-line"></i>
                </div>
            </div>

            <div class="table-container" style="padding: 1.25rem; margin-top: 0; display: flex; align-items: center; justify-content: space-between; border-left: 4px solid var(--absent-color);">
                <div>
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Absent</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--absent-color); margin-top: 0.5rem;"><?= $absent_count ?></div>
                </div>
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(239, 68, 68, 0.1); color: var(--absent-color); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    <i class="ri-close-circle-line"></i>
                </div>
            </div>

            <div class="table-container" style="padding: 1.25rem; margin-top: 0; display: flex; align-items: center; justify-content: space-between; border-left: 4px solid var(--halfday-color);">
                <div>
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Half Day</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--halfday-color); margin-top: 0.5rem;"><?= $half_day_count ?></div>
                </div>
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(245, 158, 11, 0.1); color: var(--halfday-color); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    <i class="ri-star-half-line"></i>
                </div>
            </div>

            <div class="table-container" style="padding: 1.25rem; margin-top: 0; display: flex; align-items: center; justify-content: space-between; border-left: 4px solid var(--leave-color);">
                <div>
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Paid Leave</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--leave-color); margin-top: 0.5rem;"><?= number_format($paid_leave_count, 1) ?></div>
                </div>
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(236, 72, 153, 0.1); color: var(--leave-color); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    <i class="ri-umbrella-line"></i>
                </div>
            </div>

            <div class="table-container" style="padding: 1.25rem; margin-top: 0; display: flex; align-items: center; justify-content: space-between; border-left: 4px solid var(--weekoff-color);">
                <div>
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Week Off</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--weekoff-color); margin-top: 0.5rem;"><?= $week_off_count ?></div>
                </div>
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(100, 116, 139, 0.1); color: var(--weekoff-color); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    <i class="ri-calendar-event-line"></i>
                </div>
            </div>
        </div>

        <!-- Desktop Split Layout (Actions Left, Calendar Sheet Right) -->
        <div style="display: grid; grid-template-columns: 260px 1fr; gap: 1.5rem; align-items: start;">
            
            <!-- Left Side controls card stack -->
            <div class="no-print" style="display: flex; flex-direction: column; gap: 1.5rem;">

                <!-- Action Links card -->
                <div class="table-container" style="padding: 1.5rem; margin-top: 0;">
                    <div style="font-weight: 700; color: #1e293b; font-size: 0.85rem; margin-bottom: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em;">Calendar Actions</div>
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <button class="action-list-btn" onclick="printCalendar()" style="color: #0284c7;">
                            <i class="ri-download-2-line"></i>
                            <span>Download Report</span>
                        </button>
                        <button class="action-list-btn" onclick="confirmMarkAllPresent()" style="color: #4f46e5;">
                            <i class="ri-checkbox-multiple-line"></i>
                            <span>Mark All Present</span>
                        </button>

                    </div>
                </div>
                
            </div>

            <!-- Right Side calendar card -->
            <div class="calendar-container">
                <!-- Day Names Header -->
                <div class="weekday-header">
                    <div>Sun</div>
                    <div>Mon</div>
                    <div>Tue</div>
                    <div>Wed</div>
                    <div>Thu</div>
                    <div>Fri</div>
                    <div>Sat</div>
                </div>

                <!-- Days Elements -->
                <div class="calendar-days-grid">
                    <!-- Blank cells for alignment -->
                    <?php for ($i = 0; $i < $first_day_of_week; $i++): ?>
                        <div class="day-block day-empty"></div>
                    <?php endfor; ?>

                    <!-- Month Days -->
                    <?php 
                    for ($d = 1; $d <= $days_in_month; $d++): 
                        $date_str = sprintf('%04d-%02d-%02d', $year, $month, $d);
                        $day_of_week = date('N', strtotime($date_str));
                        $is_sunday = ($day_of_week == 7);
                        
                        $record = $attendance[$d] ?? null;
                        
                        // Default states
                        $day_class = "day-unmarked";
                        $tooltip = "<strong>Day $d: Unmarked</strong><br>No attendance logged.";
                        $badge = "";
                        
                        if ($is_sunday) {
                            $day_class = "day-weekoff";
                            $tooltip = "<strong>Sunday: Week Off</strong><br>Standard weekly rest day.";
                        }
                        
                        if ($record) {
                            $status = $record['status'];
                            $check_in = $record['check_in'] ? date('h:i A', strtotime($record['check_in'])) : 'None';
                            $check_out = $record['check_out'] ? date('h:i A', strtotime($record['check_out'])) : 'None';
                            $mode = $record['working_from'] ?: 'Office';
                            
                            $tooltip = "<strong>" . date('d M, Y', strtotime($date_str)) . "</strong><br>"
                                     . "Status: <strong>$status</strong><br>"
                                     . "Check In: $check_in<br>"
                                     . "Check Out: $check_out<br>"
                                     . "Mode: $mode";
                            
                            if ($status === 'Present') {
                                $day_class = "day-present";
                            } elseif ($status === 'Absent') {
                                $day_class = "day-absent";
                            } elseif ($status === 'Late') {
                                $day_class = "day-present";
                                $badge = '<div class="late-badge">LATE</div>';
                            } elseif ($status === 'Half Day') {
                                $day_class = "day-halfday";
                                // Half days can be late or regular
                                if ($record['is_late'] || strpos(strtolower($record['check_in_location'] ?? ''), 'late') !== false) {
                                    $badge = '<div class="late-badge">LATE</div>';
                                }
                            } elseif ($status === 'On Leave') {
                                $day_class = "day-leave";
                            }
                            
                            // Check if late boolean is flagged on present
                            if ($record['is_late'] && $status === 'Present') {
                                $badge = '<div class="late-badge">LATE</div>';
                            }
                        }
                    ?>
                        <div class="day-block <?= $day_class ?>" onclick="openEditAttendanceModal('<?= $date_str ?>', <?= $d ?>)">
                            <?= sprintf('%02d', $d) ?>
                            <?= $badge ?>
                            <div class="tooltip-content"><?= $tooltip ?></div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

        </div>

    </div>
</main>

<!-- Live Location List Modal -->
<div id="liveLocationModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 1001; align-items: center; justify-content: center; backdrop-filter: blur(6px);" class="no-print">
    <div style="background: white; border-radius: 16px; width: 550px; max-width: 90vw; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: #fafafa;">
            <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #1976d2; display: flex; align-items: center; gap: 0.5rem;">
                <i class="ri-map-pin-user-line"></i> Monthly Location Logs
            </h3>
            <button onclick="closeLiveLocationModal()" style="background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 1.5rem;"><i class="ri-close-line"></i></button>
        </div>
        <div style="padding: 1.5rem; max-height: 60vh; overflow-y: auto;">
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <?php 
                $has_locations = false;
                foreach ($att_records as $rec) {
                    if ($rec['check_in_location'] || $rec['check_out_location']) {
                        $has_locations = true;
                        ?>
                        <div style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem; background: #f8fafc;">
                            <div style="font-weight: 800; color: #1e293b; font-size: 0.85rem; margin-bottom: 0.5rem; display: flex; justify-content: space-between;">
                                <span><?= date('d M Y, l', strtotime($rec['attendance_date'])) ?></span>
                                <span style="background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 20px; font-size: 0.65rem;"><?= htmlspecialchars($rec['working_from'] ?: 'Office') ?></span>
                            </div>
                            <?php if ($rec['check_in_location']): ?>
                                <div style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.8rem; color: #475569; margin-bottom: 0.25rem;">
                                    <i class="ri-login-circle-line" style="color: var(--success); font-size: 0.95rem; margin-top: 2px;"></i>
                                    <div><strong>Punch In Loc:</strong> <?= htmlspecialchars($rec['check_in_location']) ?> (<?= date('h:i A', strtotime($rec['check_in'])) ?>)</div>
                                </div>
                            <?php endif; ?>
                            <?php if ($rec['check_out_location']): ?>
                                <div style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.8rem; color: #475569;">
                                    <i class="ri-logout-circle-line" style="color: var(--absent-color); font-size: 0.95rem; margin-top: 2px;"></i>
                                    <div><strong>Punch Out Loc:</strong> <?= htmlspecialchars($rec['check_out_location']) ?> (<?= date('h:i A', strtotime($rec['check_out'])) ?>)</div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php
                    }
                }
                if (!$has_locations):
                ?>
                    <div style="text-align: center; padding: 3rem 0; color: #94a3b8;">
                        <i class="ri-map-pin-2-line" style="font-size: 3.5rem; opacity: 0.3; display: block; margin-bottom: 1rem;"></i>
                        <span style="font-weight: 600;">No live location logs registered for this month.</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div style="padding: 1rem 1.5rem; background: #fafafa; border-top: 1px solid #f1f5f9; text-align: right;">
        </div>
    </div>
</div>

<!-- Edit Attendance Modal -->
<div id="editAttendanceModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 1002; align-items: center; justify-content: center; backdrop-filter: blur(6px);" class="no-print">
    <div style="background: white; border-radius: 16px; width: 950px; max-width: 95vw; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
        <!-- Modal Title bar -->
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: white;">
            <h3 style="margin: 0; font-size: 1.2rem; font-weight: 800; color: #1e293b;">
                Edit Attendance : <?= htmlspecialchars($employee['first_name']) ?>
            </h3>
            <button onclick="closeEditAttendanceModal()" style="background: none; border: none; color: #64748b; cursor: pointer; font-size: 1.5rem;"><i class="ri-close-line"></i></button>
        </div>
        
        <!-- Main modal content split -->
        <div style="display: grid; grid-template-columns: 1.15fr 1fr; gap: 0; background: #fff;">
            
            <!-- Left Column: Mini Stats and Mini Calendar -->
            <div style="padding: 2rem; border-right: 1px solid #f1f5f9; display: flex; flex-direction: column; gap: 1.5rem; align-items: center; justify-content: center; background: #fafafa;">
                <!-- Mini Stats (like main stats but tighter) -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; width: 100%;">
                    <div class="summary-box border-present" style="min-height: 60px; padding: 0.5rem 0.75rem;">
                        <span class="summary-box-label" style="font-size: 0.75rem;">Present</span>
                        <span class="summary-box-val" id="modal-present-val" style="font-size: 1.25rem; margin-top: 0.15rem;"><?= $present_count ?></span>
                    </div>
                    <div class="summary-box border-absent" style="min-height: 60px; padding: 0.5rem 0.75rem;">
                        <span class="summary-box-label" style="font-size: 0.75rem;">Absent</span>
                        <span class="summary-box-val" id="modal-absent-val" style="font-size: 1.25rem; margin-top: 0.15rem;"><?= $absent_count ?></span>
                    </div>
                    <div class="summary-box border-halfday" style="min-height: 60px; padding: 0.5rem 0.75rem;">
                        <span class="summary-box-label" style="font-size: 0.75rem;">Half day</span>
                        <span class="summary-box-val" id="modal-halfday-val" style="font-size: 1.25rem; margin-top: 0.15rem;"><?= $half_day_count ?></span>
                    </div>
                    <div class="summary-box border-leave" style="min-height: 60px; padding: 0.5rem 0.75rem; grid-column: span 1;">
                        <span class="summary-box-label" style="font-size: 0.75rem;">Paid Leave</span>
                        <span class="summary-box-val" id="modal-leave-val" style="font-size: 1.25rem; margin-top: 0.15rem;"><?= number_format($paid_leave_count, 1) ?></span>
                    </div>
                    <div class="summary-box border-weekoff" style="min-height: 60px; padding: 0.5rem 0.75rem; grid-column: span 2;">
                        <span class="summary-box-label" style="font-size: 0.75rem;">Week Off</span>
                        <span class="summary-box-val" id="modal-weekoff-val" style="font-size: 1.25rem; margin-top: 0.15rem;"><?= $week_off_count ?></span>
                    </div>
                </div>
                
                <!-- Mini Calendar (rendered statically via php clone) -->
                <div class="calendar-container" style="padding: 1rem; width: 100%; max-width: 100%; border: 1px solid #e2e8f0; background: white; box-shadow: none;">
                    <div class="weekday-header" style="font-size: 0.7rem; margin-bottom: 0.5rem; padding-bottom: 0.4rem;">
                        <div>Sun</div>
                        <div>Mon</div>
                        <div>Tue</div>
                        <div>Wed</div>
                        <div>Thu</div>
                        <div>Fri</div>
                        <div>Sat</div>
                    </div>
                    <div class="calendar-days-grid" style="gap: 0.4rem;">
                        <!-- Blank cells -->
                        <?php for ($i = 0; $i < $first_day_of_week; $i++): ?>
                            <div class="day-block day-empty"></div>
                        <?php endfor; ?>

                        <!-- Days -->
                        <?php 
                        for ($d = 1; $d <= $days_in_month; $d++): 
                            $date_str = sprintf('%04d-%02d-%02d', $year, $month, $d);
                            $day_of_week = date('N', strtotime($date_str));
                            $is_sunday = ($day_of_week == 7);
                            
                            $record = $attendance[$d] ?? null;
                            $day_class = "day-unmarked";
                            $badge = "";
                            
                            if ($is_sunday) $day_class = "day-weekoff";
                            
                            if ($record) {
                                $status = $record['status'];
                                if ($status === 'Present') $day_class = "day-present";
                                elseif ($status === 'Absent') $day_class = "day-absent";
                                elseif ($status === 'Late') {
                                    $day_class = "day-present";
                                    $badge = '<div class="late-badge" style="font-size: 0.4rem; bottom: 2px;">LATE</div>';
                                } elseif ($status === 'Half Day') {
                                    $day_class = "day-halfday";
                                    if ($record['is_late']) $badge = '<div class="late-badge" style="font-size: 0.4rem; bottom: 2px;">LATE</div>';
                                } elseif ($status === 'On Leave') $day_class = "day-leave";
                            }
                        ?>
                            <div class="day-block <?= $day_class ?>" id="modal-day-<?= $d ?>" onclick="modalSelectDay('<?= $date_str ?>', <?= $d ?>)" style="height: 34px; font-size: 0.8rem; border-radius: 6px;">
                                <?= sprintf('%02d', $d) ?>
                                <?= $badge ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <!-- Mark All Absent as Present Button -->
                <button onclick="modalMarkAllAbsentAsPresent()" style="background: white; border: 1.5px solid #0284c7; color: #0284c7; padding: 10px 24px; border-radius: 8px; font-weight: 700; font-size: 0.8rem; cursor: pointer; transition: all 0.2s; outline: none; width: 100%; text-align: center;">
                    Mark All Absent as Present
                </button>
            </div>
            
            <!-- Right Column: Editing and Punching controls -->
            <div style="padding: 2rem; display: flex; flex-direction: column; gap: 1.5rem; background: white;">
                <!-- Heading with selected date and refresh -->
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1.5px solid #f1f5f9; padding-bottom: 0.75rem;">
                    <span id="selected-day-label" style="font-size: 1.3rem; font-weight: 800; color: #1e293b;">1st July</span>
                    <button onclick="refreshDayDetails()" style="background: none; border: none; color: #64748b; cursor: pointer; font-size: 1.2rem; outline: none;" title="Reset Punch States"><i class="ri-refresh-line"></i></button>
                </div>
                
                <!-- Pill Toggle selector -->
                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                    <button class="pill-btn pill-absent" id="pill-ABSENT" onclick="saveDayStatus('ABSENT')">Absent</button>
                    <button class="pill-btn pill-halfday" id="pill-HALF_DAY" onclick="saveDayStatus('HALF DAY')">Half Day</button>
                    <button class="pill-btn pill-present" id="pill-PRESENT" onclick="saveDayStatus('PRESENT')">Present</button>
                    <button class="pill-btn pill-weekoff" id="pill-WEEK_OFF" onclick="saveDayStatus('WEEK OFF')">Week Off</button>
                    <button class="pill-btn pill-holiday" id="pill-HOLIDAY" onclick="saveDayStatus('HOLIDAY')">Holiday</button>
                </div>
                
                <!-- Leaves section -->
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <span style="font-weight: 800; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Leaves</span>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                        <button class="pill-btn pill-leave-paid" id="pill-PAID_LEAVE" onclick="saveDayStatus('PAID LEAVE')">Paid Leave</button>
                        <button class="pill-btn pill-leave-half" id="pill-HALF_DAY_LEAVE" onclick="saveDayStatus('HALF DAY LEAVE')">Half Day Leave</button>
                        <button class="pill-btn pill-leave-unpaid" id="pill-UNPAID_LEAVE" onclick="saveDayStatus('UNPAID LEAVE')">Unpaid Leave</button>
                    </div>
                </div>
                
                <!-- Punches list -->
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <span style="font-weight: 800; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Punch Logs</span>
                    <div id="punch-logs-container" style="display: flex; flex-direction: column; gap: 0.5rem; max-height: 120px; overflow-y: auto;">
                        <!-- Rendered dynamically -->
                    </div>
                    
                    <!-- Add Punch link and inline form -->
                    <div style="margin-top: 0.25rem;">
                        <a href="javascript:void(0)" onclick="toggleAddPunchForm()" id="add-punch-link" style="font-size: 0.8rem; font-weight: 800; color: #0284c7; text-decoration: underline;">+ ADD PUNCH IN</a>
                        
                        <div id="add-punch-form" style="display: none; align-items: center; gap: 0.5rem; background: #f8fafc; padding: 0.5rem; border-radius: 8px; border: 1px solid #e2e8f0; margin-top: 0.5rem;">
                            <input type="time" id="new-punch-time" value="09:00" class="premium-input" style="width: auto; padding: 4px 8px; font-size: 0.8rem; border-radius: 6px;">
                            <select id="new-punch-type" class="premium-input" style="width: auto; padding: 4px 8px; font-size: 0.8rem; border-radius: 6px;">
                                <option value="In">In</option>
                                <option value="Out">Out</option>
                            </select>
                            <button onclick="submitNewPunch()" class="btn btn-primary" style="padding: 4px 12px; font-size: 0.8rem; font-weight: 700; border-radius: 6px; background: #0284c7; color: white; border: none; cursor: pointer;">Add</button>
                        </div>
                    </div>
                </div>
                
                <!-- Notes field -->
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <textarea id="attendance-note" placeholder="Add Note" style="width: 100%; border: 1.5px solid #cbd5e1; border-radius: 12px; padding: 0.75rem 1rem; font-size: 0.85rem; outline: none; transition: border-color 0.2s; resize: none; height: 75px;" onblur="saveDayNote(this.value)" onfocus="this.style.borderColor='#0284c7'"></textarea>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function openDatePicker() {
        document.getElementById('timeline-picker').showPicker();
    }

    function timelineChanged(val) {
        if (!val) return;
        const [year, month] = val.split('-');
        window.location.href = `employee-attendance.php?id=<?= $employee_id ?>&month=${parseInt(month)}&year=${year}`;
    }

    function switchEmployee(val) {
        if (!val) return;
        window.location.href = `employee-attendance.php?id=${val}&month=<?= $month ?>&year=<?= $year ?>`;
    }

    function printCalendar() {
        window.print();
    }

    function showLiveLocationModal() {
        document.getElementById('liveLocationModal').style.display = 'flex';
    }

    function closeLiveLocationModal() {
        document.getElementById('liveLocationModal').style.display = 'none';
    }

    function confirmMarkAllPresent() {
        Swal.fire({
            title: 'Mark All Present?',
            text: "This will fill all weekdays of the current month with Present attendance records. Existing records will be overwritten.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#4f46e5',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, Mark All Present'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.showLoading();
                fetch('employee-attendance.php?id=<?= $employee_id ?>&month=<?= $month ?>&year=<?= $year ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=mark_all_present'
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: 'All weekdays marked as Present successfully.',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('Error', data.error || 'Operation failed', 'error');
                    }
                })
                .catch(err => {
                    Swal.fire('Error', 'Connection failed or server error.', 'error');
                });
            }
        });
    }

    // Close modals on outside click
    window.onclick = function(event) {
        const modal = document.getElementById('liveLocationModal');
        const editModal = document.getElementById('editAttendanceModal');
        if (event.target == modal) {
            modal.style.display = "none";
        }
        if (event.target == editModal) {
            closeEditAttendanceModal();
        }
    }

    // Advanced Attendance Modal Javascript Variables & Handlers
    let selectedDate = null;
    let selectedDay = null;

    function openEditAttendanceModal(dateStr, dayNum) {
        selectedDate = dateStr;
        selectedDay = dayNum;
        
        // Highlight active day in modal calendar
        document.querySelectorAll('#editAttendanceModal .day-block').forEach(el => {
            el.style.border = 'none';
        });
        const activeModalDay = document.getElementById(`modal-day-${dayNum}`);
        if (activeModalDay) {
            activeModalDay.style.border = '2px solid #0284c7';
        }

        // Show Modal
        document.getElementById('editAttendanceModal').style.display = 'flex';
        loadDayDetails(dateStr);
    }

    function closeEditAttendanceModal() {
        document.getElementById('editAttendanceModal').style.display = 'none';
        // Reload page on modal close to refresh all summary stats and calendar cells perfectly
        location.reload();
    }

    function formatDateLabel(dateStr) {
        const d = new Date(dateStr);
        const day = d.getDate();
        const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        const month = months[d.getMonth()];
        
        let suffix = 'th';
        if (day === 1 || day === 21 || day === 31) suffix = 'st';
        else if (day === 2 || day === 22) suffix = 'nd';
        else if (day === 3 || day === 23) suffix = 'rd';
        
        return `${day}${suffix} ${month}`;
    }

    function loadDayDetails(dateStr) {
        document.getElementById('selected-day-label').innerText = formatDateLabel(dateStr);
        
        // Hide add punch form
        document.getElementById('add-punch-form').style.display = 'none';
        
        // Clear all active classes from pill buttons
        document.querySelectorAll('.pill-btn').forEach(btn => {
            btn.classList.remove('active');
        });

        // Fetch details from server
        fetch('employee-attendance.php?id=<?= $employee_id ?>&month=<?= $month ?>&year=<?= $year ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=get_day_details&date=${dateStr}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const att = data.attendance;
                const logs = data.logs;
                const shiftName = data.shift_name;
                
                // 1. Highlight correct pill button based on status
                if (att) {
                    const status = att.status;
                    const isHalfDay = parseInt(att.is_half_day);
                    const isLate = parseInt(att.is_late);
                    
                    if (status === 'Absent') {
                        document.getElementById('pill-ABSENT').classList.add('active');
                    } else if (status === 'Half Day') {
                        document.getElementById('pill-HALF_DAY').classList.add('active');
                    } else if (status === 'Present' || status === 'Late') {
                        document.getElementById('pill-PRESENT').classList.add('active');
                    } else if (status === 'On Leave') {
                        if (isHalfDay) {
                            document.getElementById('pill-HALF_DAY_LEAVE').classList.add('active');
                        } else {
                            document.getElementById('pill-PAID_LEAVE').classList.add('active'); // default paid leave
                        }
                    }
                    
                    // Set note
                    document.getElementById('attendance-note').value = att.notes || '';
                } else {
                    // Check if Sunday
                    const dayOfWeek = new Date(dateStr).getDay();
                    if (dayOfWeek === 0) {
                        document.getElementById('pill-WEEK_OFF').classList.add('active');
                    } else {
                        document.getElementById('pill-ABSENT').classList.add('active'); // Default to Absent if unmarked
                    }
                    document.getElementById('attendance-note').value = '';
                }
                
                // 2. Render Punch Logs
                const container = document.getElementById('punch-logs-container');
                container.innerHTML = '';
                
                if (logs && logs.length > 0) {
                    logs.forEach(log => {
                        const punchHtml = `
                            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1.5px solid #f1f5f9; padding: 0.5rem 0;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: #eef2ff; color: #4f46e5; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 800;">A</div>
                                    <div style="font-size: 0.85rem; font-weight: 700; color: #1e293b;">
                                        ${log.formatted_time} <span style="font-weight:500; color: #64748b;">&bull;</span> ${log.log_type}
                                    </div>
                                </div>
                                <div style="font-size: 0.75rem; color: #64748b; font-weight: 700;">${shiftName}</div>
                            </div>
                        `;
                        container.insertAdjacentHTML('beforeend', punchHtml);
                    });
                } else {
                    container.innerHTML = '<div style="text-align:center; padding:1.5rem 0; color:#94a3b8; font-size:0.8rem; font-weight:600;">No punches logged today.</div>';
                }
            } else {
                Swal.fire('Error', 'Failed to fetch day details', 'error');
            }
        })
        .catch(err => {
            console.error(err);
        });
    }

    function modalSelectDay(dateStr, dayNum) {
        selectedDate = dateStr;
        selectedDay = dayNum;
        
        // Highlight active day in modal calendar
        document.querySelectorAll('#editAttendanceModal .day-block').forEach(el => {
            el.style.border = 'none';
        });
        const activeModalDay = document.getElementById(`modal-day-${dayNum}`);
        if (activeModalDay) {
            activeModalDay.style.border = '2px solid #0284c7';
        }
        
        loadDayDetails(dateStr);
    }

    function saveDayStatus(status) {
        // Toggle active button visually instantly
        document.querySelectorAll('.pill-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        const btnId = 'pill-' + status.replace(' ', '_');
        const activeBtn = document.getElementById(btnId);
        if (activeBtn) activeBtn.classList.add('active');

        // Send AJAX save status
        fetch('employee-attendance.php?id=<?= $employee_id ?>&month=<?= $month ?>&year=<?= $year ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=save_day_status&date=${selectedDate}&status=${status}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Instantly update modal calendar class for this day
                const activeDayCell = document.getElementById(`modal-day-${selectedDay}`);
                if (activeDayCell) {
                    activeDayCell.className = 'day-block'; // reset classes
                    if (status === 'ABSENT') activeDayCell.classList.add('day-absent');
                    else if (status === 'HALF DAY') activeDayCell.classList.add('day-halfday');
                    else if (status === 'PRESENT') activeDayCell.classList.add('day-present');
                    else if (status === 'WEEK OFF') activeDayCell.classList.add('day-weekoff');
                    else if (status === 'HOLIDAY') activeDayCell.classList.add('day-weekoff');
                    else activeDayCell.classList.add('day-leave');
                }
                loadDayDetails(selectedDate);
            } else {
                Swal.fire('Error', 'Failed to update day status', 'error');
            }
        });
    }

    function toggleAddPunchForm() {
        const form = document.getElementById('add-punch-form');
        form.style.display = form.style.display === 'none' ? 'flex' : 'none';
    }

    function submitNewPunch() {
        const time = document.getElementById('new-punch-time').value;
        const type = document.getElementById('new-punch-type').value;
        
        fetch('employee-attendance.php?id=<?= $employee_id ?>&month=<?= $month ?>&year=<?= $year ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=add_day_punch&date=${selectedDate}&time=${time}&type=${type}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('add-punch-form').style.display = 'none';
                loadDayDetails(selectedDate);
            } else {
                Swal.fire('Error', 'Failed to add punch log', 'error');
            }
        });
    }

    function saveDayNote(notes) {
        fetch('employee-attendance.php?id=<?= $employee_id ?>&month=<?= $month ?>&year=<?= $year ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=save_day_note&date=${selectedDate}&notes=${encodeURIComponent(notes)}`
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                Swal.fire('Error', 'Failed to save note', 'error');
            }
        });
    }

    function refreshDayDetails() {
        loadDayDetails(selectedDate);
    }

    function modalMarkAllAbsentAsPresent() {
        Swal.fire({
            title: 'Mark All Absent as Present?',
            text: "This will fill all absent or unmarked weekdays of the current month with Present records.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0284c7',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, Fill Weekdays'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.showLoading();
                fetch('employee-attendance.php?id=<?= $employee_id ?>&month=<?= $month ?>&year=<?= $year ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=mark_all_absent_as_present'
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: 'All weekdays filled successfully.',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('Error', data.error || 'Operation failed', 'error');
                    }
                });
            }
        });
    }
</script>

<?php include 'includes/footer.php'; ?>
