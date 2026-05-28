<?php
include 'includes/db.php';
try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN is_notified TINYINT(1) DEFAULT 0");
    echo "Success: is_notified column added.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
