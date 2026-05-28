<?php
include 'includes/db.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS leave_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        default_allowance INT DEFAULT 0,
        color VARCHAR(20) DEFAULT '#4338ca'
    ) ENGINE=InnoDB;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS leave_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        leave_type_id INT NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        total_days DECIMAL(4,1) NOT NULL,
        reason TEXT,
        status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
        admin_note TEXT,
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        processed_at TIMESTAMP NULL,
        processed_by INT NULL,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_leave_balances (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        leave_type_id INT NOT NULL,
        balance DECIMAL(4,1) DEFAULT 0,
        year INT NOT NULL,
        UNIQUE KEY (employee_id, leave_type_id, year),
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");

    // Seed default types if empty
    $count = $pdo->query("SELECT COUNT(*) FROM leave_types")->fetchColumn();
    if ($count == 0) {
        $pdo->exec("INSERT INTO leave_types (name, default_allowance, color) VALUES 
            ('Casual Leave', 12, '#4338ca'),
            ('Sick Leave', 12, '#e11d48'),
            ('Annual Leave', 15, '#059669')");
    }

    echo "Leave tables created and seeded.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
