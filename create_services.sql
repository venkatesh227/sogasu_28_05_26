CREATE TABLE IF NOT EXISTS `services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_name` varchar(150) NOT NULL,
  `category_id` int(11) NOT NULL,
  `description` text,
  `base_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_type` varchar(50) DEFAULT 'fixed',
  `estimated_time` varchar(100) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
