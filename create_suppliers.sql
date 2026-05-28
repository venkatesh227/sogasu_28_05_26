CREATE TABLE IF NOT EXISTS `suppliers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `supplier_name` VARCHAR(100) NOT NULL,
    `firm_name` VARCHAR(150) DEFAULT NULL,
    `contact_person` VARCHAR(100) DEFAULT NULL,
    `phone_no` VARCHAR(20) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `gst_no` VARCHAR(20) DEFAULT NULL,
    `bank_name` VARCHAR(100) DEFAULT NULL,
    `account_no` VARCHAR(50) DEFAULT NULL,
    `ifsc_code` VARCHAR(20) DEFAULT NULL,
    `bank_branch` VARCHAR(100) DEFAULT NULL,
    `contact` VARCHAR(100) DEFAULT NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_supplier_name` (`supplier_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
