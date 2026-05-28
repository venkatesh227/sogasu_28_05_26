<?php
include 'includes/db.php';
$stmt = $pdo->query("DESCRIBE attendance");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
