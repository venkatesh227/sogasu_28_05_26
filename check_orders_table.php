<?php
include 'includes/db.php';
$stmt = $pdo->query("DESCRIBE orders");
echo "<pre>";
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
echo "</pre>";
?>
