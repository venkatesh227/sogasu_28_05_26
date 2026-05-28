<?php
include 'includes/db.php';
try {
    $pdo->exec(file_get_contents('create_order_issues.sql'));
    echo "Success: order_issues table created.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
