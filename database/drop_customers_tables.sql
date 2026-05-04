-- Drop customer-related tables
-- Run with caution: this permanently removes data

DROP TABLE IF EXISTS `customer_addresses`;
DROP TABLE IF EXISTS `customer_audit`;
DROP TABLE IF EXISTS `customer_contacts`;
DROP TABLE IF EXISTS `customer_sequences`;
DROP TABLE IF EXISTS `customers`;

-- Note: do NOT drop `sequences` (global) since it's used by other modules.
