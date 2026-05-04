-- Add vendor_id column to product_variants table
-- This allows variants to have their own vendor (or inherit from template product)

ALTER TABLE `product_variants` 
ADD COLUMN `vendor_id` INT UNSIGNED NULL DEFAULT NULL AFTER `cost`,
ADD INDEX `idx_variant_vendor` (`vendor_id`);
