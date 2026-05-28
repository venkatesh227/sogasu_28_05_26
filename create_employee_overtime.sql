CREATE TABLE IF NOT EXISTS `employee_overtime` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `ot_date` date NOT NULL,
  `hours` decimal(5,2) NOT NULL,
  `hourly_rate` decimal(10,2) NOT NULL,
  `multiplier` decimal(4,2) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text,
  `status` varchar(20) DEFAULT 'Pending',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
