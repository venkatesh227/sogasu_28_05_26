<?php
require 'includes/db.php';
$stmt = $pdo->query("SELECT u.id, u.username, u.mobile, e.job_role FROM users u JOIN employees e ON u.id = e.user_id WHERE e.job_role = 'Supervisor'");
print_r($stmt->fetchAll());
?>
