-- ==========================================================
-- SOGASU DATABASE SCHEMA UPDATE
-- ==========================================================
-- This script creates the required tables for the newly added 
-- admin modules: Billing, Gallery, Support, Assets, Expenses, 
-- Procurement, Sourcing, Design Board, and Tasks/Outsourcing.
-- ==========================================================

USE `sogasu`;

-- 1. BILLING & INVOICES
CREATE TABLE IF NOT EXISTS `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(50) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `advance_paid` decimal(10,2) NOT NULL DEFAULT '0.00',
  `balance_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `gst_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('Paid','Pending','Partial') DEFAULT 'Pending',
  `issue_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. FINANCIAL TRANSACTIONS (Accountant Panel)
CREATE TABLE IF NOT EXISTS `financial_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ref_id` varchar(50) DEFAULT NULL,
  `type` enum('Income','Expense') NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `description` text,
  `transaction_date` date NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. EXPENSES
CREATE TABLE IF NOT EXISTS `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `expense_category` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `status` enum('Paid','Pending') DEFAULT 'Paid',
  `expense_date` date NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. ASSET MANAGEMENT
CREATE TABLE IF NOT EXISTS `asset_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` enum('Machine','Material','Electronics','Other') NOT NULL,
  `status` tinyint(1) DEFAULT '1',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `assets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category_id` int(11) NOT NULL,
  `stock_quantity` int(11) DEFAULT '1',
  `condition_status` enum('Good','Needs Repair','Broken') DEFAULT 'Good',
  `assigned_employee_id` int(11) DEFAULT NULL,
  `last_maintenance` date DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. SUPPORT TICKETS
CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_no` varchar(50) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `issue_type` enum('Quality Issue','Fitting/Alteration','Delivery Delay','Damage','Other') NOT NULL,
  `priority` enum('Low','Medium','High','Critical') DEFAULT 'Medium',
  `status` enum('Open','In Progress','Resolved','Closed') DEFAULT 'Open',
  `description` text NOT NULL,
  `remediation_cost` decimal(10,2) DEFAULT '0.00',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. DESIGN BOARD
CREATE TABLE IF NOT EXISTS `designs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `canvas_data` longtext,
  `notes` text,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. MATERIAL SOURCING & PROCUREMENT
CREATE TABLE IF NOT EXISTS `procurement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `material_name` varchar(150) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit` varchar(20) DEFAULT 'pcs',
  `expected_cost` decimal(10,2) DEFAULT '0.00',
  `vendor_name` varchar(150) DEFAULT NULL,
  `status` enum('Pending','Ordered','Received') DEFAULT 'Pending',
  `expected_date` date DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `stock_issuance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `procurement_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `quantity_issued` int(11) NOT NULL,
  `issue_date` date NOT NULL,
  `notes` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. OUTSOURCING
CREATE TABLE IF NOT EXISTS `outsourcing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `material_design` varchar(255) NOT NULL,
  `outsourcing_employee` varchar(150) NOT NULL,
  `given_date` date NOT NULL,
  `expected_date` date NOT NULL,
  `status` enum('In Progress','Completed','Delayed') DEFAULT 'In Progress',
  `reference_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. GALLERY
CREATE TABLE IF NOT EXISTS `gallery` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(150) DEFAULT NULL,
  `category` enum('Client','Product','Reference') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. MODIFICATIONS TO EXISTING TABLES (If required)
-- Add Supervisor field to employees table if it doesn't exist
-- ALTER TABLE `employees` ADD COLUMN `supervisor_id` int(11) DEFAULT NULL AFTER `user_id`;

-- Add pre-defined notes support to orders if it doesn't exist
-- ALTER TABLE `orders` ADD COLUMN `predefined_notes` varchar(255) DEFAULT NULL AFTER `order_status`;

-- Add detailed task tracking categories
-- ALTER TABLE `orders` ADD COLUMN `task_category` enum('Embroidery','Cutting','Sewing','Finishes','Aari Work') DEFAULT NULL;

-- Add flag to distinguish customer-originated orders from admin-created ones
ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `is_customer_order` TINYINT(1) NOT NULL DEFAULT 0;
