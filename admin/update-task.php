<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || !has_permission('employees_tasks')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$action = $_POST['action'] ?? '';
$order_id = $_POST['order_id'] ?? 0;

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid Order ID']);
    exit();
}

if ($action === 'assign') {
    $emp_id = $_POST['employee_id'] ?? null;
    $stmt = $pdo->prepare("UPDATE orders SET assigned_employee_id = ?, updated_at = NOW() WHERE id = ?");
    $success = $stmt->execute([$emp_id ?: null, $order_id]);
    echo json_encode(['success' => $success]);
    exit();
}

if ($action === 'done') {
    // Fetch current status to determine next stage
    $stmt = $pdo->prepare("SELECT order_status FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $current = $stmt->fetchColumn();

    $workflow = [
        'pattern_making' => 'cutting',
        'cutting' => 'embroidery',
        'embroidery' => 'stitching',
        'stitching' => 'finishing',
        'finishing' => 'ready'
    ];

    $next = $workflow[$current] ?? 'ready';

    $stmt = $pdo->prepare("UPDATE orders SET order_status = ?, updated_at = NOW() WHERE id = ?");
    $success = $stmt->execute([$next, $order_id]);
    echo json_encode(['success' => $success, 'next_stage' => $next]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid Action']);
?>
