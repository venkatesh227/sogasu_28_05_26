-- Supervisor Workflow Update

USE `sogasu`;

-- 1. Add Supervisor to job roles if not exists
INSERT INTO `job_roles` (`role_name`, `status`) 
SELECT 'Supervisor', 'active'
WHERE NOT EXISTS (SELECT 1 FROM `job_roles` WHERE `role_name` = 'Supervisor');

-- 2. Create Racks table
CREATE TABLE IF NOT EXISTS `racks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rack_name` varchar(100) NOT NULL,
  `description` text,
  `status` enum('Available','Occupied','Maintenance') DEFAULT 'Available',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Add new columns to orders table
ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `supervisor_id` int(11) DEFAULT NULL AFTER `assigned_employee_id`;
ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `rack_id` int(11) DEFAULT NULL AFTER `supervisor_id`;

-- 4. Sample Racks
INSERT INTO `racks` (`rack_name`, `description`) VALUES 
('Rack A-1', 'Main material storage'),
('Rack A-2', 'Accessory storage'),
('Rack B-1', 'Finished products'),
('Rack B-2', 'Work in progress');
