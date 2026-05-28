<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

$issue_id = $_POST['id'] ?? null;
if (!$issue_id) {
    die(json_encode(['status' => 'error', 'message' => 'No ID provided']));
}

try {
    $stmt = $pdo->prepare("UPDATE order_issues SET status = 'resolved' WHERE id = ?");
    $stmt->execute([$issue_id]);
    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
