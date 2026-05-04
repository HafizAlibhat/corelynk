-- Add product_variant_id column to purchase_rfq_lines table for variant product support

ALTER TABLE `purchase_rfq_lines` 
ADD COLUMN `product_variant_id` INT(11) DEFAULT NULL AFTER `product_id`,
ADD INDEX `idx_product_variant_id` (`product_variant_id`);

-- Note: This column allows RFQ lines to reference specific product variants
-- instead of just the base product. Variant-specific pricing (from product_variants.cost)
-- will be used when available, falling back to product.cost_price for simple products.
