<?php
include 'includes/db.php';
$stmt = $pdo->query("DESCRIBE customers");
echo "<pre>";
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
echo "</pre>";
?>
