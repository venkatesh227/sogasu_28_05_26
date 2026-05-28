<?php
include '../includes/db.php';
$stmt = $pdo->query("SELECT * FROM users");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
