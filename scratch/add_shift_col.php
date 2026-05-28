<?php
include 'includes/db.php';
try {
    $pdo->exec("ALTER TABLE employees ADD COLUMN default_shift_id int(11) DEFAULT NULL AFTER branch");
    echo "Column 'default_shift_id' added successfully.";
} catch (PDOException $e) {
    if ($e->getCode() == '42S21') {
        echo "Column already exists.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
