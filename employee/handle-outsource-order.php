<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

/* Get employee */
$stmt = $pdo->prepare("
    SELECT id, employee_type
    FROM employees
    WHERE user_id = ?
    AND is_deleted = 0
");
$stmt->execute([$user_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee || $employee['employee_type'] !== 'outsource') {
    header("Location: dashboard.php");
    exit;
}

$employee_id = $employee['id'];

if (!isset($_GET['id']) || !isset($_GET['action'])) {
    header("Location: outsourcing_dashboard.php");
    exit;
}

$order_id = (int) $_GET['id'];
$action = $_GET['action'];

if (!in_array($action, ['accept', 'reject'])) {
    header("Location: outsourcing_dashboard.php");
    exit;
}

/* Check order exists */
$stmt = $pdo->prepare("
    SELECT id, order_status
    FROM outsource_orders
    WHERE id = ?
    AND is_deleted = 0
");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: outsourcing_dashboard.php?error=invalid_order");
    exit;
}

/* Order already finalized */
if (!in_array($order['order_status'], ['pending'])) {
    header("Location: outsourcing_dashboard.php?error=already_processed");
    exit;
}

/* Check already responded */
$stmt = $pdo->prepare("
    SELECT id
    FROM outsource_order_responses
    WHERE order_id = ?
    AND employee_id = ?
");
$stmt->execute([$order_id, $employee_id]);

if ($stmt->fetch()) {
    header("Location: outsourcing_dashboard.php?error=already_responded");
    exit;
}

/* ACCEPT */
if ($action === 'accept') {

    $stmt = $pdo->prepare("
        INSERT INTO outsource_order_responses
        (order_id, employee_id, response, created_at)
        VALUES (?, ?, 'accepted', ?)
    ");

    $stmt->execute([
        $order_id,
        $employee_id,
        date('Y-m-d H:i:s')
    ]);

    $stmt = $pdo->prepare("
    UPDATE outsource_orders
    SET order_status = 'accepted'
    WHERE id = ?
    AND order_status = 'pending'
");

    $success = $stmt->execute([
        $order_id
    ]);

    if ($success && $stmt->rowCount() > 0) {
        header("Location: outsourcing_dashboard.php?success=accepted");
    } else {
        header("Location: outsourcing_dashboard.php?error=accept_failed");
    }
    exit;
}

/* REJECT */
if ($action === 'reject') {

    $stmt = $pdo->prepare("
        INSERT INTO outsource_order_responses
        (order_id, employee_id, response, created_at)
        VALUES (?, ?, 'rejected', ?)
    ");

    $stmt->execute([
        $order_id,
        $employee_id,
        date('Y-m-d H:i:s')
    ]);

    /* Total outsource employees */
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM employees
        WHERE employee_type = 'outsource'
        AND is_deleted = 0
    ");
    $stmt->execute();
    $total_outsource = $stmt->fetchColumn();

    /* Total rejects for this order */
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM outsource_order_responses
        WHERE order_id = ?
        AND response = 'rejected'
    ");
    $stmt->execute([$order_id]);
    $reject_count = $stmt->fetchColumn();

    /* Reject only if all rejected */
    if ($reject_count >= $total_outsource) {

        $stmt = $pdo->prepare("
            UPDATE outsource_orders
            SET order_status = 'rejected'
            WHERE id = ?
        ");
        $stmt->execute([$order_id]);

        header("Location: outsourcing_dashboard.php?success=rejected");
        exit;
    }

    /* Still pending */
    header("Location: outsourcing_dashboard.php?success=response_saved");
    exit;
}
?>