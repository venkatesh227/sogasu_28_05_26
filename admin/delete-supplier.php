<?php
session_start();
include '../includes/db.php';

$id = $_GET['id'] ?? null;

if ($id) {
    $stmt = $pdo->prepare("UPDATE suppliers SET is_deleted = 1 WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['success'] = "deleted";
}

header("Location: suppliers.php");
exit;
