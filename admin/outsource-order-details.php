<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$orderId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($orderId <= 0) {
    die("Invalid Order ID");
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $employeeId = (int) ($_POST['employee_id'] ?? 0);

    if ($employeeId > 0) {

        $check = $pdo->prepare("
            SELECT id
            FROM outsource_order_responses
            WHERE order_id = ?
            AND employee_id = ?
            AND response = 'accepted'
        ");
        $check->execute([$orderId, $employeeId]);

        if ($check->fetch()) {

            $update = $pdo->prepare("
            UPDATE outsource_orders
            SET
                assigned_employee_id = ?,
                employee_taken_at = NOW(),
                order_status = 'approved'
            WHERE id = ?
            AND assigned_employee_id IS NULL
            AND order_status = 'accepted'
        ");

            $update->execute([$employeeId, $orderId]);

            if ($update->rowCount() > 0) {
                $notify = $pdo->prepare("
                    INSERT INTO notifications
                    (employee_id, title, message, is_read, created_at)
                    VALUES (?, ?, ?, 0, NOW())
                ");

                $notify->execute([
                    $employeeId,
                    'Order Assigned',
                    'A new outsource order has been assigned to you'
                ]);
            }

            header("Location: outsource-order-details.php?id=" . $orderId);
            exit;
        }
    }
}
$stmt = $pdo->prepare("
    SELECT 
        o.*,
        c.first_name,
        c.last_name,
        e.first_name as assigned_first_name,
        e.last_name as assigned_last_name
    FROM outsource_orders o
    LEFT JOIN customers c ON c.id = o.customer_id
    LEFT JOIN employees e ON e.id = o.assigned_employee_id
    WHERE o.id = ?
    AND o.is_deleted = 0
");

$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Order not found");
}

$responseStmt = $pdo->prepare("
    SELECT 
        r.*,
        e.first_name,
        e.last_name
    FROM outsource_order_responses r
    INNER JOIN employees e ON e.id = r.employee_id
    WHERE r.order_id = ?
    AND e.employee_type = 'outsource'
    ORDER BY r.created_at ASC
");

$responseStmt->execute([$orderId]);
$responses = $responseStmt->fetchAll(PDO::FETCH_ASSOC);


?>
<!DOCTYPE html>
<html>

<head>
    <title>Outsource Order Details</title>
</head>

<body>
    <h2>Outsource Order Details</h2>

    <div>
        <p><strong>Order Code:</strong> <?= htmlspecialchars($order['order_code']) ?></p>

        <p><strong>Customer:</strong>
            <?= htmlspecialchars(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')) ?>
        </p>

        <p><strong>Status:</strong> <?= htmlspecialchars($order['order_status']) ?></p>

        <p><strong>Amount:</strong> ₹<?= number_format($order['total_amount'], 2) ?></p>

        <p><strong>Due Date:</strong> <?= htmlspecialchars($order['due_date']) ?></p>
    </div>
    <h3>Employee Responses</h3>

    <?php if (empty($responses)): ?>
        <p>No employee responses yet.</p>
    <?php else: ?>
        <table border="1" cellpadding="10" cellspacing="0">
            <tr>
                <th>Employee</th>
                <th>Response</th>
                <th>Response Time</th>
                <th>Action</th>
            </tr>

            <?php foreach ($responses as $r): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?>
                    </td>

                    <td><?= htmlspecialchars($r['response']) ?></td>

                    <td><?= htmlspecialchars($r['created_at']) ?></td>

                    <td>
                        <?php if (
                            $r['response'] === 'accepted' &&
                            empty($order['assigned_employee_id'])
                        ): ?>
                            <form method="POST">
                                <input type="hidden" name="employee_id" value="<?= $r['employee_id'] ?>">
                                <button type="submit">Assign</button>
                            </form>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
    <?php if (!empty($order['assigned_employee_id'])): ?>
        <br>
        <strong>Assigned Employee:</strong>
        <?= htmlspecialchars(
            ($order['assigned_first_name'] ?? '') . ' ' .
            ($order['assigned_last_name'] ?? '')
        ) ?>
    <?php endif; ?>
</body>

</html>