<?php

session_start();

include '../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {

    $categoryId = $_POST['id'];

    try {

        $stmt = $pdo->prepare("
            UPDATE expense_categories
            SET deleted_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([$categoryId]);

        echo json_encode([
            'success' => true,
            'message' => 'Expense Category deleted successfully'
        ]);

    } catch (Exception $e) {

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);

    }

} else {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);

}
?>