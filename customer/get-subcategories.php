<?php
require '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_GET['category_id']) || empty($_GET['category_id'])) {
    echo json_encode([]);
    exit();
}

$categoryId = $_GET['category_id'];

try {
    $stmt = $pdo->prepare("SELECT id, name, price as base_price FROM sub_categories WHERE category_id = ? AND status = 'active' AND is_deleted = 0 ORDER BY name");
    $stmt->execute([$categoryId]);
    $subCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($subCategories);
} catch (Exception $e) {
    echo json_encode(['error' => 'Failed to load sub-categories']);
}
?>