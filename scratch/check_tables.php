<?php
require_once __DIR__ . '/../includes/db.php';

$tables = ['inventory', 'suppliers', 'procurement', 'sourcing'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("DESCRIBE `$table`");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "=== $table ===\n";
        foreach ($cols as $col) {
            echo "  {$col['Field']} - {$col['Type']} - Null: {$col['Null']} - Key: {$col['Key']} - Default: {$col['Default']}\n";
        }
        echo "\n";
    } catch (Exception $e) {
        echo "=== $table === (Error: " . $e->getMessage() . ")\n\n";
    }
}
