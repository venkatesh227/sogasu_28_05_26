<?php
include 'includes/db.php';
echo "--- EMPLOYEES ---\n";
$stmt = $pdo->query("DESCRIBE employees");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
echo "\n--- SHIFT_TYPES ---\n";
$stmt = $pdo->query("DESCRIBE shift_types");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
