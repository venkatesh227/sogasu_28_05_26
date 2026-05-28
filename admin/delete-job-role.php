<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}

$id = $_GET['id'] ?? null;

if ($id) {
    // Soft delete
    $stmt = $pdo->prepare("UPDATE job_roles SET is_deleted = 1 WHERE id = ?");
    if ($stmt->execute([$id])) {
        $_SESSION['success'] = "deleted";
    }
}

header("Location: job-roles.php");
exit;
?>
