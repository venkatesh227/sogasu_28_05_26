<?php
require 'includes/db.php';
$stmt = $pdo->query('DESCRIBE sub_categories');
echo "Columns in sub_categories:\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
