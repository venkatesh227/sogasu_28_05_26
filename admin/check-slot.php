<?php

function checkSlotConflict($pdo, $date, $time)
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
        AND appointment_time < ?
        AND ADDTIME(appointment_time, '00:15:00') > ?

        UNION ALL

        SELECT id, appointment_time
        FROM customer_orders
        WHERE appointment_date = ?
        AND is_deleted = 0
        AND appointment_time < ?
        AND ADDTIME(appointment_time, '00:15:00') > ?

    ");

    $stmt->execute([
        $date,
        $endTime,
        $startTime,

        $date,
        $endTime,
        $startTime
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}