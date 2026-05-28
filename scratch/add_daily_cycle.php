<?php
include 'includes/db.php';
try {
    $stmt = $pdo->prepare("INSERT INTO pay_cycles (cycle_name, status) VALUES ('Daily', 'active')");
    $stmt->execute();
    echo "Daily pay cycle added successfully";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
