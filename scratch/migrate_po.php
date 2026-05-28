<?php
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->beginTransaction();

    // 1. Create purchase_orders table
    $sql1 = "CREATE TABLE IF NOT EXISTS `purchase_orders` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `po_number` VARCHAR(50) NOT NULL UNIQUE,
      `supplier_id` INT NOT NULL,
      `order_date` DATE NOT NULL,
      `delivery_date` DATE DEFAULT NULL,
      `status` ENUM('Pending', 'Received', 'Cancelled') DEFAULT 'Pending',
      `total_amount` DECIMAL(10,2) DEFAULT 0.00,
      `notes` TEXT DEFAULT NULL,
      `invoice_no` VARCHAR(100) DEFAULT NULL,
      `invoice_date` DATE DEFAULT NULL,
      `invoice_file` VARCHAR(255) DEFAULT NULL,
      `is_deleted` TINYINT(1) DEFAULT 0,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX (`supplier_id`),
      INDEX (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql1);
    echo "Table 'purchase_orders' verified/created.\n";

    // 2. Create purchase_order_items table
    $sql2 = "CREATE TABLE IF NOT EXISTS `purchase_order_items` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `purchase_order_id` INT NOT NULL,
      `item_name` VARCHAR(255) NOT NULL,
      `sku` VARCHAR(100) DEFAULT NULL,
      `category` VARCHAR(100) NOT NULL,
      `quantity` DECIMAL(10,2) NOT NULL,
      `unit` VARCHAR(50) NOT NULL,
      `cost` DECIMAL(10,2) NOT NULL,
      `received_quantity` DECIMAL(10,2) DEFAULT 0.00,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX (`purchase_order_id`),
      INDEX (`sku`),
      CONSTRAINT `fk_po_items_po_id` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql2);
    echo "Table 'purchase_order_items' verified/created.\n";

    $pdo->commit();
    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
