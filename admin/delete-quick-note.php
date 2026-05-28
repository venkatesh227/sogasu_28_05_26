<?php
session_start();
include '../includes/db.php';

if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("UPDATE quick_notes SET is_deleted = 1 WHERE id = ?");
    $stmt->execute([$_GET['id']]);
}

header("Location: quick-notes.php");
exit;
?>
