-- Add variant_id column to purchase_grn_lines table
ALTER TABLE `purchase_grn_lines`
ADD COLUMN `variant_id` INT(11) DEFAULT NULL AFTER `product_id`,
ADD INDEX `idx_variant_id` (`variant_id`);
