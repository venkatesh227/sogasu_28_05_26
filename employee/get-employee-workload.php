<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Unauthorized']));
}

$employee_id = $_GET['id'] ?? null;

if (!$employee_id) {
    die(json_encode(['error' => 'No ID']));
}

// Fetch Pending Tasks count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE assigned_employee_id = ? AND order_status != 'delivered' AND is_deleted = 0");
$stmt->execute([$employee_id]);
$pending_count = $stmt->fetchColumn();

// Fetch soonest deadline
$stmt = $pdo->prepare("SELECT MIN(due_date) FROM orders WHERE assigned_employee_id = ? AND order_status != 'delivered' AND is_deleted = 0");
$stmt->execute([$employee_id]);
$next_deadline = $stmt->fetchColumn();

// Determine Load Status
$status = 'Available';
$color = '#10b981'; // Green
if ($pending_count >= 5) {
    $status = 'Very Busy';
    $color = '#ef4444'; // Red
} elseif ($pending_count >= 3) {
    $status = 'Busy';
    $color = '#f59e0b'; // Orange
}

echo json_encode([
    'pending_tasks' => $pending_count,
    'next_deadline' => $next_deadline ? date('d M', strtotime($next_deadline)) : 'None',
    'status' => $status,
    'color' => $color
]);
