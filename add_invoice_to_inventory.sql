-- Sogasu Database Migration: Add Invoice fields to Inventory
-- Adding invoice tracking fields to support purchasing audit trails.

ALTER TABLE `inventory` 
ADD COLUMN `invoice_no` VARCHAR(100) DEFAULT NULL AFTER `supplier_contact`,
ADD COLUMN `invoice_date` DATE DEFAULT NULL AFTER `invoice_no`,
ADD COLUMN `invoice_file` VARCHAR(255) DEFAULT NULL AFTER `invoice_date`;
