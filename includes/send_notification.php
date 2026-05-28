<?php
// Helper file for admins to send notifications to employees
// This should be included in admin pages or used via AJAX

session_start();
require '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && $_SESSION['role'] === 'super_admin') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'send_notification') {
        $employee_id = $_POST['employee_id'] ?? '';
        $title = $_POST['title'] ?? '';
        $message = $_POST['message'] ?? '';
        
        if (!empty($employee_id) && !empty($title)) {
            $stmt = $pdo->prepare("INSERT INTO notifications (employee_id, title, message, type) VALUES (?, ?, ?, 'admin_update')");
            if ($stmt->execute([$employee_id, $title, $message])) {
                echo json_encode(['success' => true, 'message' => 'Notification sent successfully']);
                exit();
            }
        }
    }
    
    if ($action === 'send_to_all') {
        $title = $_POST['title'] ?? '';
        $message = $_POST['message'] ?? '';
        
        if (!empty($title)) {
            // Get all active employees
            $employees = $pdo->query("SELECT id FROM employees WHERE status = 1 AND is_deleted = 0")->fetchAll();
            
            $stmt = $pdo->prepare("INSERT INTO notifications (employee_id, title, message, type) VALUES (?, ?, ?, 'admin_update')");
            
            foreach ($employees as $emp) {
                $stmt->execute([$emp['id'], $title, $message]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Notification sent to all employees']);
            exit();
        }
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
?>
