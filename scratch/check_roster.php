<?php
include 'includes/db.php';
echo "Shift Roster Schema:\n";
$stmt = $pdo->query("DESCRIBE shift_roster");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\nSample Roster:\n";
$stmt = $pdo->query("SELECT * FROM shift_roster LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
