<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || !has_permission('inventory')) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Optional: Delete files from the server
    $stmt = $pdo->prepare("SELECT reference_image, attachment_file FROM sourcing WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        if (!empty($row['reference_image']) && file_exists('../' . $row['reference_image'])) {
            unlink('../' . $row['reference_image']);
        }
        if (!empty($row['attachment_file']) && file_exists('../' . $row['attachment_file'])) {
            unlink('../' . $row['attachment_file']);
        }
    }

    $stmt = $pdo->prepare("DELETE FROM sourcing WHERE id = ?");
    if ($stmt->execute([$id])) {
        header("Location: sourcing.php?success=deleted");
    } else {
        header("Location: sourcing.php?error=failed");
    }
    exit();
}
header("Location: sourcing.php");
exit();
