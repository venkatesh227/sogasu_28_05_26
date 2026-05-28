<?php
include '../includes/db.php';
$stmt = $pdo->query("SHOW TABLES LIKE 'orders'");
print_r($stmt->fetch());
?>
