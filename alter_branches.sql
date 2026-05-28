ALTER TABLE branches 
ADD COLUMN branch_code varchar(50) AFTER branch_name, 
ADD COLUMN description text AFTER branch_code, 
ADD COLUMN city varchar(50) AFTER phone, 
ADD COLUMN created_by int(11) DEFAULT NULL AFTER created_at, 
ADD COLUMN updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
ADD COLUMN updated_by int(11) DEFAULT NULL AFTER updated_at,
ADD COLUMN measurement_mode varchar(15) DEFAULT 'CMS' AFTER color_theme;
