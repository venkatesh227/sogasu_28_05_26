<?php
include 'includes/db.php';
try {
    $pdo->exec(file_get_contents('create_notifications.sql'));
    echo "Success: notifications table created.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
