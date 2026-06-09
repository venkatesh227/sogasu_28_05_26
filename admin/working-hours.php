<?php
ob_start();
session_start();
include '../includes/db.php';

$filter_employee = $_GET['employee_id'] ?? '';
$filter_from = $_GET['from_date'] ?? date('Y-m-01');
$filter_to = $_GET['to_date'] ?? date('Y-m-d');
$filter_preset = $_GET['preset'] ?? '';

if ($filter_preset === 'daily') {
    $filter_from = $filter_to = date('Y-m-d');
} elseif ($filter_preset === 'weekly') {
    $filter_from = date('Y-m-d', strtotime('monday this week'));
    $filter_to = date('Y-m-d', strtotime('sunday this week'));
} elseif ($filter_preset === 'monthly') {
    $filter_from = date('Y-m-01');
    $filter_to = date('Y-m-t');
}

// Fetch Employees for dropdown
$empList = $pdo->query("SELECT id, first_name, last_name FROM employees WHERE is_deleted = 0 ORDER BY first_name ASC")->fetchAll();

// Build Query to fetch all logs with employee shift info
$query = "
    SELECT 
        al.*,
        e.first_name, e.last_name, e.job_role,
        s.start_time as shift_start,
        s.late_mark_after
    FROM attendance_logs al
    JOIN employees e ON al.employee_id = e.id
    LEFT JOIN shift_types s ON e.default_shift_id = s.id
    WHERE al.log_date BETWEEN ? AND ?
";
$params = [$filter_from, $filter_to];

if ($filter_employee) {
    $query .= " AND al.employee_id = ?";
    $params[] = $filter_employee;
}

$query .= " ORDER BY al.log_date ASC, al.employee_id ASC, al.log_time ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$all_logs = $stmt->fetchAll();

// Process logs into daily sessions
$daily_sessions = []; // [emp_id][date] = [[in, out], [in, out]]
foreach ($all_logs as $log) {
    $emp_id = $log['employee_id'];
    $date = $log['log_date'];
    $type = $log['log_type'];
    $time = $log['log_time'];

    if (!isset($daily_sessions[$emp_id][$date])) {
        $daily_sessions[$emp_id][$date] = [];
    }

    $sessions = &$daily_sessions[$emp_id][$date];
    $last_index = count($sessions) - 1;

    if ($type === 'In') {
        $sessions[] = ['in' => $time, 'out' => null, 'meta' => $log];
    } else {
        if ($last_index >= 0 && $sessions[$last_index]['out'] === null) {
            $sessions[$last_index]['out'] = $time;
        }
    }
}

// Flatten sessions into records for display

$records = [];
foreach ($daily_sessions as $emp_id => $dates) {
    foreach ($dates as $date => $sessions) {
        if (empty($sessions)) continue;
        
        $total_sec = 0;
        foreach ($sessions as $s) {
            if ($s['in'] && $s['out']) {
                $total_sec += strtotime($s['out']) - strtotime($s['in']);
            }
        }
        
        $h = floor($total_sec / 3600);
        $m = floor(($total_sec % 3600) / 60);
        $duration = sprintf("%02d:%02d:00", $h, $m);
        $decimal = $total_sec / 3600;

        $meta = $sessions[0]['meta'];
        $is_late = false;
        if ($meta['shift_start'] && $sessions[0]['in']) {
            $check_in_time = strtotime($sessions[0]['in']);
            $shift_start_time = strtotime($meta['shift_start']);
            $grace_sec = ($meta['late_mark_after'] ?: 0) * 60;
            if ($check_in_time > ($shift_start_time + $grace_sec)) {
                $is_late = true;
            }
        }

        $is_half_day = ($decimal > 0 && $decimal < 4) ? true : false;

        $records[] = [
            'attendance_date' => $date,
            'employee_id' => $emp_id,
            'first_name' => $meta['first_name'],
            'last_name' => $meta['last_name'],
            'job_role' => $meta['job_role'],
            'check_in' => $sessions[0]['in'],
            'check_out' => end($sessions)['out'], 
            'work_duration' => $duration,
            'total_hours' => $decimal,
            'session_count' => count($sessions),
            'is_late' => $is_late,
            'is_half_day' => $is_half_day
        ];
    }
}

// Sort records by date DESC

usort($records, function($a, $b) {
    return strcmp($b['attendance_date'], $a['attendance_date']);
});

