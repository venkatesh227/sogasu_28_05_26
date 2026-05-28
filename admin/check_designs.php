<?php
include '../includes/db.php';
$stmt = $pdo->query('DESCRIBE designs');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
