<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id FROM employees WHERE user_id = ?");
$stmt->execute([$user_id]);
$emp = $stmt->fetch();

if (!$emp) {
    die(json_encode(['status' => 'error', 'message' => 'Employee not found']));
}

$employee_id = $emp['id'];
$order_id = $_POST['order_id'];
$issue_type = $_POST['issue_type'];
$description = $_POST['description'];

try {
    $stmt = $pdo->prepare("INSERT INTO order_issues (order_id, employee_id, issue_type, description) VALUES (?, ?, ?, ?)");
    $stmt->execute([$order_id, $employee_id, $issue_type, $description]);

    // Optional: Notify supervisor via a notifications table if it exists
    // $pdo->prepare("INSERT INTO notifications ...")->execute([...]);

    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
