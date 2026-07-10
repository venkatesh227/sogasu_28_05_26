<?php

function findNextAvailableSlot($pdo, $date, $startTime)
{
    $current = strtotime($startTime);

    $businessEnd = strtotime('08:00 PM');

    while ($current <= $businessEnd) {

        $time = date('H:i:s', $current);

        $stmt = $pdo->prepare("

            SELECT appointment_time
            FROM appointments
            WHERE appointment_date = ?
            AND is_deleted = 0
            AND appointment_time < ?
            AND ADDTIME(appointment_time, '00:15:00') > ?

        ");

        $endTime = date(
            'H:i:s',
            strtotime($time . ' +15 minutes')
        );

        $stmt->execute([
            $date,
            $endTime,
            $time
        ]);

        $exists = $stmt->fetch();

        if (!$exists) {

            return $time;
        }

        $current = strtotime(
            $exists['appointment_time'] . ' +15 minutes'
        );
    }

    return null;
}