<?php
include '../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id']) && isset($_POST['status'])) {
    $categoryId = $_POST['id'];
    $newStatus = $_POST['status'];
    
    try {
        $stmt = $pdo->prepare("UPDATE categories SET status = ? WHERE id = ?");
        $result = $stmt->execute([$newStatus, $categoryId]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update status']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>
