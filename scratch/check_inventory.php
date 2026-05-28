<?php
require_once __DIR__ . '/../includes/db.php';
$stmt = $pdo->query("SELECT id, item_name, category FROM inventory LIMIT 10");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT);
