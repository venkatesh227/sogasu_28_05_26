<?php
include 'includes/db.php';
echo "--- JOB ROLES ---\n";
$stmt = $pdo->query("SELECT role_name, COUNT(*) as c FROM job_roles GROUP BY role_name HAVING c > 1");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
echo "--- BRANCHES ---\n";
$stmt = $pdo->query("SELECT branch_name, COUNT(*) as c FROM branches GROUP BY branch_name HAVING c > 1");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
