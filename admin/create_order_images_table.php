<?php
include '../includes/db.php';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        image_type ENUM('fabric', 'sample') DEFAULT 'fabric',
        is_deleted TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Success: Order images table created.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
