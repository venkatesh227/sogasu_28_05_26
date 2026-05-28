<?php
include '../includes/db.php';
session_start();

$user_id = $_SESSION['user_id'] ?? 1;

if (isset($_GET['id'])) {

    $stmt = $pdo->prepare("UPDATE sub_categories 
    SET is_deleted=1, deleted_at=?, deleted_by=? 
    WHERE id=?");

    $stmt->execute([
        date("Y-m-d H:i:s"),
        $user_id,
        $_GET['id']
    ]);

    header("Location: sub-categories.php?deleted=1");
    exit;
}