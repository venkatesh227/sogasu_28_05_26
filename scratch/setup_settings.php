<?php
include 'includes/db.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS global_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(50) UNIQUE NOT NULL,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // Seed default OT rate if missing
    $pdo->exec("INSERT IGNORE INTO global_settings (setting_key, setting_value) VALUES ('global_ot_rate', '100')");

    echo "Settings table initialized.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
