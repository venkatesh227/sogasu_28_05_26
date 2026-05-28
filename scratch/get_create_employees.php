<?php
include 'includes/db.php';
$stmt = $pdo->query("SHOW CREATE TABLE employees");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo $row['Create Table'];
?>
