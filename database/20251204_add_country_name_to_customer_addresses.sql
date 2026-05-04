-- Add country_name to customer_addresses to persist free-text country values
ALTER TABLE `customer_addresses`
  ADD COLUMN `country_name` VARCHAR(255) DEFAULT NULL;
