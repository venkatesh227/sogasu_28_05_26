<?php
include 'includes/db.php';
$stmt = $pdo->query("SELECT * FROM employee_payments WHERE payment_type = 'Advance'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
