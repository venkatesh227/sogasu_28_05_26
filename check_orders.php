<?php
include 'includes/db.php';
$stmt = $pdo->query("SELECT id, order_code, order_status, assigned_employee_id FROM orders LIMIT 10");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
