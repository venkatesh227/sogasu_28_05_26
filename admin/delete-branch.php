<?php
session_start();
include '../includes/db.php';

$id = $_GET['id'] ?? null;

if ($id) {

    $stmt = $pdo->prepare("SELECT id FROM branches WHERE id = ? AND is_deleted = 0");
    $stmt->execute([$id]);
    $branch = $stmt->fetch();

    if ($branch) {

        // soft delete using deleted_at
        $pdo->prepare("UPDATE branches SET is_deleted = 1, deleted_at = NOW(), deleted_by = ? WHERE id = ?")
    ->execute([$_SESSION['user_id'] ?? null, $id]);

        $_SESSION['success'] = "deleted";
    }
}

header("Location: branches.php");
exit;