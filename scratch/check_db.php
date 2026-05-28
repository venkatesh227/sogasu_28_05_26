<?php
include 'includes/db.php';
$tables = ['employee_overtime', 'orders', 'leave_requests'];
foreach ($tables as $t) {
    echo "Table: $t\n";
    try {
        $stmt = $pdo->query("DESCRIBE $t");
        print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
