<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

$order_id = $_POST['order_id'] ?? null;
$status = $_POST['status'] ?? null;

if (!$order_id || !$status) {
    die(json_encode(['status' => 'error', 'message' => 'Missing data']));
}

try {
    $pdo->beginTransaction();

    // 1. Fetch current rack_id of the order before we update status        
    $stmt_fetch = $pdo->prepare("
    SELECT rack_id, status_history
    FROM orders
    WHERE id = ?
");

    $stmt_fetch->execute([$order_id]);
    $order_data = $stmt_fetch->fetch();

    $is_customer_order = false;

    if (!$order_data) {

        $stmt_fetch = $pdo->prepare("
        SELECT rack_id, status_history
        FROM customer_orders
        WHERE id = ?
    ");

        $stmt_fetch->execute([$order_id]);
        $order_data = $stmt_fetch->fetch();

        $is_customer_order = true;
    }

    $current_rack_id = $order_data['rack_id'] ?? null;
    $existingHistory = $order_data['status_history'] ?? '';

    $historyArray = [];

    if (!empty($existingHistory)) {

        $historyArray = explode(',', $existingHistory);

    }

    if (!in_array($status, $historyArray)) {

        $historyArray[] = $status;

    }

    $newHistory = implode(',', $historyArray);

    // 2. Update order status                      
    if (!$is_customer_order) {

        $stmt = $pdo->prepare("
        UPDATE orders 
        SET order_status = ?, 
            status_history = ?,
            updated_at = NOW() 
        WHERE id = ?
    ");

        $stmt->execute([$status, $newHistory, $order_id]);

    } else {

        $stmt = $pdo->prepare("
        UPDATE customer_orders 
        SET status = ?, 
            status_history = ?,
            updated_at = NOW() 
        WHERE id = ?
    ");

        $stmt->execute([$status, $newHistory, $order_id]);
    }

    // 3. If order has an assigned rack and status is updated to anything other than 'pending' (started)
    if ($current_rack_id && $status !== 'pending') {
        
        // Free the rack in the racks table
        $stmt_rack = $pdo->prepare("UPDATE racks SET status = 'Available' WHERE id = ?");
        $stmt_rack->execute([$current_rack_id]);

        // Clear the rack_id from the order since materials have been collected
        if (!$is_customer_order) {

            $stmt_clear_order = $pdo->prepare("
        UPDATE orders 
        SET rack_id = NULL 
        WHERE id = ?
    ");

        } else {

            $stmt_clear_order = $pdo->prepare("
        UPDATE customer_orders 
        SET rack_id = NULL 
        WHERE id = ?
    ");
        }

        $stmt_clear_order->execute([$order_id]);
    }

    // Auto-resolve any open issues for this order when an update is  made to its status
    $stmt = $pdo->prepare("UPDATE order_issues SET status = 'resolved' WHERE order_id = ? AND status = 'open'");
    $stmt->execute([$order_id]);

    $pdo->commit();
    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
