<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$id = $_GET['id'] ?? null;

if ($id) {
    // Soft delete the service
    $stmt = $pdo->prepare("UPDATE services SET is_deleted = 1 WHERE id = ?");
    $stmt->execute([$id]);
}

header("Location: services-pricing.php?deleted=1");
exit();
