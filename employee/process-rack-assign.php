<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = $_POST['order_id'];
    $rack_id = $_POST['rack_id'];

    try {
        $pdo->beginTransaction();

        // Update order with rack_id
        $stmt = $pdo->prepare("UPDATE orders SET rack_id = ? WHERE id = ?");
        $stmt->execute([$rack_id, $order_id]);

        // Update rack status to Occupied
        $stmt = $pdo->prepare("UPDATE racks SET status = 'Occupied' WHERE id = ?");
        $stmt->execute([$rack_id]);

$pdo->commit();

$_SESSION['rack_success'] = "Rack assigned successfully!";

header("Location: dashboard.php");
exit;
    } catch (Exception $e) {
        $pdo->rollBack();
$_SESSION['rack_error'] = "Failed to assign rack!";

header("Location: dashboard.php");
exit;    }
    exit();
}
?>
