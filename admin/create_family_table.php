<?php
include '../includes/db.php';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS customer_family_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        member_name VARCHAR(100) NOT NULL,
        relationship VARCHAR(50),
        phone VARCHAR(20),
        notes TEXT,
        is_deleted TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
    )");
    echo "Success: Family members table created.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
