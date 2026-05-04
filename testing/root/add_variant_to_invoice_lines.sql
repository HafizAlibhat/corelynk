-- Add product_variant_id and product_name columns to customer_invoice_lines table

ALTER TABLE `customer_invoice_lines`
ADD COLUMN `product_variant_id` INT(11) DEFAULT NULL AFTER `product_id`,
ADD COLUMN `product_name` VARCHAR(255) DEFAULT NULL AFTER `product_variant_id`,
ADD INDEX `idx_product_variant_id` (`product_variant_id`);

-- Note: product_name stores the template product name for variant products
-- variant attributes will be stored/displayed separately in the description field
