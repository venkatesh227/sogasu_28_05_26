<?php
$host = 'localhost';
$dbname = 'sogasu_28';
$u = 'root';
$p = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $u, $p);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    foreach (array('appointments','customer_measurements') as $table) {
        echo "TABLE $table\n";
        foreach ($pdo->query('SHOW COLUMNS FROM ' . $table) as $col) {
            echo $col['Field'] . ' ' . $col['Type'] . ' ' . ($col['Null'] == 'NO' ? 'NOT NULL' : 'NULL') . ' ' . ($col['Key'] ? $col['Key'] : '') . ' ' . ($col['Extra'] ? 'EXTRA=' . $col['Extra'] : '') . "\n";
        }
        echo "\n";
    }
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage();
}
