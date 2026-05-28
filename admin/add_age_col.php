<?php
include '../includes/db.php';
try {
    $pdo->exec("ALTER TABLE customer_family_members ADD COLUMN age INT(3) AFTER relationship");
    echo "Success: Age column added.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
