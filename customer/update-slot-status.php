<?php

session_start();

require '../includes/db.php';

$id = $_POST['id'] ?? 0;

$action = $_POST['action'] ?? '';

$stmt = $pdo->prepare("
    SELECT *
    FROM appointment_notifications
    WHERE id = ?
");

$stmt->execute([$id]);

$notification = $stmt->fetch();

if (!$notification) {

    echo json_encode([
        'success' => false
    ]);

    exit;
}

/*
|--------------------------------------------------
| ACCEPT SLOT
|--------------------------------------------------
*/

if ($action == 'accept') {

    $updateOrder = $pdo->prepare("
        UPDATE customer_orders
        SET
            appointment_date = ?,
            appointment_time = ?,
            slot_status = 'accepted'
        WHERE id = ?
    ");

    $updateOrder->execute([
        $notification['suggested_date'],
        $notification['suggested_time'],
        $notification['order_id']
    ]);

    $status = 'accepted';
}

/*
|--------------------------------------------------
| REJECT SLOT
|--------------------------------------------------
*/

elseif ($action == 'reject') {

    $updateOrder = $pdo->prepare("
        UPDATE customer_orders
        SET
            slot_status = 'rejected'
        WHERE id = ?
    ");

    $updateOrder->execute([
        $notification['order_id']
    ]);

    $status = 'rejected';
}

else {

    echo json_encode([
        'success' => false
    ]);

    exit;
}

/*
|--------------------------------------------------
| UPDATE NOTIFICATION
|--------------------------------------------------
*/

$updateNotification = $pdo->prepare("
    UPDATE appointment_notifications
    SET
        status = ?,
        updated_at = ?
    WHERE id = ?
");

$updateNotification->execute([
    $status,
    date('Y-m-d H:i:s'),
    $id
]);

echo json_encode([
    'success' => true
]);