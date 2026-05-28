<?php
include 'includes/db.php';
$stmt = $pdo->query("DESCRIBE attendance");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt2 = $pdo->query("DESCRIBE attendance_logs");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
