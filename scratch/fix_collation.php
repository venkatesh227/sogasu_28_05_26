<?php
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->beginTransaction();

    // Convert purchase_orders table character set and collation to match standard utf8mb4_general_ci
    $sql1 = "ALTER TABLE `purchase_orders` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;";
    $pdo->exec($sql1);
    echo "Converted 'purchase_orders' table collation to utf8mb4_general_ci.\n";

    // Convert purchase_order_items table character set and collation to match standard utf8mb4_general_ci
    $sql2 = "ALTER TABLE `purchase_order_items` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;";
    $pdo->exec($sql2);
    echo "Converted 'purchase_order_items' table collation to utf8mb4_general_ci.\n";

    $pdo->commit();
    echo "Collation fix migration completed successfully!\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
