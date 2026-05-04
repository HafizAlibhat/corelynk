-- Update processes table to be product-independent
-- This allows processes to be created separately and then attached to products

USE production_management_system;

-- First, let's backup existing data if any
CREATE TABLE IF NOT EXISTS processes_backup AS SELECT * FROM processes;

-- Drop the existing processes table constraints
ALTER TABLE processes DROP FOREIGN KEY processes_ibfk_1;
ALTER TABLE processes DROP INDEX unique_product_sequence;

-- Modify the table structure
ALTER TABLE processes 
    MODIFY COLUMN product_id INT UNSIGNED NULL,
    DROP COLUMN sequence_order,
    ADD COLUMN process_type ENUM('in_house', 'outsource') NOT NULL DEFAULT 'in_house' AFTER description,
    MODIFY COLUMN is_vendor_process BOOLEAN GENERATED ALWAYS AS (process_type = 'outsource') STORED;

-- Create a new table for product-process relationships with ordering
CREATE TABLE IF NOT EXISTS product_processes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    process_id INT UNSIGNED NOT NULL,
    sequence_order INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (process_id) REFERENCES processes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_product_process (product_id, process_id),
    UNIQUE KEY unique_product_sequence (product_id, sequence_order),
    INDEX idx_product_processes_product (product_id),
    INDEX idx_product_processes_process (process_id)
) ENGINE=InnoDB;

-- Re-add the foreign key for vendor
ALTER TABLE processes 
    ADD CONSTRAINT processes_vendor_fk 
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL;

-- Add index for process_type
ALTER TABLE processes ADD INDEX idx_processes_type (process_type);
