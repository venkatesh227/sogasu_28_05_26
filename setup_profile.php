<?php
// This file adds missing columns to employees table and creates notifications table
require 'includes/db.php';

try {
    // Add columns to employees table if they don't exist
    $columns_to_add = [
        "profile_photo VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER status",
        "notification_enabled TINYINT(1) DEFAULT 1 AFTER profile_photo",
        "preferred_language VARCHAR(10) DEFAULT 'en' AFTER notification_enabled"
    ];
    
    foreach ($columns_to_add as $column_sql) {
        $check_sql = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_NAME='employees' AND COLUMN_NAME='" . explode(" ", $column_sql)[0] . "'";
        $result = $pdo->query($check_sql)->fetch();
        if (!$result) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN $column_sql");
            echo "✓ Added column to employees table<br>";
        }
    }
    
    // Create notifications table if it doesn't exist
    $create_notif_table = "CREATE TABLE IF NOT EXISTS notifications (
        id INT(11) PRIMARY KEY AUTO_INCREMENT,
        employee_id INT(11) NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT,
        type VARCHAR(50) DEFAULT 'admin_update',
        is_read TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    )";
    
    $pdo->exec($create_notif_table);
    echo "✓ Notifications table created/verified<br>";
    
    echo "<br><strong>Database setup completed successfully!</strong><br>";
    echo "You can now <a href='employee/profile.php'>visit the profile page</a>";
    
} catch (PDOException $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
}
?>
