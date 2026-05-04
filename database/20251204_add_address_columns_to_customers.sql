-- Add simple address columns to customers table (address_line1, address_line2, city, state, postal_code, country)
-- Run this once against your corelynk_db. If any column already exists, MySQL will error; back up first.

ALTER TABLE `customers`
  ADD COLUMN `address_line1` VARCHAR(255) DEFAULT NULL,
  ADD COLUMN `address_line2` VARCHAR(255) DEFAULT NULL,
  ADD COLUMN `city` VARCHAR(150) DEFAULT NULL,
  ADD COLUMN `state` VARCHAR(150) DEFAULT NULL,
  ADD COLUMN `postal_code` VARCHAR(50) DEFAULT NULL,
  ADD COLUMN `country` VARCHAR(100) DEFAULT NULL;
