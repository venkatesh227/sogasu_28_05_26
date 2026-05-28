<?php
include 'includes/db.php';
$tables = ['employee_payments', 'employee_overtime'];
foreach ($tables as $table) {
    $stmt = $pdo->query("DESCRIBE $table");
    echo "<h3>$table</h3><pre>";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";
}
?>
