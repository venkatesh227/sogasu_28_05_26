<?php
include 'includes/db.php';
$stmt = $pdo->query("SHOW TABLES LIKE '%leave%'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

$stmt = $pdo->query("DESCRIBE employees");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Employee columns: " . implode(", ", $cols) . "\n";
?>
