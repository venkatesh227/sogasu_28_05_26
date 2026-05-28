<?php
require 'includes/db.php';
$new_hash = password_hash('123456', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE mobile = ?");
$stmt->execute([$new_hash, '8500065292']);
echo "Password successfully set to 123456!";
?>
