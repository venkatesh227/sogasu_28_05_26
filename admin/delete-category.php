<?php
session_start(); // ✅ IMPORTANT

include '../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $categoryId = $_POST['id'];

    try {
        $stmt = $pdo->prepare("UPDATE categories 
        SET is_deleted=1, deleted_at=NOW(), deleted_by=? 
        WHERE id=?");

        $stmt->execute([
            $_SESSION['user_id'] ?? 1,  // ✅ correct
            $categoryId            // ✅ FIXED
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Category deleted successfully'
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