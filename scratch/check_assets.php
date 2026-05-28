<?php
require_once 'includes/db.php';
$stmt = $pdo->query("DESCRIBE assets");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
