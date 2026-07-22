<?php

function calculateAttendanceSummary($pdo, $fromDate, $toDate)
{
    // Build Query to fetch all logs with employee shift info
    $query = "
    SELECT 
        al.*,
        e.first_name, e.last_name, e.job_role,
        s.start_time as shift_start,
        s.end_time as shift_end,
        s.late_mark_after
    FROM attendance_logs al
    JOIN employees e ON al.employee_id = e.id
    LEFT JOIN shift_roster sr
    ON sr.employee_id = e.id
    AND sr.roster_date = al.log_date

    LEFT JOIN shift_types s
    ON s.id = COALESCE(sr.shift_type_id, e.default_shift_id)
    WHERE al.log_date BETWEEN ? AND ?
";

    $params = [$fromDate, $toDate];
    // Fetch manually marked attendance
    $manualStmt = $pdo->prepare("
    SELECT employee_id, attendance_date, status
    FROM attendance
    WHERE attendance_date BETWEEN ? AND ?
");

    $manualStmt->execute([$fromDate, $toDate]);

    $manualAttendance = [];

    while ($row = $manualStmt->fetch(PDO::FETCH_ASSOC)) {
        $manualAttendance[$row['employee_id']][$row['attendance_date']] = $row['status'];
    }

    $query .= " ORDER BY al.log_date ASC, al.employee_id ASC, al.log_time ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $all_logs = $stmt->fetchAll();
    $all_logs = array_values($all_logs);
    $calculatedAttendance = [];
    $daily_sessions = []; // [emp_id][date] = [[in, out], [in, out]]
    foreach ($all_logs as $log) {
        $emp_id = $log['employee_id'];
        $date = $log['log_date'];
        $type = $log['log_type'];
        $time = $log['log_time'];

        if (!isset($daily_sessions[$emp_id][$date])) {
            $daily_sessions[$emp_id][$date] = [];
        }

        $sessions =& $daily_sessions[$emp_id][$date];
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

    $lateCounts = [];
    foreach ($daily_sessions as $emp_id => $dates) {
        ksort($dates);
        foreach ($dates as $date => $daySessions) {
            // If attendance was manually marked by admin,
// use it directly instead of recalculating from logs.
            if (isset($manualAttendance[$emp_id][$date])) {

                $calculatedAttendance[$emp_id][date('j', strtotime($date))]
                    = $manualAttendance[$emp_id][$date];

                continue;
            }
            if (empty($daySessions))
                continue;

            $total_sec = 0;
            foreach ($daySessions as $s) {
                if ($s['in'] && $s['out']) {
                    $total_sec += strtotime($s['out']) - strtotime($s['in']);
                }
            }
            $meta = $daySessions[0]['meta'] ?? [];

            $status = 'IN_PROGRESS';

            $checkIn = null;
            $shiftStart = null;
            $shiftEnd = null;

            if (
                !empty($meta['shift_start']) &&
                !empty($meta['shift_end']) &&
                !empty($daySessions[0]['in'])
            ) {

                // If employee has not checked out yet, skip this record
                if (empty(end($daySessions)['out'])) {
                    continue;
                }

                $checkIn = strtotime($date . ' ' . $daySessions[0]['in']);

                $shiftStart = strtotime($date . ' ' . $meta['shift_start']);

                $shiftEnd = strtotime($date . ' ' . $meta['shift_end']);

                if ($shiftEnd <= $shiftStart) {
                    $shiftEnd = strtotime('+1 day', $shiftEnd);
                }

                $graceMinutes = !empty($meta['late_mark_after'])
                    ? (int) $meta['late_mark_after']
                    : 15;

                $graceEnd = $shiftStart + ($graceMinutes * 60);

                $minimumHalfDaySeconds = 4 * 3600;

                $isGraceCrossed = ($checkIn > $graceEnd);

                /*
                ============================================
                RULE 1
                Less than 4 hours = Half_day
                ============================================
                */

                // Default
                $status = 'ON_TIME';

                // Less than 4 hrs
                if ($total_sec < $minimumHalfDaySeconds) {

                    $status = 'HALF_DAY';

                }

                // Worked full shift
                else {

                    if (!$isGraceCrossed) {

                        $status = 'ON_TIME';

                    } else {

                        $monthKey = date('Y-m', strtotime($date));

                        if (!isset($lateCounts[$emp_id][$monthKey])) {
                            $lateCounts[$emp_id][$monthKey] = 0;
                        }

                        $lateCounts[$emp_id][$monthKey]++;

                        if ($lateCounts[$emp_id][$monthKey] <= 2) {

                            $status = 'LATE';

                        } else {

                            $status = 'HALF_DAY';

                        }

                    }
                }

                if ($status == 'ON_TIME') {
                    $status = 'Present';
                } elseif ($status == 'LATE') {
                    $status = 'Late';
                } elseif ($status == 'HALF_DAY') {
                    $status = 'Half Day';
                }

                $calculatedAttendance[$emp_id][date('j', strtotime($date))] = $status;

            }

        }

    }
    // Add manually marked attendance that has no biometric logs
    foreach ($manualAttendance as $emp_id => $dates) {

        foreach ($dates as $date => $status) {

            $day = date('j', strtotime($date));

            if (!isset($calculatedAttendance[$emp_id][$day])) {

                $calculatedAttendance[$emp_id][$day] = $status;

            }
        }
    }
    return $calculatedAttendance;
}