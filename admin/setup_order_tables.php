<?php
include '../includes/db.php';
try {
    // 1. Create orders table
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_code VARCHAR(50) NOT NULL,
        customer_id INT NOT NULL,
        family_member_id INT DEFAULT NULL,
        category_id INT,
        sub_category_id INT,
        fabric_details TEXT,
        notes TEXT,
        order_status VARCHAR(50) DEFAULT 'current',
        supervisor_id INT,
        assigned_employee_id INT DEFAULT NULL,
        rack_id INT DEFAULT NULL,
        base_price DECIMAL(10,2) DEFAULT 0.00,
        extra_charges DECIMAL(10,2) DEFAULT 0.00,
        total_amount DECIMAL(10,2) DEFAULT 0.00,
        advance_paid DECIMAL(10,2) DEFAULT 0.00,
        advance_payment_mode VARCHAR(50) DEFAULT NULL,
        due_date DATE,
        measurement_unit VARCHAR(10) DEFAULT 'CMS',
        is_deleted TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "Success: orders table created.\n";

    // 2. Create order_measurements table
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_measurements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        key_name VARCHAR(100) NOT NULL,
        measurement_value VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Success: order_measurements table created.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
