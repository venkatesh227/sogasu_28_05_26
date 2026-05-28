<?php
include 'includes/db.php';
$sql = "CREATE TABLE IF NOT EXISTS inventory (
    id INT AUTO_INCREMENT PRIMARY KEY, 
    item_name VARCHAR(255) NOT NULL, 
    sku VARCHAR(100), 
    category VARCHAR(100), 
    quantity DECIMAL(10,2) DEFAULT 0, 
    unit VARCHAR(50), 
    cost DECIMAL(10,2) DEFAULT 0, 
    status TINYINT(1) DEFAULT 1, 
    is_deleted TINYINT(1) DEFAULT 0, 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
try {
    $pdo->exec($sql);
    echo "Table 'inventory' created successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
