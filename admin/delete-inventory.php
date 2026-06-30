<?php
session_start();
include '../includes/db.php';

$id = $_GET['id'] ?? null;

if ($id) {

    $check = $pdo->prepare("SELECT id FROM inventory WHERE id = ? AND is_deleted = 0");
    $check->execute([$id]);

    if ($check->fetch()) {
        $stmt = $pdo->prepare("
    UPDATE inventory 
    SET is_deleted = 1, deleted_at = NOW()
    WHERE id = ? AND is_deleted = 0
");
        $stmt->execute([$id]);

        $_SESSION['success'] = "deleted";
    }
}
header("Location: inventory.php");
exit;