// Summary Calculation

$summary = [];
foreach ($records as $r) {
    $emp_id = $r['employee_id'];
    if (!isset($summary[$emp_id])) {
        $summary[$emp_id] = [
            'name' => $r['first_name'] . ' ' . $r['last_name'],
            'role' => $r['job_role'],
            'total_hrs' => 0,
            'days' => 0,
            'lates' => 0,
            'half_days' => 0
        ];
    }
    $summary[$emp_id]['total_hrs'] += $r['total_hours'];
    $summary[$emp_id]['days']++;
    if ($r['is_late']) $summary[$emp_id]['lates']++;
    if ($r['is_half_day']) $summary[$emp_id]['half_days']++;
}

$pageTitle = "Working Hours Report - Sogasu";
$activePage = "hr_reports";
include 'includes/header.php';
?>

<main class="main-content" style="overflow-y: auto !important; height: 100vh;">
    <?php include 'includes/topbar.php'; ?>

    <div style="margin-bottom: 2rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Working Hours Analysis</h2>
                <p class="text-muted" style="margin: 0;">Track and calculate employee productivity time.</p>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <button onclick="window.print()" class="btn" style="background: white; border: 1px solid #e2e8f0; color: #64748b;">
                    <i class="ri-printer-line"></i> Print
                </button>
                <button class="btn btn-primary" onclick="exportReport()">
                    <i class="ri-file-excel-line"></i> Export CSV
                </button>
            </div>
        </div>

        <!-- Filter Card -->

        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem;">
            <form action="" method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.5rem; align-items: end;">
                <div>
                    <label style="display: block; font-size: 0.8rem; color: #64748b; margin-bottom: 0.5rem;">Select Employee</label>
                    <select name="employee_id" style="width: 100%; padding: 0.6rem; border: 1px solid #e2e8f0; border-radius: 6px; background: white;">
                        <option value="">All Employees</option>
                        <?php foreach ($empList as $emp): ?>
                            <option value="<?= $emp['id'] ?>" <?= $filter_employee == $emp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 0.8rem; color: #64748b; margin-bottom: 0.5rem;">Preset</label>
                    <select name="preset" onchange="this.form.submit()" style="width: 100%; padding: 0.6rem; border: 1px solid #e2e8f0; border-radius: 6px; background: #f8fafc;">
                        <option value="">Custom Range</option>
                        <option value="daily" <?= $filter_preset == 'daily' ? 'selected' : '' ?>>Daily (Today)</option>
                        <option value="weekly" <?= $filter_preset == 'weekly' ? 'selected' : '' ?>>Weekly (Mon-Sun)</option>
                        <option value="monthly" <?= $filter_preset == 'monthly' ? 'selected' : '' ?>>Monthly (Current)</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 0.8rem; color: #64748b; margin-bottom: 0.5rem;">From Date</label>
                    <input type="date" name="from_date" value="<?= $filter_from ?>" style="width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 6px;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.8rem; color: #64748b; margin-bottom: 0.5rem;">To Date</label>
                    <input type="date" name="to_date" value="<?= $filter_to ?>" style="width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 6px;">
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1; padding: 0.6rem;">Apply Filters</button>
                    <a href="working-hours.php" class="btn" style="background: #f1f5f9; color: #475569; padding: 0.6rem; text-decoration: none; display: flex; align-items: center; justify-content: center;"><i class="ri-refresh-line"></i></a>
                </div>
            </form>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 350px; gap: 2rem; align-items: start;">
            <!-- Detailed Logs -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
                <div style="padding: 1rem 1.5rem; border-bottom: 1px solid #f1f5f9; background: #fff;">
                    <h3 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0;">Detailed Working Logs</h3>
                </div>
                <div style="padding: 1.5rem; overflow-x: auto;">
                    <table id="hoursTable" class="display" style="width: 100%;">
                        <thead>
                            <tr style="background: #f8fafc; text-align: left; color: #64748b; font-size: 0.85rem; font-weight: 600;">
                                <th>Date</th>
                                <th>Employee</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Hrs (Dec)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $r): ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 1rem 0;"><?= date('d M, Y', strtotime($r['attendance_date'])) ?></td>
                                    <td>
                                        <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></div>
                                        <div style="font-size: 0.75rem; color: #94a3b8;"><?= htmlspecialchars($r['job_role']) ?></div>
                                    </td>
                                    <td><span style="color: #059669; font-weight: 500;"><?= date('h:i A', strtotime($r['check_in'])) ?></span></td>
                                    <td><span style="color: #4338ca; font-weight: 500;"><?= $r['check_out'] ? date('h:i A', strtotime($r['check_out'])) : '--:--' ?></span></td>
                                    <td>
                                        <div style="background: #f1f5f9; padding: 0.2rem 0.5rem; border-radius: 4px; display: inline-block; font-size: 0.85rem; font-weight: 600;"><?= $r['work_duration'] ?: 'N/A' ?></div>
                                        <?php if ($r['session_count'] > 1): ?>
                                            <div style="font-size: 0.65rem; color: #94a3b8; margin-top: 2px;"><i class="ri-history-line"></i> <?= $r['session_count'] ?> sessions</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($r['is_late']): ?>
                                            <span style="display: inline-block; padding: 0.2rem 0.5rem; background: #fff7ed; color: #9a3412; border: 1px solid #ffedd5; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">LATE</span>
                                        <?php endif; ?>
                                        <?php if ($r['is_half_day']): ?>
                                            <span style="display: inline-block; padding: 0.2rem 0.5rem; background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">HALF DAY</span>
                                        <?php endif; ?>
                                        <?php if (!$r['is_late'] && !$r['is_half_day']): ?>
                                            <span style="display: inline-block; padding: 0.2rem 0.5rem; background: #f0fdf4; color: #166534; border: 1px solid #dcfce7; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">ON TIME</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-weight: 700; color: #1e293b;"><?= number_format($r['total_hours'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Summary Sidebar -->

            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
                <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; background: #fff;">
                    <h3 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0;">Employee Totals</h3>
                </div>
                <div style="padding: 1.5rem;">
                    <?php if (empty($summary)): ?>
                        <div style="text-align: center; color: #94a3b8; padding: 2rem 0;">No data found.</div>
                    <?php else: ?>
                        <?php foreach ($summary as $s): ?>
                            <div style="padding: 1rem; border: 1px solid #f1f5f9; border-radius: 8px; margin-bottom: 1rem; background: #fdfdfd;">
                                <div style="font-weight: 700; color: #1e293b; font-size: 0.95rem;"><?= htmlspecialchars($s['name']) ?></div>
                                <div style="font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.75rem;"><?= htmlspecialchars($s['role']) ?></div>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                                    <div style="font-size: 0.85rem; color: #64748b;"><i class="ri-calendar-check-line"></i> <?= $s['days'] ?> Days</div>
                                    <div style="font-size: 1.1rem; font-weight: 800; color: #4338ca;"><?= number_format($s['total_hrs'], 1) ?> hrs</div>
                                </div>
                                <div style="display: flex; gap: 0.5rem;">
                                    <div style="flex: 1; background: #fff7ed; padding: 0.4rem; border-radius: 6px; text-align: center;">
                                        <div style="font-size: 0.65rem; color: #9a3412; text-transform: uppercase; font-weight: 600;">Lates</div>
                                        <div style="font-weight: 700; color: #c2410c;"><?= $s['lates'] ?></div>
                                    </div>
                                    <div style="flex: 1; background: #fef2f2; padding: 0.4rem; border-radius: 6px; text-align: center;">
                                        <div style="font-size: 0.65rem; color: #991b1b; text-transform: uppercase; font-weight: 600;">Half Days</div>
                                        <div style="font-weight: 700; color: #b91c1c;"><?= $s['half_days'] ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/includes/datatable.php'; ?>
<script>
    $(document).ready(function() {
        initializeDataTable('hoursTable', 'Working Hours Report');
    });

    function exportReport() {
        // Simple CSV export logic
        
        let csv = 'Date,Employee,Role,Check In,Check Out,Duration,Hours\n';
        const rows = document.querySelectorAll('#hoursTable tbody tr');
        rows.forEach(row => {
            const cols = row.querySelectorAll('td');
            let rowData = [];
            cols.forEach(col => rowData.push('"' + col.innerText.replace(/\n/g, ' ') + '"'));
            csv += rowData.join(',') + '\n';
        });
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.setAttribute('hidden', '');
        a.setAttribute('href', url);
        a.setAttribute('download', 'working_hours_report.csv');
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }
</script>

<?php include 'includes/footer.php'; ?>
