<?php

session_start();

require '../includes/db.php';

$id = $_GET['id'] ?? 0;

$action = $_GET['action'] ?? '';

$stmt = $pdo->prepare("

    SELECT *
    FROM appointment_notifications
    WHERE id = ?
    AND user_id = ?

");

$stmt->execute([
    $id,
    $_SESSION['user_id']
]);

$notification = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$notification) {

    die('Notification not found');
}

if ($action === 'accept') {

    $insert = $pdo->prepare("

        INSERT INTO customer_orders (

            user_id,
            appointment_date,
            appointment_time,
            slot_status,
            created_at

        )

        VALUES (?, ?, ?, ?, NOW())

    ");

    $insert->execute([

        $_SESSION['user_id'],
        $notification['suggested_date'],
        $notification['suggested_time'],
        'confirmed'

    ]);

    $update = $pdo->prepare("

        UPDATE appointment_notifications
        SET status = 'accepted'
        WHERE id = ?

    ");

    $update->execute([$id]);

}

if ($action === 'reject') {

    $update = $pdo->prepare("

        UPDATE appointment_notifications
        SET status = 'rejected'
        WHERE id = ?

    ");

    $update->execute([$id]);

}

header("Location: dashboard.php");

exit();