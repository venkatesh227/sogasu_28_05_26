<?php
include '../includes/db.php';
$stmt = $pdo->query('DESCRIBE orders');
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($cols as $col) {
    echo $col['Field'] . " - " . $col['Type'] . "\n";
}
?>
