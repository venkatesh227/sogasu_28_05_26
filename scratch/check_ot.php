<?php
include 'includes/db.php';
echo "OT Rate Settings:\n";
$stmt = $pdo->query("SELECT * FROM ot_rate_settings");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\nGlobal OT Rate:\n";
$stmt = $pdo->query("SELECT * FROM global_settings WHERE setting_key = 'global_ot_rate'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\nOT Requests:\n";
$stmt = $pdo->query("SELECT * FROM employee_overtime ORDER BY id DESC LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
