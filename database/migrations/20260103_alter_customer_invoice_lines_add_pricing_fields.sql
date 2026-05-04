-- Adds missing invoice line fields used by invoice UI and create-from-sales-order flow.
-- Safe to run multiple times due to IF NOT EXISTS.

ALTER TABLE `customer_invoice_lines`
  ADD COLUMN IF NOT EXISTS `product_code` VARCHAR(64) NULL AFTER `product_id`,
  ADD COLUMN IF NOT EXISTS `unit` VARCHAR(32) NULL AFTER `description`,
  ADD COLUMN IF NOT EXISTS `discount_type` VARCHAR(16) NULL AFTER `unit_price`,
  ADD COLUMN IF NOT EXISTS `discount_value` DECIMAL(12,4) NULL AFTER `discount_type`,
  ADD COLUMN IF NOT EXISTS `discount_amount` DECIMAL(12,4) NULL AFTER `discount_value`,
  ADD COLUMN IF NOT EXISTS `tax_rate` DECIMAL(12,4) NULL AFTER `discount_amount`,
  ADD COLUMN IF NOT EXISTS `tax_amount` DECIMAL(12,4) NULL AFTER `tax_rate`,
  ADD COLUMN IF NOT EXISTS `product_image_url` VARCHAR(255) NULL AFTER `tax_amount`;
