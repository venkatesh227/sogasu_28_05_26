<?php
include '../includes/db.php';
session_start();

$user_id = $_SESSION['user_id'] ?? 1;

if (isset($_GET['id'])) {

    $stmt = $pdo->prepare("
        UPDATE measurement_keys 
        SET 
            is_deleted = 1,
            deleted_at = NOW(),
            deleted_by = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $user_id,
        $_GET['id']
    ]);
}

header("Location: add-measurement-key.php?deleted=1");
exit;