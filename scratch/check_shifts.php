<?php
include 'includes/db.php';
echo "Shift Types Schema:\n";
$stmt = $pdo->query("DESCRIBE shift_types");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\nShift Types:\n";
$stmt = $pdo->query("SELECT * FROM shift_types");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
