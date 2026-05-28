<?php
require 'includes/db.php';

$sql = file_get_contents('supervisor_workflow_update.sql');

try {
    $pdo->exec($sql);
    echo "Database updated successfully!";
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage();
}
?>
