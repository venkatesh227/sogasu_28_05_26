<?php
include 'includes/db.php';
$stmt = $pdo->query("SELECT * FROM pay_cycles");
print_r($stmt->fetchAll());
?>
