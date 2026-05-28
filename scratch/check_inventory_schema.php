<?php
require_once __DIR__ . '/../includes/db.php';

$tables = [
    'inventory',
    'inventory_categories',
    'inventory_issues',
    'inventory_invoices',
    'inventory_stock_issues',
    'stock',
    'stock_issues'
];

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SHOW CREATE TABLE $table");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        echo "=== $table ===\n";
        echo $row[1] . "\n\n";
    } catch (Exception $e) {
        echo "=== $table === (Not found or error: " . $e->getMessage() . ")\n\n";
    }
}
