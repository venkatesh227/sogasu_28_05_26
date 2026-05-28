<?php
require_once __DIR__ . '/../includes/db.php';

try {
    // Check if column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM orders LIKE 'advance_payment_mode'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN advance_payment_mode VARCHAR(50) DEFAULT NULL AFTER advance_paid");
        echo "Successfully added 'advance_payment_mode' column to 'orders' table.\n";
    } else {
        echo "Column 'advance_payment_mode' already exists in 'orders' table.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
