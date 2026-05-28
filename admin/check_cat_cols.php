<?php
include '../includes/db.php';
$stmt = $pdo->query('DESCRIBE categories');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt2 = $pdo->query('DESCRIBE sub_categories');
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
?>
