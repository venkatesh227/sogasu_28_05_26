ALTER TABLE categories ADD COLUMN is_deleted tinyint(1) DEFAULT '0' AFTER status;

ALTER TABLE sub_categories 
ADD COLUMN created_by int(11) DEFAULT NULL AFTER created_at,
ADD COLUMN updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
ADD COLUMN updated_by int(11) DEFAULT NULL AFTER updated_at;
