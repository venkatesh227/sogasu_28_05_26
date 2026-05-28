<?php
include 'includes/db.php';
$stmt = $pdo->query("SHOW TABLES");
echo "<pre>";
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
echo "</pre>";
?>
