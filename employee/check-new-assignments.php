<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['new_orders' => 0]));
}

// Get the employee_id for the logged-in user
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("
    SELECT id, job_role
    FROM employees
    WHERE user_id = ?
");
$stmt->execute([$user_id]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$emp) {
    die(json_encode(['new_orders' => 0]));
}

$employee_id = $emp['id'];
$job_role = $emp['job_role'];
// Check for unnotified orders
$stmt = $pdo->prepare("SELECT id, order_code FROM orders WHERE assigned_employee_id = ? AND is_notified = 0 AND is_deleted = 0");
$stmt->execute([$employee_id]);
$new_orders = $stmt->fetchAll();

// Check for processed shift requests
$stmt = $pdo->prepare("SELECT id, status, request_date FROM shift_requests WHERE employee_id = ? AND is_notified = 0");
$stmt->execute([$employee_id]);
$shift_notifs = $stmt->fetchAll();
// Check appointment notifications
if ($job_role == 'Supervisor') {

    $stmt = $pdo->prepare("
    SELECT id, title, message
    FROM appointment_notifications
    WHERE user_id = ?
    AND notification_type = 'supervisor_assignment'
    AND status = 'pending'
    ORDER BY id DESC
    LIMIT 1
");

    $stmt->execute([$user_id]);

} else {

    $stmt = $pdo->prepare("
        SELECT id, title, message
        FROM notifications
        WHERE employee_id = ?
        AND is_read = 0
        ORDER BY id ASC
        LIMIT 1
    ");

    $stmt->execute([$employee_id]);

}

$appointment_notification = $stmt->fetch(PDO::FETCH_ASSOC);

$response = [
    'new_orders' => count($new_orders),
    'shift_updates' => count($shift_notifs),
    'appointment_notifications' => 0
];

if (!empty($new_orders)) {
    // Mark as notified
    $ids = array_column($new_orders, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $updateStmt = $pdo->prepare("UPDATE orders SET is_notified = 1 WHERE id IN ($placeholders)");
    $updateStmt->execute($ids);

    $response['codes'] = array_column($new_orders, 'order_code');
    $response['order_message'] = "You have " . count($new_orders) . " new task(s) assigned.";
}

if (!empty($shift_notifs)) {
    // Mark as notified
    $ids = array_column($shift_notifs, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $updateStmt = $pdo->prepare("UPDATE shift_requests SET is_notified = 1 WHERE id IN ($placeholders)");
    $updateStmt->execute($ids);

    $msg = "Your shift request for " . date('d M', strtotime($shift_notifs[0]['request_date'])) . " has been " . $shift_notifs[0]['status'] . ".";
    if (count($shift_notifs) > 1) {
        $msg = "You have " . count($shift_notifs) . " shift request updates.";
    }
    $response['shift_message'] = $msg;
    $response['shift_status'] = $shift_notifs[0]['status'];
}
if ($appointment_notification) {

    $response['appointment_notifications'] = 1;
    $response['appointment_message'] = $appointment_notification['message'];
    $response['appointment_title'] = $appointment_notification['title'];

    if ($job_role == 'Supervisor') {

        $stmt = $pdo->prepare("
        UPDATE appointment_notifications
        SET status = 'read'
        WHERE id = ?
    ");

    } else {

        $stmt = $pdo->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE id = ?
    ");

    }

    $stmt->execute([$appointment_notification['id']]);

}

echo json_encode($response);
