<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || !has_permission('employees_tasks')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$action = $_POST['action'] ?? '';
$order_id = $_POST['order_id'] ?? 0;
$source = $_POST['source'] ?? 'orders';

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid Order ID']);
    exit();
}

switch ($source) {

    case 'orders':
        $table = 'orders';
        $statusColumn = 'order_status';
        break;

    case 'customer_orders':
        $table = 'customer_orders';
        $statusColumn = 'status';
        break;

    case 'outsource_orders':
        $table = 'outsource_orders';
        $statusColumn = 'order_status';
        break;

    default:
        echo json_encode([
            'success' => false,
            'message' => 'Invalid Source'
        ]);
        exit();
}

if ($action === 'assign') {
    $emp_id = $_POST['employee_id'] ?? null;
    $stmt = $pdo->prepare("
    UPDATE {$table}
    SET assigned_employee_id = ?, updated_at = NOW()
    WHERE id = ?
    ");
    $success = $stmt->execute([$emp_id ?: null, $order_id]);
    echo json_encode(['success' => $success]);
    exit();
}

if ($action === 'done') {
    // Fetch current status to determine next stage
    $stmt = $pdo->prepare("
    SELECT {$statusColumn}
    FROM {$table}
    WHERE id = ?
    ");
    $stmt->execute([$order_id]);
    $current = $stmt->fetchColumn();

    if ($source == 'orders' || $source == 'customer_orders') {

        $workflow = [
            'pending' => 'processing',
            'processing' => 'pattern_making',
            'pattern_making' => 'cutting',
            'cutting' => 'embroidery',
            'embroidery' => 'stitching',
            'stitching' => 'finishing',
            'finishing' => 'ready',
            'ready' => 'completed',
            'completed' => 'delivered'
        ];

    } elseif ($source == 'outsource_orders') {

        $workflow = [
            'pending' => 'accepted',
            'accepted' => 'approved',
            'approved' => 'in progress',
            'in progress' => 'completed'
        ];
    }

    $next = $workflow[$current] ?? $current;

    $stmt = $pdo->prepare("
    UPDATE {$table}
    SET {$statusColumn} = ?, updated_at = NOW()
    WHERE id = ?
    ");
    $success = $stmt->execute([$next, $order_id]);
    echo json_encode(['success' => $success, 'next_stage' => $next]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid Action']);
?>