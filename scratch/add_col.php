<?php
require 'includes/db.php';
try {
    $pdo->exec("ALTER TABLE sub_categories ADD COLUMN preparation_days int(11) DEFAULT 0 AFTER price");
    echo "Column 'preparation_days' added successfully to 'sub_categories' table.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
