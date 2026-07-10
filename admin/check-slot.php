<?php

function checkSlotConflict($pdo, $date, $time, $excludeId = null)
{
    $startTime = date('H:i:s', strtotime($time));

    $endTime = date(
        'H:i:s',
        strtotime($time . ' +15 minutes')
    );

    $stmt = $pdo->prepare("

        SELECT id, appointment_time
        FROM appointments
        WHERE appointment_date = ?
        AND is_deleted = 0
        AND (? IS NULL OR id != ?)
        AND appointment_time < ?
        AND ADDTIME(appointment_time, '00:15:00') > ?

    ");

    $stmt->execute([
        $date,
        $excludeId,
        $excludeId,
        $endTime,
        $startTime
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}