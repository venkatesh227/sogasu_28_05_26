<?php
include 'includes/db.php';
$stmt = $pdo->query('DESCRIBE employees');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
