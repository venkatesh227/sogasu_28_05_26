<?php
session_start();
include '../includes/db.php';

$id = $_GET['id'] ?? null;

if ($id) {
    $stmt = $pdo->prepare("DELETE FROM racks WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['success'] = "deleted";
}

header("Location: racks.php");
exit();
?>
