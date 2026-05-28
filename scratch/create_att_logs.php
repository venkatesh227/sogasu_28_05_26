<?php
include 'includes/db.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        log_date DATE NOT NULL,
        log_time TIME NOT NULL,
        log_type ENUM('In', 'Out') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_emp_date (employee_id, log_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Table 'attendance_logs' created successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
