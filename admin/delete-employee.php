<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access");
}

$current_user_id = $_SESSION['user_id'];

$id = $_GET['id'] ?? null;

if ($id) {

    $stmt = $pdo->prepare("SELECT user_id FROM employees WHERE id = ? AND is_deleted = 0");
    $stmt->execute([$id]);
    $employee = $stmt->fetch();

    if ($employee) {

        try {
            $pdo->beginTransaction();

            // existing functionality (keep this)
            if (!empty($employee['user_id'])) {
                $stmt = $pdo->prepare("UPDATE users SET status = 0 WHERE id = ?");
                $stmt->execute([$employee['user_id']]);
            }

            // UPDATED soft delete
            $pdo->prepare("
                UPDATE employees 
                SET is_deleted = 1, deleted_by = ?, deleted_at = NOW() 
                WHERE id = ?
            ")->execute([$current_user_id, $id]);

            $pdo->commit();

            $_SESSION['success'] = "deleted";

        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
}

$redirect = $_GET['redirect'] ?? 'payroll';
header("Location: $redirect.php");
exit;