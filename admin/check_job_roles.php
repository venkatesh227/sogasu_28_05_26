<?php
include '../includes/db.php';
$stmt = $pdo->query("SELECT * FROM job_roles");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
