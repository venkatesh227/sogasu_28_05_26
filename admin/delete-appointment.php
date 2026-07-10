<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access");
}

$current_user_id = $_SESSION['user_id'];
$id = $_GET['id'] ?? null;

if ($id) {

    $stmt = $pdo->prepare("SELECT id FROM appointments WHERE id=? AND is_deleted=0");
    $stmt->execute([$id]);
    $appointment = $stmt->fetch();

    if ($appointment) {

        $pdo->prepare("
            UPDATE appointments 
            SET is_deleted = 1, deleted_by = ?, deleted_at = NOW()
            WHERE id = ?
        ")->execute([$current_user_id, $id]);

        $_SESSION['success'] = "Appointment deleted successfully";
    }
}

header("Location: appointments.php");
exit;