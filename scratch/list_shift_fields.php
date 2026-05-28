<?php
include 'includes/db.php';
$stmt = $pdo->query("DESCRIBE shift_types");
foreach($stmt->fetchAll() as $row) {
    echo $row['Field'] . "\n";
}
?>
