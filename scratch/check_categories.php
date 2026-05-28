<?php
include __DIR__ . '/../includes/db.php';
echo "--- inventory_categories ---\n";
$stmt = $pdo->query("SELECT * FROM inventory_categories");
foreach ($stmt->fetchAll() as $row) {
    print_r($row);
}

echo "\n--- Unique categories in inventory table ---\n";
$stmt = $pdo->query("SELECT category, COUNT(*) as count FROM inventory GROUP BY category");
foreach ($stmt->fetchAll() as $row) {
    print_r($row);
}
