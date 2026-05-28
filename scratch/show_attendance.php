<?php
include 'includes/db.php';
$stmt = $pdo->query("SHOW CREATE TABLE attendance");
print_r($stmt->fetch());
?>
