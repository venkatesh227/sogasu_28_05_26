<?php
include 'includes/db.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ot_rate_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        from_date DATE NOT NULL,
        to_date DATE NOT NULL,
        hourly_rate DECIMAL(10,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    echo "OT Rate Settings table initialized.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
