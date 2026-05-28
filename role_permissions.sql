-- Role based permissions schema for Sogasu Admin Panel

USE `sogasu`;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `role_name` VARCHAR(100) NOT NULL,
  `permission_key` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_role_permission` (`role_name`, `permission_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default permissions for Manager job role
INSERT IGNORE INTO `role_permissions` (`role_name`, `permission_key`) VALUES
('Manager', 'dashboard'), ('Manager', 'dashboard_view'), ('Manager', 'dashboard_add'), ('Manager', 'dashboard_edit'), ('Manager', 'dashboard_delete'),
('Manager', 'masters'), ('Manager', 'masters_view'), ('Manager', 'masters_add'), ('Manager', 'masters_edit'), ('Manager', 'masters_delete'),
('Manager', 'orders'), ('Manager', 'orders_view'), ('Manager', 'orders_add'), ('Manager', 'orders_edit'), ('Manager', 'orders_delete'),
('Manager', 'employees_tasks'), ('Manager', 'employees_tasks_view'), ('Manager', 'employees_tasks_add'), ('Manager', 'employees_tasks_edit'), ('Manager', 'employees_tasks_delete'),
('Manager', 'hr'), ('Manager', 'hr_view'), ('Manager', 'hr_add'), ('Manager', 'hr_edit'), ('Manager', 'hr_delete'),
('Manager', 'appointments'), ('Manager', 'appointments_view'), ('Manager', 'appointments_add'), ('Manager', 'appointments_edit'), ('Manager', 'appointments_delete'),
('Manager', 'inventory'), ('Manager', 'inventory_view'), ('Manager', 'inventory_add'), ('Manager', 'inventory_edit'), ('Manager', 'inventory_delete'),
('Manager', 'assets'), ('Manager', 'assets_view'), ('Manager', 'assets_add'), ('Manager', 'assets_edit'), ('Manager', 'assets_delete'),
('Manager', 'finance'), ('Manager', 'finance_view'), ('Manager', 'finance_add'), ('Manager', 'finance_edit'), ('Manager', 'finance_delete'),
('Manager', 'customers'), ('Manager', 'customers_view'), ('Manager', 'customers_add'), ('Manager', 'customers_edit'), ('Manager', 'customers_delete'),
('Manager', 'reports'), ('Manager', 'reports_view'), ('Manager', 'reports_add'), ('Manager', 'reports_edit'), ('Manager', 'reports_delete'),
('Manager', 'support'), ('Manager', 'support_view'), ('Manager', 'support_add'), ('Manager', 'support_edit'), ('Manager', 'support_delete');

-- Seed default permissions for Accountant job role
INSERT IGNORE INTO `role_permissions` (`role_name`, `permission_key`) VALUES
('Accountant', 'dashboard'), ('Accountant', 'dashboard_view'), ('Accountant', 'dashboard_add'), ('Accountant', 'dashboard_edit'), ('Accountant', 'dashboard_delete'),
('Accountant', 'finance'), ('Accountant', 'finance_view'), ('Accountant', 'finance_add'), ('Accountant', 'finance_edit'), ('Accountant', 'finance_delete'),
('Accountant', 'hr'), ('Accountant', 'hr_view'), ('Accountant', 'hr_add'), ('Accountant', 'hr_edit'), ('Accountant', 'hr_delete'),
('Accountant', 'reports'), ('Accountant', 'reports_view'), ('Accountant', 'reports_add'), ('Accountant', 'reports_edit'), ('Accountant', 'reports_delete');

-- Seed default permissions for Supervisor job role
INSERT IGNORE INTO `role_permissions` (`role_name`, `permission_key`) VALUES
('Supervisor', 'dashboard'), ('Supervisor', 'dashboard_view'), ('Supervisor', 'dashboard_add'), ('Supervisor', 'dashboard_edit'), ('Supervisor', 'dashboard_delete'),
('Supervisor', 'orders'), ('Supervisor', 'orders_view'), ('Supervisor', 'orders_add'), ('Supervisor', 'orders_edit'), ('Supervisor', 'orders_delete'),
('Supervisor', 'employees_tasks'), ('Supervisor', 'employees_tasks_view'), ('Supervisor', 'employees_tasks_add'), ('Supervisor', 'employees_tasks_edit'), ('Supervisor', 'employees_tasks_delete'),
('Supervisor', 'hr'), ('Supervisor', 'hr_view'), ('Supervisor', 'hr_add'), ('Supervisor', 'hr_edit'), ('Supervisor', 'hr_delete'),
('Supervisor', 'support'), ('Supervisor', 'support_view'), ('Supervisor', 'support_add'), ('Supervisor', 'support_edit'), ('Supervisor', 'support_delete');
