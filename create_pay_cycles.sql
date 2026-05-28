CREATE TABLE IF NOT EXISTS `pay_cycles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cycle_name` varchar(100) NOT NULL,
  `status` varchar(20) DEFAULT 'active',
  `is_deleted` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `pay_cycles` (`cycle_name`) SELECT * FROM (SELECT 'Weekly (Saturday)') AS tmp WHERE NOT EXISTS (SELECT cycle_name FROM pay_cycles WHERE cycle_name = 'Weekly (Saturday)') LIMIT 1;
INSERT INTO `pay_cycles` (`cycle_name`) SELECT * FROM (SELECT 'Bi-Weekly') AS tmp WHERE NOT EXISTS (SELECT cycle_name FROM pay_cycles WHERE cycle_name = 'Bi-Weekly') LIMIT 1;
INSERT INTO `pay_cycles` (`cycle_name`) SELECT * FROM (SELECT 'Monthly (1st)') AS tmp WHERE NOT EXISTS (SELECT cycle_name FROM pay_cycles WHERE cycle_name = 'Monthly (1st)') LIMIT 1;
