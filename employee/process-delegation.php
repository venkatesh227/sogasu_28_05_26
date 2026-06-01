<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = $_POST['order_id'];
    $employee_id = $_POST['employee_id'];

    $stmt = $pdo->prepare("UPDATE orders SET assigned_employee_id = ?, employee_taken_at = NOW() WHERE id = ?");
    if ($stmt->execute([$employee_id, $order_id])) {

    $_SESSION['delegate_success'] = "Order assigned successfully!";

    header("Location: dashboard.php");
    exit;

} else {

    $_SESSION['delegate_error'] = "Failed to assign order!";

    header("Location: dashboard.php");
    exit;
}
    exit();
}
?>
