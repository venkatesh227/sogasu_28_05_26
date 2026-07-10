<?php

session_start();
header('Content-Type: application/json');

require '../includes/db.php';

$id = $_POST['id'] ?? 0;

$action = $_POST['action'] ?? '';

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

$notification = $stmt->fetch();

if (!$notification) {

    echo json_encode([
        'success' => false,
        'message' => 'Notification not found.'
    ]);

    exit;
}

/*
|--------------------------------------------------
| ACCEPT SLOT
|--------------------------------------------------
*/

if ($action == 'accept') {

    $pending = $_SESSION['pending_appointment'] ?? null;

    if (!$pending) {
        echo json_encode([
            'success' => false,
            'message' => 'Pending appointment not found.'
        ]);
        exit;
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

    unset($_SESSION['pending_appointment']);

    $_SESSION['appointment_success'] = 'Appointment created successfully!';

    $status = 'accepted';
}

/*
|--------------------------------------------------
| REJECT SLOT
|--------------------------------------------------
*/ elseif ($action == 'reject') {

    unset($_SESSION['pending_appointment']);

    $status = 'rejected';
} else {

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
    'success' => true,
    'redirect' => 'dashboard.php'
]);