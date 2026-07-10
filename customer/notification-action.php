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
    AND status = 'pending'

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

    $pending = $_SESSION['pending_appointment'] ?? null;

    if (!$pending) {
        die('Pending appointment not found.');
    }

    $stmt = $pdo->prepare("
    SELECT username, mobile
    FROM users
    WHERE id = ?
    LIMIT 1
");

    $stmt->execute([$_SESSION['user_id']]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $insert = $pdo->prepare("
INSERT INTO appointments
(
    user_id,
    customer_name,
    customer_phone,
    category_id,
    sub_category_id,
    appointment_date,
    appointment_time,
    visit_type,
    measurement_id,
    material_image,
    referral_image,
    delivery_type,
    delivery_method,
    appointment_source,
    workflow_status,
    status,
    notes,
    created_by,
    created_at
)
VALUES
(
    :user_id,
    :customer_name,
    :customer_phone,
    :category_id,
    :sub_category_id,
    :appointment_date,
    :appointment_time,
    :visit_type,
    :measurement_id,
    :material_image,
    :referral_image,
    :delivery_type,
    :delivery_method,
    'customer',
    'pending',
    'scheduled',
    :notes,
    :created_by,
    NOW()
)
");

    $insert->execute([
        ':user_id' => $_SESSION['user_id'],
        ':customer_name' => $user['username'] ?? '',
        ':customer_phone' => $user['mobile'] ?? '',
        ':category_id' => $pending['category_id'],
        ':sub_category_id' => $pending['sub_category_id'],
        ':appointment_date' => $notification['suggested_date'],
        ':appointment_time' => $notification['suggested_time'],
        ':visit_type' => $pending['visit_type'],
        ':measurement_id' => $pending['measurement_id'],
        ':material_image' => $pending['material_image'],
        ':referral_image' => $pending['referral_image'],
        ':delivery_type' => $pending['delivery_type'],
        ':delivery_method' => $pending['delivery_method'],
        ':notes' => $pending['notes'],
        ':created_by' => $_SESSION['user_id']
    ]);

    $update = $pdo->prepare("

        UPDATE appointment_notifications
        SET status = 'accepted'
        WHERE id = ?

    ");

    $update->execute([$id]);

    unset($_SESSION['pending_appointment']);

    $_SESSION['appointment_success'] = 'Appointment created successfully!';

}

if ($action === 'reject') {

    $update = $pdo->prepare("

        UPDATE appointment_notifications
        SET status = 'rejected'
        WHERE id = ?

    ");

    $update->execute([$id]);

    unset($_SESSION['pending_appointment']);

    $_SESSION['success_message'] = 'Appointment rejected successfully';

}

header("Location: dashboard.php");

exit();