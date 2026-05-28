<?php
require_once __DIR__ . '/../includes/db.php';

$stmt = $pdo->query("SHOW TABLES LIKE '%stock%'");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->query("SHOW TABLES LIKE '%inv%'");
$tables2 = $stmt->fetchAll(PDO::FETCH_COLUMN);

$tables = array_unique(array_merge($tables, $tables2));

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        echo "=== $table ===\n";
        echo $row[1] . "\n\n";
    } catch (Exception $e) {
        echo "=== $table === (Not found or error: " . $e->getMessage() . ")\n\n";
    }
}
