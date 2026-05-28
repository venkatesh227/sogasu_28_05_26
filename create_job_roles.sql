CREATE TABLE IF NOT EXISTS `job_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(100) NOT NULL,
  `description` text,
  `status` enum('active','inactive') DEFAULT 'active',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `job_roles` (`role_name`, `status`) VALUES 
('Master (Cutter)', 'active'),
('Tailor (Stitching)', 'active'),
('Helper (Finishing)', 'active'),
('Manager', 'active');
