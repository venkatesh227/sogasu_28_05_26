<?php
include '../includes/db.php';
try {
    $stmt = $pdo->query("DESCRIBE orders");
    print_r($stmt->fetchAll());
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
