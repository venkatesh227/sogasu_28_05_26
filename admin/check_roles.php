<?php
include '../includes/db.php';
$stmt = $pdo->query("SELECT DISTINCT role FROM users");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
?>
