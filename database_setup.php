<?php
require 'includes/db.php';

$sqlFiles = [
    'fix_missing_tables.sql',
    'database_update.sql',
    'create_job_roles.sql',
    'create_pay_cycles.sql',
    'create_icons.sql',
    'create_measurements.sql',
    'create_services.sql',
    'create_employee_overtime.sql',
    'create_employee_payments.sql',
    'insert_more_icons.sql',
    'supervisor_workflow_update.sql',
    'alter_branches.sql',
    'alter_categories.sql',
    'alter_tables_misc.sql',
    'create_inventory_categories.sql',
    'add_invoice_to_inventory.sql',
    'create_notifications.sql',
    'create_order_issues.sql',
    'role_permissions.sql',
    'create_suppliers.sql'
];

echo "<h2>Sogasu Database Setup</h2>";

foreach ($sqlFiles as $file) {
    if (file_exists($file)) {
        echo "Processing $file... ";
        $sql = file_get_contents($file);
        
        // Remove 'USE `sogasu`;' if present to avoid issues if db name is different
        $sql = preg_replace('/USE `.*?`;/i', '', $sql);
        
        try {
            $pdo->exec($sql);
            echo "<span style='color:green'>Success</span><br>";
        } catch (PDOException $e) {
            echo "<span style='color:red'>Error: " . $e->getMessage() . "</span><br>";
        }
    } else {
        echo "<span style='color:orange'>File $file not found, skipping.</span><br>";
    }
}

// Create default super admin
echo "Checking for default super admin... ";
$stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'super_admin' LIMIT 1");
$stmt->execute();
if (!$stmt->fetch()) {
    $email = 'admin@sogasu.com';
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $username = 'Super Admin';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'super_admin')");
        $stmt->execute([$username, $email, $password]);
        echo "<span style='color:green'>Default admin created (admin@sogasu.com / admin123)</span><br>";
    } catch (PDOException $e) {
        echo "<span style='color:red'>Error creating admin: " . $e->getMessage() . "</span><br>";
    }
} else {
    echo "Super admin already exists.<br>";
}

echo "<br><b>Setup completed!</b> You can now try logging in at <a href='admin/login.php'>admin/login.php</a>";
?>
