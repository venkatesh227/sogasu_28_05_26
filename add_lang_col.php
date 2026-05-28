<?php
include 'includes/db.php';
try {
    $pdo->exec("ALTER TABLE employees ADD COLUMN preferred_language VARCHAR(10) DEFAULT 'en' AFTER phone");
    echo "Success: preferred_language column added.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
