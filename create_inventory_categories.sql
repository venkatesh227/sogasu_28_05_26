CREATE TABLE IF NOT EXISTS `inventory_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `status` TINYINT(1) DEFAULT 1,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO `inventory_categories` (`name`, `code`, `status`, `is_deleted`) VALUES
('Fabric', 'fabric', 1, 0),
('Lining', 'lining', 1, 0),
('Thread', 'thread', 1, 0),
('Accessories (Buttons/Zips)', 'access', 1, 0)
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);
