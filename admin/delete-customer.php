<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access");
}
$current_user_id = $_SESSION['user_id'];

$id = $_GET['id'] ?? null;

if ($id) {

    $stmt = $pdo->prepare("SELECT user_id FROM customers WHERE id = ?");
    $stmt->execute([$id]);
    $customer = $stmt->fetch();

    if ($customer) {

        try {
            $pdo->beginTransaction();

            // soft delete
            $pdo->prepare("
                UPDATE customers 
                SET is_deleted = 1, deleted_by = ?, deleted_at = NOW() 
                WHERE id = ?
            ")->execute([$current_user_id, $id]);

            // deactivate user
            $pdo->prepare("
                UPDATE users 
                SET status = 0 
                WHERE id = ?
            ")->execute([$customer['user_id']]);

            $pdo->commit();
            $_SESSION['success'] = "Customer deleted successfully!";

        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
}

header("Location: customers.php");
exit;