
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(190) NOT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `currency_code` varchar(10) DEFAULT 'PKR',
  `is_bank` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `api_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `api_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `revoked` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token_hash` (`token_hash`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `art_number_counter`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `art_number_counter` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `next_number` int(10) unsigned NOT NULL DEFAULT 1,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(60) NOT NULL COMMENT 'login, logout, perm_change, data_access, etc.',
  `module` varchar(60) DEFAULT NULL,
  `resource_id` int(10) unsigned DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Extra context in JSON' CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_time` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=928 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `auth_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auth_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_auth_logs_user_id` (`user_id`),
  KEY `idx_auth_logs_action` (`action`),
  KEY `idx_auth_logs_created_at` (`created_at`),
  KEY `idx_auth_logs_email_date` (`email`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=326 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `channels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `channels` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `width` int(11) DEFAULT NULL,
  `height` int(11) DEFAULT NULL,
  `max_file_size` int(11) DEFAULT NULL,
  `allowed_formats` text DEFAULT NULL,
  `background_rule` varchar(20) DEFAULT 'any',
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `rules_json` longtext DEFAULT NULL,
  `short_code` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cheque_deletions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cheque_deletions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cheque_id` int(11) NOT NULL,
  `cheque_number` varchar(50) DEFAULT NULL,
  `amount` decimal(18,2) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `deleted_by` varchar(100) DEFAULT NULL,
  `deleted_at` datetime NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  PRIMARY KEY (`id`),
  KEY `idx_cheque` (`cheque_id`),
  KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cheque_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cheque_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cheque_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_cheque` (`cheque_id`),
  KEY `idx_account` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cheque_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cheque_sequences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bank_account_id` int(11) NOT NULL,
  `prefix` varchar(10) DEFAULT NULL,
  `next_number` int(11) NOT NULL DEFAULT 1,
  `suffix` varchar(10) DEFAULT NULL,
  `last_issued_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bank_account_id` (`bank_account_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cheques`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cheques` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bank_account_id` int(11) NOT NULL,
  `employee_id` int(10) unsigned DEFAULT NULL,
  `cheque_number` varchar(50) NOT NULL,
  `cheque_date` date NOT NULL,
  `payee_type` varchar(20) NOT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `contact_id` int(11) DEFAULT NULL,
  `payee_name` varchar(190) DEFAULT NULL,
  `delivery_type` varchar(20) DEFAULT 'ac_payee',
  `status` varchar(20) DEFAULT 'draft',
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `posted_entry_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `payment_type` varchar(20) NOT NULL DEFAULT 'settlement',
  `deleted_flag` tinyint(1) DEFAULT 0,
  `deleted_reason` text DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bank` (`bank_account_id`),
  KEY `idx_vendor` (`vendor_id`),
  KEY `idx_date` (`cheque_date`),
  KEY `idx_cheques_employee` (`employee_id`),
  CONSTRAINT `fk_cheques_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cheques_backup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cheques_backup` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bank_account_id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `cheque_number` varchar(50) NOT NULL,
  `cheque_date` date NOT NULL,
  `payee_type` varchar(20) NOT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `contact_id` int(11) DEFAULT NULL,
  `payee_name` varchar(190) DEFAULT NULL,
  `delivery_type` varchar(20) DEFAULT 'ac_payee',
  `status` varchar(20) DEFAULT 'draft',
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `posted_entry_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `deleted_flag` tinyint(1) DEFAULT 0,
  `deleted_reason` text DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bank` (`bank_account_id`),
  KEY `idx_vendor` (`vendor_id`),
  KEY `idx_date` (`cheque_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `state_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `state_id` (`state_id`)
) ENGINE=InnoDB AUTO_INCREMENT=47868 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `company_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `company_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `tagline` varchar(255) DEFAULT NULL,
  `contact` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `timezone` varchar(64) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `invoice_footer` text DEFAULT NULL,
  `pdf_template` varchar(50) DEFAULT 'default',
  `logo_path` varchar(255) DEFAULT NULL,
  `base_currency` varchar(10) DEFAULT 'PKR',
  `secondary_currency` varchar(10) DEFAULT 'USD',
  `default_sales_currency` varchar(10) DEFAULT NULL,
  `default_purchase_currency` varchar(10) DEFAULT NULL,
  `use_demo_data` tinyint(1) DEFAULT 1,
  `quotation_prefix` varchar(20) DEFAULT NULL,
  `sales_order_prefix` varchar(20) DEFAULT NULL,
  `customer_code_prefix` varchar(20) DEFAULT NULL,
  `vendor_code_prefix` varchar(20) DEFAULT NULL,
  `art_number_prefix` varchar(10) DEFAULT 'RI',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `pdf_show_header_address` tinyint(1) DEFAULT 1,
  `pdf_show_footer` tinyint(1) DEFAULT 1,
  `pdf_inv_show_header` tinyint(1) DEFAULT 1,
  `pdf_inv_show_footer` tinyint(1) DEFAULT 1,
  `pdf_quote_show_header` tinyint(1) DEFAULT 1,
  `pdf_quote_show_footer` tinyint(1) DEFAULT 1,
  `pdf_so_show_header` tinyint(1) DEFAULT 1,
  `pdf_so_show_footer` tinyint(1) DEFAULT 1,
  `pdf_po_show_header` tinyint(1) DEFAULT 1,
  `pdf_po_show_footer` tinyint(1) DEFAULT 1,
  `pdf_rfq_show_header` tinyint(1) DEFAULT 1,
  `pdf_rfq_show_footer` tinyint(1) DEFAULT 1,
  `bank_details` text DEFAULT NULL,
  `invoice_terms` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `component_stock_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `component_stock_transactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `component_id` int(10) unsigned NOT NULL,
  `transaction_type` enum('in','out','adjustment') NOT NULL,
  `quantity` decimal(10,3) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT 0.00,
  `reference_type` enum('purchase','work_order','adjustment','return') NOT NULL,
  `reference_id` int(10) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `component_id` (`component_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `component_usage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `component_usage` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `work_order_id` int(10) unsigned NOT NULL,
  `component_id` int(10) unsigned NOT NULL,
  `quantity_required` decimal(10,3) NOT NULL,
  `quantity_used` decimal(10,3) DEFAULT 0.000,
  `quantity_remaining` decimal(10,3) DEFAULT 0.000,
  `issued_by` int(10) unsigned DEFAULT NULL,
  `issued_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `work_order_id` (`work_order_id`),
  KEY `component_id` (`component_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `components`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `components` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(20) NOT NULL DEFAULT 'pcs',
  `current_stock` decimal(10,3) DEFAULT 0.000,
  `minimum_stock` decimal(10,3) DEFAULT 0.000,
  `unit_cost` decimal(10,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `core_notification_reads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `core_notification_reads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `notification_id` bigint(20) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_core_notification_user` (`notification_id`,`user_id`),
  KEY `idx_core_notification_reads_user` (`user_id`,`read_at`),
  KEY `notification_id` (`notification_id`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `core_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `core_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `notification_type` varchar(50) NOT NULL,
  `source_table` varchar(50) NOT NULL,
  `source_id` bigint(20) unsigned NOT NULL,
  `source_status` varchar(50) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `payload_json` longtext DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `became_inactive_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_core_notification_source` (`notification_type`,`source_table`,`source_id`),
  KEY `idx_core_notifications_type_active` (`notification_type`,`is_active`),
  KEY `idx_core_notifications_source` (`source_table`,`source_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `countries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `iso_code` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=209 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `credit_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `credit_notes` (
  `id` int(11) NOT NULL,
  `party_type` varchar(30) NOT NULL,
  `party_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `applied_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` varchar(30) NOT NULL DEFAULT 'open',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_party` (`party_type`,`party_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `currencies` (
  `code` varchar(10) NOT NULL,
  `name` varchar(100) NOT NULL,
  `symbol` varchar(10) DEFAULT NULL,
  `is_base` tinyint(1) DEFAULT 0,
  `decimals` int(11) DEFAULT 2,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_addresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `label` varchar(100) DEFAULT NULL,
  `line1` varchar(255) DEFAULT NULL,
  `line2` varchar(255) DEFAULT NULL,
  `city_id` int(11) DEFAULT NULL,
  `state_id` int(11) DEFAULT NULL,
  `country_id` int(11) DEFAULT NULL,
  `city_name` varchar(255) DEFAULT NULL,
  `state_name` varchar(255) DEFAULT NULL,
  `postal_code` varchar(50) DEFAULT NULL,
  `is_billing` tinyint(1) NOT NULL DEFAULT 0,
  `is_shipping` tinyint(1) NOT NULL DEFAULT 0,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `country_name` varchar(255) DEFAULT NULL,
  `address_type` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `country_id` (`country_id`),
  KEY `state_id` (`state_id`),
  KEY `city_id` (`city_id`)
) ENGINE=InnoDB AUTO_INCREMENT=973 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_deposits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_deposits` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `deposit_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payment_method_id` int(11) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `posted_entry_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_invoice_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_invoice_lines` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_variant_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `product_code` varchar(64) DEFAULT NULL,
  `description` varchar(500) NOT NULL,
  `unit` varchar(32) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_type` varchar(16) DEFAULT NULL,
  `discount_value` decimal(12,4) DEFAULT NULL,
  `discount_amount` decimal(12,4) DEFAULT NULL,
  `tax_rate` decimal(12,4) DEFAULT NULL,
  `tax_amount` decimal(12,4) DEFAULT NULL,
  `product_image_url` varchar(255) DEFAULT NULL,
  `tax_code_id` int(11) DEFAULT NULL,
  `line_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime DEFAULT NULL,
  `display_type` varchar(20) DEFAULT NULL,
  `section_title` varchar(255) DEFAULT NULL,
  `sort_order` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `idx_product_variant_id` (`product_variant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_invoice_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_invoice_schedules` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) unsigned NOT NULL,
  `seq` int(11) NOT NULL DEFAULT 1,
  `label` varchar(120) NOT NULL DEFAULT 'Payment',
  `percentage` decimal(9,4) NOT NULL DEFAULT 0.0000,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `basis` enum('invoice_date','delivery_date') NOT NULL DEFAULT 'invoice_date',
  `offset_days` int(11) NOT NULL DEFAULT 0,
  `due_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_id_seq` (`invoice_id`,`seq`),
  KEY `due_date` (`due_date`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_invoices` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) DEFAULT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `issue_date` date NOT NULL,
  `due_date` date NOT NULL,
  `payment_term_id` int(11) DEFAULT NULL,
  `currency_code` varchar(3) NOT NULL DEFAULT 'PKR',
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','confirmed','posted','issued','partially_paid','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `posted_entry_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `invoice_type` enum('system','custom') NOT NULL DEFAULT 'system',
  `parent_invoice_id` int(11) DEFAULT NULL,
  `is_custom_adjusted` tinyint(1) DEFAULT 0,
  `custom_notes` text DEFAULT NULL,
  `shipping_cost` decimal(15,2) DEFAULT 0.00,
  `customs_value` decimal(15,2) DEFAULT 0.00,
  `export_reference` varchar(100) DEFAULT NULL,
  `sales_order_id` int(11) DEFAULT NULL,
  `discount_exclude_shipping` tinyint(1) DEFAULT 0,
  `document_discount_value` decimal(14,2) DEFAULT 0.00,
  `discount_total` decimal(14,2) DEFAULT 0.00,
  `document_discount_type` enum('percent','fixed') DEFAULT NULL,
  `customer_snapshot` text DEFAULT NULL COMMENT 'JSON snapshot of the customer address/contact used on this document',
  `bank_details` text DEFAULT NULL,
  `terms_conditions` text DEFAULT NULL,
  `customer_po_number` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `customer_id` (`customer_id`),
  KEY `status` (`status`),
  KEY `due_date` (`due_date`),
  KEY `idx_parent_invoice` (`parent_invoice_id`),
  KEY `idx_invoice_type` (`invoice_type`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_payment_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_payment_allocations` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `payment_id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `allocated_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_id_invoice_id` (`payment_id`,`invoice_id`),
  KEY `payment_id` (`payment_id`),
  KEY `invoice_id` (`invoice_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_payments` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method_id` int(11) NOT NULL,
  `source_account_id` int(11) DEFAULT NULL,
  `currency_code` varchar(3) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `advance_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `reference_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `posted_entry_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `payment_date` (`payment_date`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_persons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_persons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint(20) unsigned NOT NULL COMMENT 'Reference to customers table',
  `name` varchar(255) NOT NULL COMMENT 'Person name or contact name',
  `title` varchar(100) DEFAULT NULL COMMENT 'Title/Position (e.g., Manager, Director)',
  `email` varchar(255) DEFAULT NULL COMMENT 'Email address',
  `phone` varchar(20) DEFAULT NULL COMMENT 'Phone number',
  `mobile` varchar(20) DEFAULT NULL COMMENT 'Mobile number',
  `is_primary_contact` tinyint(4) DEFAULT 0 COMMENT 'Mark as primary contact (1) or secondary (0)',
  `is_active` tinyint(4) DEFAULT 1 COMMENT 'Active (1) or inactive (0)',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_is_primary` (`is_primary_contact`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) DEFAULT NULL,
  `odoo_id` int(11) DEFAULT NULL,
  `customer_code` varchar(64) NOT NULL,
  `name` varchar(255) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(100) DEFAULT NULL,
  `mobile` varchar(100) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `type` varchar(50) DEFAULT 'retail',
  `status` varchar(50) DEFAULT 'active',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `customer_code` (`customer_code`),
  UNIQUE KEY `idx_customers_public_id` (`public_id`),
  KEY `idx_customers_odoo_id` (`odoo_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1155 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customs_invoice_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customs_invoice_approvals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(36) NOT NULL,
  `customs_invoice_id` bigint(20) unsigned NOT NULL,
  `customs_invoice_version_id` bigint(20) unsigned NOT NULL,
  `approval_status` varchar(40) NOT NULL DEFAULT 'PENDING',
  `approval_channel` varchar(30) NOT NULL DEFAULT 'PORTAL',
  `requested_to_name` varchar(150) DEFAULT NULL,
  `requested_to_email` varchar(190) DEFAULT NULL,
  `request_message` text DEFAULT NULL,
  `decision_comment` text DEFAULT NULL,
  `token_hash` varchar(128) DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `requested_by_user_id` int(10) unsigned DEFAULT NULL,
  `decided_by_user_id` int(10) unsigned DEFAULT NULL,
  `requested_at` datetime DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `customs_invoice_id_approval_status` (`customs_invoice_id`,`approval_status`),
  KEY `token_hash` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customs_invoice_audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customs_invoice_audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(36) NOT NULL,
  `customs_invoice_id` bigint(20) unsigned NOT NULL,
  `customs_invoice_version_id` bigint(20) unsigned DEFAULT NULL,
  `event_type` varchar(40) NOT NULL,
  `field_path` varchar(255) DEFAULT NULL,
  `before_value` longtext DEFAULT NULL,
  `after_value` longtext DEFAULT NULL,
  `diff_json` longtext DEFAULT NULL,
  `actor_user_id` int(10) unsigned DEFAULT NULL,
  `actor_role` varchar(60) DEFAULT NULL,
  `actor_ip` varchar(60) DEFAULT NULL,
  `actor_user_agent` text DEFAULT NULL,
  `correlation_id` varchar(80) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `customs_invoice_id_created_at` (`customs_invoice_id`,`created_at`),
  KEY `event_type_created_at` (`event_type`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customs_invoice_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customs_invoice_files` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(36) NOT NULL,
  `customs_invoice_id` bigint(20) unsigned NOT NULL,
  `customs_invoice_version_id` bigint(20) unsigned DEFAULT NULL,
  `file_type` varchar(30) NOT NULL,
  `storage_disk` varchar(40) NOT NULL DEFAULT 'local',
  `storage_path` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `mime_type` varchar(120) DEFAULT NULL,
  `file_size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `sha256_hash` varchar(64) DEFAULT NULL,
  `template_version` varchar(50) DEFAULT NULL,
  `render_engine_version` varchar(50) DEFAULT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `customs_invoice_id_file_type` (`customs_invoice_id`,`file_type`),
  KEY `customs_invoice_version_id` (`customs_invoice_version_id`),
  KEY `sha256_hash` (`sha256_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customs_invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customs_invoice_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(36) NOT NULL,
  `customs_invoice_id` bigint(20) unsigned NOT NULL,
  `customs_invoice_version_id` bigint(20) unsigned NOT NULL,
  `line_no` int(10) unsigned NOT NULL DEFAULT 1,
  `line_type` varchar(30) NOT NULL DEFAULT 'ORIGINAL_MAPPED',
  `source_invoice_line_id` int(10) unsigned DEFAULT NULL,
  `source_product_id` int(10) unsigned DEFAULT NULL,
  `custom_description` text NOT NULL,
  `hs_code` varchar(40) DEFAULT NULL,
  `declared_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `uom` varchar(40) DEFAULT NULL,
  `declared_unit_price` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `declared_line_total` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `declared_weight` decimal(18,4) DEFAULT NULL,
  `weight_uom` varchar(20) DEFAULT NULL,
  `currency_code` varchar(3) NOT NULL DEFAULT 'USD',
  `group_key` varchar(80) DEFAULT NULL,
  `metadata_json` longtext DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `customs_invoice_version_id_line_no` (`customs_invoice_version_id`,`line_no`),
  KEY `customs_invoice_id` (`customs_invoice_id`),
  KEY `source_invoice_line_id` (`source_invoice_line_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customs_invoice_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customs_invoice_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(36) NOT NULL,
  `customs_invoice_id` bigint(20) unsigned NOT NULL,
  `version_no` int(10) unsigned NOT NULL DEFAULT 1,
  `parent_version_id` bigint(20) unsigned DEFAULT NULL,
  `change_type` varchar(40) NOT NULL DEFAULT 'CREATE',
  `change_reason` text DEFAULT NULL,
  `is_approved_snapshot` tinyint(1) NOT NULL DEFAULT 0,
  `is_final_snapshot` tinyint(1) NOT NULL DEFAULT 0,
  `sealed_at` datetime DEFAULT NULL,
  `snapshot_json` longtext DEFAULT NULL,
  `snapshot_hash` varchar(64) NOT NULL,
  `pdf_file_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`),
  UNIQUE KEY `customs_invoice_id_version_no` (`customs_invoice_id`,`version_no`),
  KEY `customs_invoice_id` (`customs_invoice_id`),
  KEY `sealed_at` (`sealed_at`),
  KEY `snapshot_hash` (`snapshot_hash`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customs_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customs_invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(36) NOT NULL,
  `original_invoice_id` int(10) unsigned NOT NULL,
  `customs_invoice_no` varchar(100) NOT NULL,
  `mode` varchar(30) NOT NULL DEFAULT 'VALUE_ONLY',
  `status` varchar(40) NOT NULL DEFAULT 'DRAFT',
  `current_version_no` int(10) unsigned NOT NULL DEFAULT 1,
  `current_version_id` bigint(20) unsigned DEFAULT NULL,
  `currency_code` varchar(3) NOT NULL DEFAULT 'USD',
  `declared_total` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `shipment_id` int(10) unsigned DEFAULT NULL,
  `tracking_no` varchar(120) DEFAULT NULL,
  `source_snapshot_hash` varchar(64) NOT NULL,
  `lock_state` varchar(40) NOT NULL DEFAULT 'UNLOCKED',
  `row_version` bigint(20) unsigned NOT NULL DEFAULT 0,
  `created_by` int(10) unsigned DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`),
  UNIQUE KEY `customs_invoice_no` (`customs_invoice_no`),
  KEY `original_invoice_id` (`original_invoice_id`),
  KEY `status` (`status`),
  KEY `tracking_no` (`tracking_no`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_order_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `delivery_order_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `delivery_order_id` int(10) unsigned NOT NULL,
  `sales_order_line_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned DEFAULT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `quantity_ordered` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ready_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `qty_to_ship` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_delivery_order_id` (`delivery_order_id`),
  KEY `idx_sales_order_line_id` (`sales_order_line_id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_variant_id` (`variant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_order_parcel_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `delivery_order_parcel_images` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `delivery_order_id` int(10) unsigned NOT NULL,
  `image_path` varchar(500) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_do_id` (`delivery_order_id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_order_tracking_docs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `delivery_order_tracking_docs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `delivery_order_id` int(10) unsigned NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `original_name` varchar(255) NOT NULL DEFAULT '',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_do` (`delivery_order_id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `delivery_orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) DEFAULT NULL,
  `sales_order_id` int(10) unsigned NOT NULL,
  `do_number` varchar(50) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `shipping_vendor_id` int(10) unsigned DEFAULT NULL,
  `shipping_service_id` int(10) unsigned DEFAULT NULL,
  `final_weight_kg` decimal(10,3) DEFAULT NULL,
  `shipping_cost_pkr` decimal(12,2) DEFAULT NULL,
  `tracking_number` varchar(150) DEFAULT NULL,
  `tracking_url` varchar(500) DEFAULT NULL,
  `destination_country` varchar(100) DEFAULT NULL,
  `shipping_notes` text DEFAULT NULL,
  `parcel_image` varchar(255) DEFAULT NULL,
  `estimated_delivery_days` int(10) unsigned DEFAULT NULL,
  `delivery_status` varchar(30) DEFAULT NULL,
  `delivery_confirmed_at` datetime DEFAULT NULL,
  `delivery_notes` text DEFAULT NULL,
  `shipped_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `shipping_po_id` int(10) unsigned DEFAULT NULL,
  `shipping_bill_id` int(10) unsigned DEFAULT NULL,
  `delivered_at` date DEFAULT NULL,
  `delivery_screenshot` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `do_number` (`do_number`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_sales_order_id` (`sales_order_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `document_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_attachments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `document_type` varchar(64) NOT NULL,
  `document_id` int(10) unsigned NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size` int(10) unsigned DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `document_type` (`document_type`),
  KEY `document_id` (`document_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `document_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `document_type` varchar(50) NOT NULL,
  `document_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `context` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_dl_doc` (`document_type`,`document_id`),
  KEY `idx_dl_user` (`user_id`),
  KEY `idx_dl_time` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=313 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `document_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_tags` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tag_id` int(10) unsigned NOT NULL,
  `document_type` varchar(80) NOT NULL,
  `document_id` int(10) unsigned NOT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_document_tags` (`tag_id`,`document_type`,`document_id`),
  KEY `idx_document_tags_document` (`document_type`,`document_id`),
  KEY `idx_document_tags_tag` (`tag_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employee_skills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_skills` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(10) unsigned NOT NULL,
  `skill_name` varchar(100) NOT NULL,
  `proficiency_level` varchar(50) DEFAULT 'basic',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employees` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_code` varchar(20) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `monthly_salary` decimal(15,2) DEFAULT NULL,
  `salary_currency` varchar(3) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_code` (`employee_code`),
  UNIQUE KEY `uniq_employee_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `exchange_rate`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `exchange_rate` (
  `id` int(11) NOT NULL,
  `base_code` varchar(10) NOT NULL,
  `quote_code` varchar(10) NOT NULL,
  `rate` decimal(15,6) NOT NULL,
  `as_of` date NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pair_date` (`base_code`,`quote_code`,`as_of`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `feature_flags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `feature_flags` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `flag_key` varchar(80) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_feature_flags_flag_key` (`flag_key`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `field_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `field_permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int(10) unsigned NOT NULL,
  `module` varchar(60) NOT NULL,
  `field_name` varchar(100) NOT NULL COMMENT 'DB column or virtual field name',
  `visibility` enum('visible','masked','hidden') NOT NULL DEFAULT 'visible',
  `mask_value` varchar(50) DEFAULT '***' COMMENT 'Replacement text when masked',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_module_field` (`role_id`,`module`,`field_name`),
  CONSTRAINT `field_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fiscal_year`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fiscal_year` (
  `id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gate_passes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gate_passes` (
  `id` int(10) unsigned NOT NULL,
  `public_id` char(36) DEFAULT NULL,
  `gate_pass_number` varchar(100) NOT NULL,
  `type` varchar(20) NOT NULL,
  `recipient_type` varchar(20) DEFAULT 'vendor',
  `recipient_name` varchar(150) DEFAULT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `items` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `expected_date` datetime DEFAULT NULL,
  `actual_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `completed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `grn_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `grn_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `grn_id` int(11) NOT NULL,
  `po_line_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `qty_received` decimal(15,3) NOT NULL DEFAULT 0.000,
  `unit_cost` decimal(15,4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `grn_id` (`grn_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `grn_receipt_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `grn_receipt_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `po_id` int(10) unsigned NOT NULL COMMENT 'Reference to purchase order',
  `po_line_id` int(10) unsigned NOT NULL COMMENT 'Reference to PO line',
  `grn_id` int(10) unsigned NOT NULL COMMENT 'Reference to GRN',
  `grn_line_id` int(10) unsigned DEFAULT NULL COMMENT 'Reference to GRN line',
  `product_id` int(10) unsigned NOT NULL COMMENT 'Product received',
  `variant_id` int(10) unsigned DEFAULT NULL COMMENT 'Product variant if applicable',
  `unit_price` decimal(15,4) DEFAULT NULL COMMENT 'Unit price at time of receipt',
  `qty_ordered` decimal(15,4) DEFAULT NULL COMMENT 'Total qty ordered on PO line',
  `qty_previously_received` decimal(15,4) DEFAULT NULL COMMENT 'Qty received in previous GRNs',
  `qty_received_this_grn` decimal(15,4) DEFAULT NULL COMMENT 'Qty received in this GRN',
  `warehouse_id` int(10) unsigned DEFAULT NULL COMMENT 'Warehouse location',
  `location_id` int(10) unsigned DEFAULT NULL COMMENT 'Location within warehouse',
  `received_date` datetime DEFAULT NULL COMMENT 'When received',
  `previous_grn_id` int(10) unsigned DEFAULT NULL COMMENT 'If this completes pending from earlier GRN',
  `notes` text DEFAULT NULL COMMENT 'Notes on this receipt (e.g., partial damage, over-received reason)',
  `created_by` int(10) unsigned DEFAULT NULL COMMENT 'User who created receipt',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_grn_line` (`grn_id`,`po_line_id`),
  KEY `idx_po` (`po_id`),
  KEY `idx_po_line` (`po_line_id`),
  KEY `idx_grn` (`grn_id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_received_date` (`received_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Audit trail for GRN receipt history - tracks each partial receipt event';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `grns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `grns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `po_id` int(11) DEFAULT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `grn_number` varchar(100) DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `po_id` (`po_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `internal_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `internal_transfers` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_number` varchar(30) NOT NULL,
  `product_id` int(11) unsigned NOT NULL,
  `variant_id` int(11) unsigned DEFAULT NULL,
  `item_key` varchar(32) NOT NULL,
  `quantity` decimal(18,4) NOT NULL,
  `from_warehouse_id` int(11) unsigned NOT NULL,
  `from_location_id` int(11) unsigned NOT NULL,
  `to_warehouse_id` int(11) unsigned NOT NULL,
  `to_location_id` int(11) unsigned NOT NULL,
  `reason` text NOT NULL,
  `notes` text DEFAULT NULL,
  `out_movement_id` int(11) DEFAULT NULL,
  `in_movement_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transfer_number` (`transfer_number`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoice_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoice_documents` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `document_type` enum('system_invoice','custom_invoice','receipt','credit_note') NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `generated_at` datetime DEFAULT NULL,
  `generated_by` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_id` (`invoice_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `journal_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `journal_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entry_date` date NOT NULL,
  `memo` varchar(255) DEFAULT NULL,
  `currency_code` varchar(10) DEFAULT 'PKR',
  `total_debits` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_credits` decimal(18,2) NOT NULL DEFAULT 0.00,
  `usd_amount` decimal(18,2) DEFAULT NULL,
  `usd_fee` decimal(18,2) DEFAULT NULL,
  `usd_system_rate` decimal(18,6) DEFAULT NULL,
  `usd_bank_rate` decimal(18,6) DEFAULT NULL,
  `usd_net_converted` decimal(18,2) DEFAULT NULL,
  `source_type` enum('invoice','payment','cheque','credit_note','manual','vendor_bill','vendor_payment','vendor_advance_application','vendor_bill_advance_application','mobile_expense','salary_payment') DEFAULT NULL,
  `source_id` int(11) DEFAULT NULL,
  `vendor_id` int(11) unsigned DEFAULT NULL,
  `customer_id` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `journal_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `journal_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entry_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `debit` decimal(18,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(18,2) NOT NULL DEFAULT 0.00,
  `currency_code` varchar(10) DEFAULT 'PKR',
  `fx_rate` decimal(18,8) DEFAULT NULL,
  `base_amount` decimal(18,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_entry` (`entry_id`),
  KEY `idx_account` (`account_id`)
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_login_email_time` (`email`,`created_at`),
  KEY `idx_login_ip_time` (`ip_address`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=472 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `version` varchar(255) NOT NULL,
  `class` varchar(255) NOT NULL,
  `group` varchar(255) NOT NULL,
  `namespace` varchar(255) NOT NULL,
  `time` int(11) NOT NULL,
  `batch` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=148 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `odoo_screen_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `odoo_screen_cache` (
  `cache_key` varchar(191) NOT NULL,
  `data` longtext DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`cache_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `odoo_screen_claims`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `odoo_screen_claims` (
  `odoo_id` int(11) NOT NULL,
  `claimed_by` varchar(150) DEFAULT NULL,
  `claimed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`odoo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `odoo_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `odoo_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `host` varchar(255) DEFAULT NULL,
  `port` int(11) DEFAULT NULL,
  `db_name` varchar(150) DEFAULT NULL,
  `username` varchar(150) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `job_mode` varchar(20) DEFAULT 'manual',
  `job_interval` int(11) DEFAULT NULL,
  `fetch_limit` int(11) DEFAULT 10,
  `last_run` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL,
  `method_name` varchar(100) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_terms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_terms` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `net_days` int(11) NOT NULL,
  `discount_days` int(11) DEFAULT 0,
  `discount_percentage` decimal(5,2) DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `milestones` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `module` varchar(60) NOT NULL COMMENT 'e.g. invoices, orders, inventory',
  `action` varchar(30) NOT NULL COMMENT 'read / write / edit / delete',
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_module_action` (`module`,`action`)
) ENGINE=InnoDB AUTO_INCREMENT=98 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pos_order_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pos_order_lines` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `pos_order_id` int(11) unsigned NOT NULL,
  `product_id` int(11) unsigned DEFAULT NULL,
  `variant_id` int(11) unsigned DEFAULT NULL,
  `product_name` varchar(200) NOT NULL,
  `variant_name` varchar(200) DEFAULT '',
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(12,2) DEFAULT 0.00,
  `discount` decimal(12,2) DEFAULT 0.00,
  `line_total` decimal(12,2) DEFAULT 0.00,
  `notes` varchar(500) DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_pos_order_id` (`pos_order_id`),
  KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pos_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pos_orders` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `order_number` varchar(30) NOT NULL,
  `order_type` varchar(20) DEFAULT 'dine_in',
  `customer_name` varchar(100) DEFAULT 'Walk-in',
  `table_number` varchar(20) DEFAULT NULL,
  `subtotal` decimal(12,2) DEFAULT 0.00,
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `tax_amount` decimal(12,2) DEFAULT 0.00,
  `discount_amount` decimal(12,2) DEFAULT 0.00,
  `discount_type` varchar(10) DEFAULT 'fixed',
  `total` decimal(12,2) DEFAULT 0.00,
  `amount_paid` decimal(12,2) DEFAULT 0.00,
  `change_due` decimal(12,2) DEFAULT 0.00,
  `payment_method` varchar(20) DEFAULT 'cash',
  `status` varchar(20) DEFAULT 'open',
  `notes` text DEFAULT NULL,
  `cashier_id` int(11) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order_number` (`order_number`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `preparation_components`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `preparation_components` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` int(10) unsigned DEFAULT NULL,
  `product_id` int(10) unsigned DEFAULT NULL,
  `variant_id` int(10) unsigned DEFAULT NULL,
  `qty_per_unit` decimal(10,4) DEFAULT 0.0000,
  `is_optional` tinyint(1) DEFAULT 0,
  `processing_record_id` int(10) unsigned DEFAULT NULL,
  `component_name` varchar(255) DEFAULT NULL,
  `quantity` decimal(12,4) DEFAULT 0.0000,
  `unit` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_preparation_components_profile_id` (`profile_id`),
  KEY `idx_preparation_components_variant` (`variant_id`),
  CONSTRAINT `fk_preparation_components_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `preparation_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `preparation_profiles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned NOT NULL,
  `variant_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_preparation_profiles_product_id` (`product_id`),
  KEY `idx_preparation_profiles_product_active` (`product_id`,`is_active`),
  KEY `idx_preparation_profiles_variant` (`variant_id`),
  KEY `idx_preparation_profiles_product_variant_active` (`product_id`,`variant_id`,`is_active`),
  CONSTRAINT `fk_preparation_profiles_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `preparation_steps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `preparation_steps` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` int(10) unsigned NOT NULL,
  `service_product_id` int(10) unsigned DEFAULT NULL,
  `service_variant_id` int(10) unsigned DEFAULT NULL,
  `step_order` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_optional` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_preparation_steps_profile_id` (`profile_id`),
  KEY `idx_preparation_steps_profile_order` (`profile_id`,`step_order`),
  KEY `idx_preparation_steps_service_product` (`service_product_id`),
  KEY `idx_preparation_steps_service_variant` (`service_variant_id`),
  CONSTRAINT `fk_preparation_steps_service_product` FOREIGN KEY (`service_product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_preparation_steps_service_variant` FOREIGN KEY (`service_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `price_list_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `price_list_items` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `price_list_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `special_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `min_quantity` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `price_list_id` (`price_list_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `price_lists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `price_lists` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `process_batch_employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `process_batch_employees` (
  `id` int(11) unsigned NOT NULL,
  `batch_id` int(11) unsigned NOT NULL,
  `employee_id` int(11) unsigned NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `batch_id` (`batch_id`),
  KEY `employee_id` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `process_batch_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `process_batch_logs` (
  `id` int(10) unsigned NOT NULL,
  `process_batch_id` int(10) unsigned NOT NULL,
  `log_date` date DEFAULT NULL,
  `log_type` varchar(50) DEFAULT 'progress',
  `qty_received` int(10) unsigned DEFAULT 0,
  `qty_completed` int(10) unsigned DEFAULT 0,
  `qty_rejected` int(10) unsigned DEFAULT 0,
  `qty_scrapped` int(10) unsigned DEFAULT 0,
  `qty_for_repair` int(10) unsigned DEFAULT 0,
  `accepted_qty` int(10) unsigned DEFAULT 0,
  `repaired_qty` int(10) unsigned DEFAULT 0,
  `rejected_qty` int(10) unsigned DEFAULT 0,
  `employee_id` int(10) unsigned DEFAULT NULL,
  `vendor_id` int(10) unsigned DEFAULT NULL,
  `operator_id` int(10) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `process_batch_id` (`process_batch_id`),
  KEY `employee_id` (`employee_id`),
  KEY `vendor_id` (`vendor_id`),
  KEY `operator_id` (`operator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `process_batch_releases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `process_batch_releases` (
  `id` int(10) unsigned NOT NULL,
  `process_batch_id` int(10) unsigned NOT NULL,
  `released_qty` decimal(10,3) NOT NULL,
  `released_by` int(10) unsigned DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `gatepass_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `process_batch_id` (`process_batch_id`),
  KEY `released_by` (`released_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `process_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `process_batches` (
  `id` int(10) unsigned NOT NULL,
  `work_order_item_id` int(10) unsigned NOT NULL,
  `process_id` int(10) unsigned NOT NULL,
  `vendor_id` int(10) unsigned DEFAULT NULL,
  `batch_code` varchar(100) DEFAULT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `planned_qty` int(10) unsigned DEFAULT 0,
  `actual_qty` int(10) unsigned DEFAULT 0,
  `status` varchar(50) DEFAULT 'open',
  `created_by` int(10) unsigned DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `work_order_item_id` (`work_order_item_id`),
  KEY `process_id` (`process_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_pb_vendor` (`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `process_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `process_categories` (
  `id` int(10) unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_process_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `process_employee_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `process_employee_assignments` (
  `id` int(10) unsigned NOT NULL,
  `process_id` int(10) unsigned NOT NULL,
  `employee_id` int(10) unsigned NOT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `process_id` (`process_id`),
  KEY `employee_id` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `process_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `process_templates` (
  `id` int(10) unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,
  `is_vendor_process` tinyint(1) DEFAULT 0,
  `vendor_id` int(10) unsigned DEFAULT NULL,
  `standard_time_minutes` int(11) DEFAULT 0,
  `qc_checklist` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`qc_checklist`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `vendor_id` (`vendor_id`),
  KEY `idx_process_templates_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `process_vendors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `process_vendors` (
  `id` int(10) unsigned NOT NULL,
  `process_id` int(10) unsigned NOT NULL,
  `vendor_id` int(10) unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_process_vendor` (`process_id`,`vendor_id`),
  KEY `idx_pv_process` (`process_id`),
  KEY `idx_pv_vendor` (`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `processes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `processes` (
  `id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned DEFAULT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `sequence_order` int(11) NOT NULL DEFAULT 1,
  `is_vendor_process` tinyint(1) NOT NULL DEFAULT 0,
  `vendor_id` int(10) unsigned DEFAULT NULL,
  `description` text DEFAULT NULL,
  `standard_time_minutes` int(11) DEFAULT 0,
  `responsibility_mode` varchar(20) DEFAULT NULL,
  `responsibility_department` varchar(100) DEFAULT NULL,
  `qc_checklist` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`qc_checklist`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `process_template_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `fk_processes_process_template_id` (`process_template_id`),
  KEY `idx_processes_category` (`category_id`),
  KEY `idx_processes_vendor` (`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `processing_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `processing_records` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned DEFAULT NULL,
  `sales_order_id` int(10) unsigned DEFAULT NULL,
  `sales_order_line_id` int(10) unsigned DEFAULT NULL,
  `step_id` int(10) unsigned DEFAULT NULL,
  `vendor_id` int(10) unsigned DEFAULT NULL,
  `qty` decimal(10,4) DEFAULT 0.0000,
  `location_id` int(10) unsigned DEFAULT NULL,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `notes` text DEFAULT NULL,
  `parent_send_note_id` int(11) DEFAULT NULL,
  `rework_reason_id` int(11) DEFAULT NULL,
  `rework_vendor_id` int(11) DEFAULT NULL,
  `actual_start_date` timestamp NULL DEFAULT NULL,
  `actual_completion_date` timestamp NULL DEFAULT NULL,
  `completion_percent` decimal(5,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_processing_records_product_step_status` (`product_id`,`step_id`,`status`),
  KEY `idx_processing_records_so_id` (`sales_order_id`),
  KEY `idx_processing_records_so_line_id` (`sales_order_line_id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_asset_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_asset_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned DEFAULT NULL,
  `variant_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `variant_id` (`variant_id`),
  KEY `product_id_variant_id` (`product_id`,`variant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=244 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_asset_listings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_asset_listings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned DEFAULT NULL,
  `channel_id` int(10) unsigned DEFAULT NULL,
  `listing_url` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `variant_id` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_id_variant_id_channel_id` (`product_id`,`variant_id`,`channel_id`),
  KEY `product_id` (`product_id`),
  KEY `channel_id` (`channel_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_assets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `asset_group_id` int(10) unsigned DEFAULT NULL,
  `channel_id` int(10) unsigned DEFAULT NULL,
  `type` varchar(20) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `thumbnail_path` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_size` bigint(20) unsigned NOT NULL,
  `mime_type` varchar(120) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `tags` text DEFAULT NULL,
  `uploaded_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `section_label` varchar(120) DEFAULT NULL,
  `source_asset_id` int(11) unsigned DEFAULT NULL,
  `section_key` varchar(60) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `asset_group_id` (`asset_group_id`),
  KEY `channel_id` (`channel_id`),
  KEY `asset_group_id_channel_id_type` (`asset_group_id`,`channel_id`,`type`),
  KEY `idx_product_assets_source_asset_id` (`source_asset_id`)
) ENGINE=InnoDB AUTO_INCREMENT=481 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_attribute_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_attribute_assignments` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(11) unsigned NOT NULL,
  `attribute_id` int(11) unsigned NOT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_id_attribute_id` (`product_id`,`attribute_id`),
  KEY `product_id` (`product_id`),
  KEY `attribute_id` (`attribute_id`),
  CONSTRAINT `product_attribute_assignments_attribute_id_foreign` FOREIGN KEY (`attribute_id`) REFERENCES `product_attributes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `product_attribute_assignments_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_attribute_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_attribute_values` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `attribute_id` int(11) unsigned NOT NULL,
  `value` varchar(150) NOT NULL,
  `code` varchar(32) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attribute_id_value` (`attribute_id`,`value`),
  UNIQUE KEY `attribute_id_code` (`attribute_id`,`code`),
  KEY `attribute_id` (`attribute_id`),
  CONSTRAINT `product_attribute_values_attribute_id_foreign` FOREIGN KEY (`attribute_id`) REFERENCES `product_attributes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_attributes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_attributes` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `values` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `prefix` varchar(50) DEFAULT NULL,
  `suffix` varchar(4) DEFAULT NULL,
  `start_range` int(10) unsigned DEFAULT 1,
  `end_range` int(10) unsigned DEFAULT 999999,
  `next_number` int(10) unsigned DEFAULT 1,
  `parent_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_categories_suffix` (`suffix`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_processes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_processes` (
  `id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `process_template_id` int(10) unsigned DEFAULT NULL,
  `process_id` int(10) unsigned DEFAULT NULL,
  `sequence_order` int(11) NOT NULL DEFAULT 1,
  `custom_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_product_processes_product` (`product_id`),
  KEY `idx_product_processes_template` (`process_template_id`),
  KEY `idx_product_processes_process` (`process_id`),
  KEY `idx_product_processes_sequence` (`product_id`,`sequence_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_stock_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_stock_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `transaction_type` varchar(20) NOT NULL,
  `quantity` decimal(15,3) NOT NULL DEFAULT 0.000,
  `unit_cost` decimal(15,4) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_variants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_variants` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(11) unsigned NOT NULL,
  `art_number` varchar(100) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `price` decimal(15,2) DEFAULT NULL,
  `sale_currency` varchar(10) DEFAULT NULL,
  `cost` decimal(15,2) DEFAULT NULL,
  `cost_currency` varchar(10) DEFAULT NULL,
  `vendor_id` int(10) unsigned DEFAULT NULL,
  `vendor_price` decimal(15,4) DEFAULT NULL,
  `vendor_currency` varchar(10) DEFAULT NULL,
  `attributes` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `weight` decimal(15,4) DEFAULT NULL,
  `cost_price` decimal(15,2) DEFAULT NULL,
  `sale_price` decimal(15,2) DEFAULT NULL,
  `vendor_price_pkr` decimal(15,2) DEFAULT NULL,
  `combination_key` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `art_number` (`art_number`),
  UNIQUE KEY `ux_product_variants_product_combination` (`product_id`,`combination_key`),
  KEY `product_id` (`product_id`),
  KEY `idx_variant_vendor` (`vendor_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11710 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_vendor_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_vendor_rates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned NOT NULL,
  `variant_id` int(10) unsigned DEFAULT NULL,
  `vendor_id` int(10) unsigned NOT NULL,
  `rate` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `currency` varchar(3) NOT NULL DEFAULT 'PKR',
  `lead_time_days` int(11) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_vendor_rates_item_vendor` (`product_id`,`variant_id`,`vendor_id`),
  KEY `product_id` (`product_id`),
  KEY `variant_id` (`variant_id`),
  KEY `vendor_id` (`vendor_id`),
  KEY `product_id_variant_id` (`product_id`,`variant_id`),
  CONSTRAINT `product_vendor_rates_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `product_vendor_rates_variant_id_foreign` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `product_vendor_rates_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_vendors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_vendors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `vendor_product_code` varchar(100) DEFAULT NULL,
  `lead_time_days` int(11) DEFAULT NULL,
  `last_cost` decimal(15,4) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `vendor_id` (`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,
  `unit` varchar(20) NOT NULL DEFAULT 'pcs',
  `description` text DEFAULT NULL,
  `images` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `current_stock` decimal(15,3) NOT NULL DEFAULT 0.000,
  `unit_cost` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `barcode` varchar(100) DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `weight` decimal(10,3) DEFAULT 0.000,
  `vendor_id` int(11) DEFAULT NULL,
  `cost_price` decimal(15,2) DEFAULT 0.00,
  `cost_currency` varchar(3) DEFAULT 'USD',
  `sale_price` decimal(15,2) DEFAULT 0.00,
  `sale_currency` varchar(3) DEFAULT 'USD',
  `vendor_price` decimal(15,2) DEFAULT NULL,
  `vendor_price_pkr` decimal(15,2) DEFAULT NULL,
  `vendor_currency` varchar(3) DEFAULT NULL,
  `product_type` varchar(20) NOT NULL DEFAULT 'simple',
  `detailed_type` varchar(20) NOT NULL DEFAULT 'storable',
  `service_policy` varchar(20) DEFAULT NULL,
  `service_execution_mode` varchar(20) DEFAULT NULL,
  `attributes_definitions` text DEFAULT NULL,
  `excluded_combos` text DEFAULT NULL,
  `unit_weight` decimal(12,3) NOT NULL DEFAULT 0.000,
  `weight_unit` varchar(10) NOT NULL DEFAULT 'KG',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  UNIQUE KEY `idx_products_public_id` (`public_id`),
  KEY `category_id` (`category_id`),
  KEY `idx_products_code` (`code`),
  KEY `idx_products_detailed_type` (`detailed_type`)
) ENGINE=InnoDB AUTO_INCREMENT=108 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_grn_line_issues`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_grn_line_issues` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `grn_id` int(11) NOT NULL,
  `grn_line_id` int(11) NOT NULL,
  `po_line_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `action_date` date DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_grn_line` (`grn_line_id`),
  KEY `idx_grn` (`grn_id`),
  KEY `idx_action_type` (`action_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_grn_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_grn_lines` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `grn_id` int(11) NOT NULL,
  `po_line_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `qty_received` float NOT NULL DEFAULT 0,
  `over_received_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `over_receipt_reason_type` varchar(50) DEFAULT NULL,
  `over_receipt_reason_details` text DEFAULT NULL,
  `over_receipt_split_json` text DEFAULT NULL,
  `unit_cost` float DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `warehouse_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_variant_id` (`variant_id`),
  KEY `idx_warehouse_location` (`warehouse_id`,`location_id`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_grns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_grns` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) DEFAULT NULL,
  `grn_number` varchar(100) DEFAULT NULL,
  `po_id` int(11) NOT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_purchase_grns_public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_lines_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_lines_cache` (
  `odoo_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `product_qty` decimal(15,4) DEFAULT 0.0000,
  `qty_received` decimal(15,4) DEFAULT 0.0000,
  `metadata` longtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`odoo_id`),
  KEY `idx_purchase_order` (`order_id`),
  KEY `idx_purchase_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_order_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_order_lines` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `po_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `unit_price` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `tax_code_id` int(11) DEFAULT NULL,
  `line_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `qty_received` decimal(15,3) NOT NULL DEFAULT 0.000,
  `created_at` datetime DEFAULT NULL,
  `display_type` varchar(20) DEFAULT NULL,
  `receive_status` varchar(30) DEFAULT NULL,
  `fully_received_date` datetime DEFAULT NULL,
  `discount_value` decimal(12,4) DEFAULT 0.0000,
  `section_title` varchar(255) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `discount_amount` decimal(12,4) DEFAULT 0.0000,
  `discount_type` enum('percent','fixed') DEFAULT NULL,
  `sort_order` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_po` (`po_id`),
  KEY `idx_qty_received` (`qty_received`),
  KEY `idx_receive_status` (`receive_status`)
) ENGINE=InnoDB AUTO_INCREMENT=89 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_orders` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) DEFAULT NULL,
  `po_number` varchar(100) DEFAULT NULL,
  `rfq_id` int(11) DEFAULT NULL,
  `vendor_id` int(11) NOT NULL,
  `currency` varchar(10) DEFAULT NULL,
  `order_date` date NOT NULL,
  `delivery_date` date DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'draft',
  `currency_code` varchar(10) DEFAULT 'PKR',
  `subtotal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `tax_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_by` int(11) unsigned DEFAULT NULL,
  `discount_exclude_shipping` tinyint(1) DEFAULT 0,
  `shipping_amount` decimal(12,2) DEFAULT 0.00,
  `document_discount_value` decimal(14,2) DEFAULT 0.00,
  `discount_total` decimal(14,2) DEFAULT 0.00,
  `document_discount_type` enum('percent','fixed') DEFAULT NULL,
  `processing_record_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_purchase_orders_public_id` (`public_id`),
  UNIQUE KEY `uq_purchase_orders_po_number` (`po_number`),
  KEY `idx_vendor` (`vendor_id`),
  KEY `fk_purchase_orders_processing_record_id` (`processing_record_id`),
  CONSTRAINT `fk_purchase_orders_processing_record_id` FOREIGN KEY (`processing_record_id`) REFERENCES `processing_records` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_rfq_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_rfq_lines` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `rfq_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_variant_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `quantity` float NOT NULL DEFAULT 0,
  `unit_cost` float DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `line_total` float DEFAULT 0,
  `discount` float DEFAULT 0,
  `discount_percent` float DEFAULT 0,
  `tax_percent` float DEFAULT 0,
  `tax_amount` float DEFAULT 0,
  `display_type` varchar(20) DEFAULT NULL,
  `section_title` varchar(255) DEFAULT NULL,
  `sort_order` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_product_variant_id` (`product_variant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=94 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_rfqs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_rfqs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) DEFAULT NULL,
  `rfq_number` varchar(100) DEFAULT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `currency` varchar(10) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  `rfq_date` datetime DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `subtotal` float DEFAULT 0,
  `discount` float DEFAULT 0,
  `tax_amount` float DEFAULT 0,
  `grand_total` float DEFAULT 0,
  `total_discount` float DEFAULT 0,
  `total_tax` float DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `uq_purchase_rfqs_rfq_number` (`rfq_number`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchases_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchases_cache` (
  `odoo_id` int(11) NOT NULL,
  `name` varchar(150) DEFAULT NULL,
  `partner_id` int(11) DEFAULT NULL,
  `partner_code` varchar(150) DEFAULT NULL,
  `date_order` datetime DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `ordered_qty` decimal(15,4) DEFAULT 0.0000,
  `received_qty` decimal(15,4) DEFAULT 0.0000,
  `outstanding_qty` decimal(15,4) DEFAULT 0.0000,
  `order_line_ids` longtext DEFAULT NULL,
  `metadata` longtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`odoo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `qc_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qc_records` (
  `id` int(10) unsigned NOT NULL,
  `process_run_id` int(10) unsigned NOT NULL,
  `qc_checklist_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`qc_checklist_data`)),
  `quantity_checked` int(10) unsigned NOT NULL,
  `quantity_passed` int(10) unsigned DEFAULT 0,
  `quantity_failed` int(10) unsigned DEFAULT 0,
  `qc_decision` enum('pass','rework','reject') NOT NULL,
  `remarks` text DEFAULT NULL,
  `inspected_by` int(10) unsigned DEFAULT NULL,
  `inspected_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `qc_rejection_reasons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qc_rejection_reasons` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_qc_rejection_reasons_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `qty_variance_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qty_variance_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `send_note_id` int(11) DEFAULT NULL,
  `receive_note_id` int(11) DEFAULT NULL,
  `variance_type` enum('overage','shortage','quality_loss') DEFAULT NULL,
  `sent_qty` decimal(10,4) DEFAULT NULL,
  `received_qty` decimal(10,4) DEFAULT NULL,
  `variance_qty` decimal(10,4) DEFAULT NULL,
  `variance_percent` decimal(5,2) DEFAULT NULL,
  `threshold_percent` decimal(5,2) DEFAULT NULL,
  `status` enum('flagged','approved','rejected','reversed') DEFAULT 'flagged',
  `approved_by` int(11) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_send_note_id` (`send_note_id`),
  KEY `idx_receive_note_id` (`receive_note_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `quotation_discounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quotation_discounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `quotation_id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `discount_type` enum('percent','fixed') NOT NULL DEFAULT 'percent',
  `discount_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `conditions` text DEFAULT NULL COMMENT 'JSON conditions for applying the discount',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) unsigned DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quotation_id` (`quotation_id`),
  KEY `active` (`active`),
  KEY `discount_type` (`discount_type`),
  CONSTRAINT `quotation_discounts_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `quotation_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quotation_lines` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `quotation_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_variant_id` int(11) DEFAULT NULL,
  `product_code` varchar(100) DEFAULT NULL,
  `description` varchar(500) NOT NULL,
  `unit` varchar(20) DEFAULT 'pcs',
  `quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `base_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) DEFAULT 0.00,
  `line_number` int(11) DEFAULT NULL,
  `discount_type` enum('percent','fixed') DEFAULT 'percent',
  `discount_value` decimal(10,2) DEFAULT 0.00,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `tax_type` varchar(16) NOT NULL DEFAULT 'percent',
  `tax_value` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `weight` decimal(10,3) DEFAULT 0.000,
  `unit_weight` decimal(10,3) DEFAULT 0.000,
  `vendor_id` int(11) DEFAULT NULL,
  `cost_price` decimal(15,2) DEFAULT 0.00,
  `sale_price_currency` varchar(3) DEFAULT 'USD',
  `product_name` varchar(255) DEFAULT NULL,
  `product_image_url` varchar(500) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `weight_unit` varchar(10) DEFAULT 'kg' COMMENT 'Unit of weight: kg, g, lbs, oz',
  `display_type` varchar(20) DEFAULT NULL,
  `section_title` varchar(255) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quotation_id` (`quotation_id`)
) ENGINE=InnoDB AUTO_INCREMENT=233 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `quotation_shipping`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quotation_shipping` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `quotation_id` int(11) unsigned NOT NULL,
  `carrier` varchar(64) DEFAULT NULL,
  `service` varchar(128) DEFAULT NULL,
  `product_weight` decimal(12,3) NOT NULL DEFAULT 0.000,
  `packing_weight` decimal(12,3) NOT NULL DEFAULT 0.000,
  `box_weight` decimal(12,3) NOT NULL DEFAULT 0.000,
  `shipment_weight` decimal(12,3) NOT NULL DEFAULT 0.000,
  `shipping_method` varchar(32) DEFAULT NULL,
  `shipping_cost` decimal(12,2) DEFAULT NULL,
  `shipping_cost_currency` varchar(8) DEFAULT NULL,
  `shipping_taxable` tinyint(1) NOT NULL DEFAULT 0,
  `shipping_tax_rate` decimal(5,2) DEFAULT NULL,
  `shipping_tax_amount` decimal(12,2) DEFAULT NULL,
  `shipping_total` decimal(12,2) DEFAULT NULL,
  `show_to_customer` tinyint(1) NOT NULL DEFAULT 0,
  `show_weight_on_pdf` tinyint(1) NOT NULL DEFAULT 0,
  `actual_shipping_cost` decimal(12,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `metadata` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quotation_id` (`quotation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `quotations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quotations` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) DEFAULT NULL,
  `quote_number` varchar(50) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `issue_date` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','sent','accepted','rejected','converted') NOT NULL DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `converted_to_sales_order_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `document_discount_type` enum('percent','fixed') DEFAULT 'percent',
  `document_discount_value` decimal(10,2) DEFAULT 0.00,
  `discount_exclude_shipping` tinyint(1) NOT NULL DEFAULT 1,
  `document_tax_type` enum('percent','fixed') DEFAULT 'percent',
  `document_tax_value` decimal(10,2) DEFAULT 0.00,
  `shipping_cost` decimal(15,2) DEFAULT 0.00,
  `handling_charges` decimal(15,2) DEFAULT 0.00,
  `packaging_charges` decimal(15,2) DEFAULT 0.00,
  `insurance_cost` decimal(15,2) DEFAULT 0.00,
  `total_weight` decimal(10,3) DEFAULT 0.000,
  `exchange_rate` decimal(10,4) DEFAULT 1.0000,
  `base_currency` varchar(3) DEFAULT 'USD',
  `quote_currency` varchar(3) DEFAULT 'USD',
  `price_list_id` int(10) unsigned DEFAULT NULL,
  `discount` decimal(14,2) DEFAULT 0.00,
  `tax` decimal(14,2) DEFAULT 0.00,
  `shipping_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `customer_snapshot` text DEFAULT NULL COMMENT 'JSON snapshot of the customer address/contact used on this document',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_quotations_quote_number` (`quote_number`),
  UNIQUE KEY `idx_quotations_public_id` (`public_id`),
  KEY `customer_id` (`customer_id`),
  KEY `idx_converted_to_sales_order_id` (`converted_to_sales_order_id`)
) ENGINE=InnoDB AUTO_INCREMENT=99 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rework_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rework_records` (
  `id` int(10) unsigned NOT NULL,
  `qc_record_id` int(10) unsigned NOT NULL,
  `original_process_run_id` int(10) unsigned NOT NULL,
  `rework_process_run_id` int(10) unsigned NOT NULL,
  `quantity_reworked` int(10) unsigned NOT NULL,
  `reason` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_data_access`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_data_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `dashboard_sales_visible` tinyint(1) NOT NULL DEFAULT 1,
  `dashboard_purchases_visible` tinyint(1) NOT NULL DEFAULT 1,
  `dashboard_finance_visible` tinyint(1) NOT NULL DEFAULT 1,
  `isolate_quotations` tinyint(1) NOT NULL DEFAULT 0,
  `isolate_sales_orders` tinyint(1) NOT NULL DEFAULT 0,
  `isolate_purchase_orders` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `can_make_documents_private` tinyint(1) NOT NULL DEFAULT 0,
  `product_allowed_categories` text DEFAULT NULL,
  `product_hide_services` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_data_access_role` (`role_id`),
  KEY `idx_role_data_access_role` (`role_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int(10) unsigned NOT NULL,
  `permission_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_perm` (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=307 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(60) NOT NULL,
  `slug` varchar(60) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = built-in, cannot be deleted',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `salary_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `salary_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(10) unsigned NOT NULL,
  `period_month` date NOT NULL COMMENT 'First day of the salary month',
  `basic_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `allowances` decimal(15,2) NOT NULL DEFAULT 0.00,
  `deductions` decimal(15,2) NOT NULL DEFAULT 0.00,
  `net_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `currency_code` varchar(3) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `paid_on` date DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `source_account_id` int(11) DEFAULT NULL,
  `cheque_number` varchar(50) DEFAULT NULL,
  `cheque_image` varchar(255) DEFAULT NULL,
  `posted_entry_id` int(11) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_employee_month` (`employee_id`,`period_month`),
  KEY `period_month` (`period_month`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sale_lines_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sale_lines_cache` (
  `odoo_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `product_uom_qty` decimal(15,4) DEFAULT 0.0000,
  `qty_delivered` decimal(15,4) DEFAULT 0.0000,
  `price_unit` decimal(15,4) DEFAULT 0.0000,
  `metadata` longtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`odoo_id`),
  KEY `idx_sale_order` (`order_id`),
  KEY `idx_sale_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales_cache` (
  `odoo_id` int(11) NOT NULL,
  `name` varchar(150) DEFAULT NULL,
  `partner_id` int(11) DEFAULT NULL,
  `partner_code` varchar(150) DEFAULT NULL,
  `date_order` datetime DEFAULT NULL,
  `commitment_date` datetime DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `amount_total` decimal(15,2) DEFAULT NULL,
  `remaining_qty` decimal(15,4) DEFAULT 0.0000,
  `has_pending_po` tinyint(1) DEFAULT 0,
  `order_line_ids` longtext DEFAULT NULL,
  `metadata` longtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`odoo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_order_line_po_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales_order_line_po_map` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sales_order_id` int(11) NOT NULL,
  `sales_order_line_id` int(11) NOT NULL,
  `purchase_order_id` int(11) NOT NULL,
  `purchase_order_line_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_mapping` (`sales_order_line_id`,`purchase_order_line_id`),
  KEY `idx_so_id` (`sales_order_id`),
  KEY `idx_so_line_id` (`sales_order_line_id`),
  KEY `idx_po_id` (`purchase_order_id`),
  KEY `idx_po_line_id` (`purchase_order_line_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_order_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales_order_lines` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `sales_order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_variant_id` int(11) DEFAULT NULL,
  `description` varchar(500) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `weight_unit` varchar(10) DEFAULT 'kg' COMMENT 'Unit of weight: kg, g, lbs, oz',
  `display_type` varchar(20) DEFAULT NULL,
  `discount_value` decimal(12,4) DEFAULT 0.0000,
  `section_title` varchar(255) DEFAULT NULL,
  `tax_rate` decimal(8,4) DEFAULT 0.0000,
  `tax_type` varchar(16) DEFAULT NULL,
  `tax_amount` decimal(12,4) DEFAULT 0.0000,
  `tax_value` decimal(12,4) DEFAULT 0.0000,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `discount_amount` decimal(12,4) DEFAULT 0.0000,
  `discount_type` enum('percent','fixed') DEFAULT NULL,
  `sort_order` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_order_id` (`sales_order_id`),
  KEY `tmp_fk_product` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=150 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales_orders` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) DEFAULT NULL,
  `order_number` varchar(50) NOT NULL,
  `quotation_id` int(11) DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `order_date` date DEFAULT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','confirmed','shipped','delivered','closed','cancelled') NOT NULL DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `shipping_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(10) DEFAULT 'PKR',
  `currency_code` varchar(10) DEFAULT 'PKR',
  `document_discount_value` decimal(14,2) DEFAULT 0.00,
  `discount_exclude_shipping` tinyint(1) DEFAULT 0,
  `discount` decimal(14,2) DEFAULT 0.00,
  `document_discount_type` enum('percent','fixed') DEFAULT NULL,
  `customer_snapshot` text DEFAULT NULL COMMENT 'JSON snapshot of the customer address/contact used on this document',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sales_orders_order_number` (`order_number`),
  UNIQUE KEY `idx_sales_orders_public_id` (`public_id`),
  KEY `customer_id` (`customer_id`)
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `scrap_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scrap_records` (
  `id` int(10) unsigned NOT NULL,
  `process_run_id` int(10) unsigned NOT NULL,
  `quantity_scrapped` int(10) unsigned NOT NULL,
  `reason` text NOT NULL,
  `estimated_cost` decimal(10,2) DEFAULT 0.00,
  `actual_cost` decimal(10,2) DEFAULT 0.00,
  `recorded_by` int(10) unsigned DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `security_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `security_settings` (
  `id` int(11) NOT NULL,
  `backdate_password_hash` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sequences` (
  `name` varchar(100) NOT NULL,
  `last_value` bigint(20) unsigned NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(128) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `timestamp` int(10) unsigned DEFAULT NULL,
  `data` blob DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ci_sessions_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shipping_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shipping_services` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `carrier` varchar(128) NOT NULL,
  `vendor_id` int(10) unsigned DEFAULT NULL,
  `service_name` varchar(128) NOT NULL,
  `min_weight` decimal(10,3) NOT NULL DEFAULT 0.000,
  `base_rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `rate_per_kg` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `cost_pkr` decimal(12,2) NOT NULL DEFAULT 0.00,
  `account_number` varchar(100) DEFAULT NULL,
  `currency` varchar(8) NOT NULL DEFAULT 'USD',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `metadata` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `product_id` int(10) unsigned DEFAULT NULL,
  `rate_per_kg_pkr` decimal(12,4) DEFAULT 0.0000,
  `base_rate_pkr` decimal(12,2) DEFAULT 0.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `states`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `states` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `country_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `country_id` (`country_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4129 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `step_execution_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `step_execution_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `step_id` int(11) NOT NULL,
  `qc_required` tinyint(1) DEFAULT 0,
  `qc_approval_required` tinyint(1) DEFAULT 0,
  `advance_enabled` tinyint(1) DEFAULT 0,
  `qty_variance_threshold_percent` decimal(5,2) DEFAULT 5.00,
  `allow_partial_overlap` tinyint(1) DEFAULT 1,
  `min_completed_percent_for_next` decimal(5,2) DEFAULT 0.00,
  `max_rework_cycles` int(11) DEFAULT 2,
  `lock_after_bill` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_step_id` (`step_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `step_execution_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `step_execution_options` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `step_id` int(10) unsigned DEFAULT NULL,
  `execution_type` enum('vendor','inhouse') DEFAULT NULL,
  `vendor_id` int(10) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `step_key` varchar(100) DEFAULT NULL,
  `option_label` varchar(255) DEFAULT NULL,
  `option_value` text DEFAULT NULL,
  `service_price` decimal(15,4) DEFAULT NULL,
  `service_unit` varchar(20) DEFAULT 'per_item',
  `currency` varchar(3) DEFAULT 'PKR',
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_step_execution_options_step_id` (`step_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_adjustment_audit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_adjustment_audit` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `batch_id` int(10) unsigned NOT NULL,
  `movement_id` int(10) unsigned DEFAULT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `variant_id` int(10) unsigned DEFAULT NULL,
  `warehouse_id` int(10) unsigned DEFAULT NULL,
  `location_id` int(10) unsigned DEFAULT NULL,
  `mode` varchar(20) NOT NULL,
  `action_kind` varchar(30) NOT NULL,
  `old_balance` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `target_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `qty_change` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `reason_code` varchar(40) DEFAULT NULL,
  `reason_text` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_saa_batch` (`batch_id`),
  KEY `idx_saa_movement` (`movement_id`),
  KEY `idx_saa_product` (`product_id`),
  KEY `idx_saa_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_adjustment_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_adjustment_batches` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `adjustment_type` varchar(50) NOT NULL DEFAULT 'adjustment',
  `mode` varchar(20) NOT NULL DEFAULT 'add',
  `notes` text DEFAULT NULL,
  `warehouse_id` int(10) unsigned DEFAULT NULL,
  `location_id` int(10) unsigned DEFAULT NULL,
  `line_count` int(11) NOT NULL DEFAULT 0,
  `total_estimated_value` decimal(18,2) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_balances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_balances` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(11) unsigned NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `item_key` varchar(64) DEFAULT NULL,
  `warehouse_id` int(11) unsigned DEFAULT 1,
  `location_id` int(11) unsigned NOT NULL,
  `quantity` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_warehouse_location_variant` (`product_id`,`warehouse_id`,`location_id`,`variant_id`),
  UNIQUE KEY `ux_sb_item_wh_loc` (`item_key`,`warehouse_id`,`location_id`),
  KEY `product_id` (`product_id`),
  KEY `location_id` (`location_id`),
  KEY `fk_stock_balances_warehouse` (`warehouse_id`),
  KEY `idx_variant_id` (`variant_id`),
  KEY `idx_sb_variant` (`variant_id`),
  KEY `idx_sb_itemkey` (`item_key`),
  CONSTRAINT `fk_stock_balances_location` FOREIGN KEY (`location_id`) REFERENCES `warehouse_locations` (`id`),
  CONSTRAINT `fk_stock_balances_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=90 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_locations` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `parent_id` int(11) unsigned DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_movements` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(11) unsigned NOT NULL,
  `variant_id` int(10) unsigned DEFAULT NULL,
  `item_key` varchar(32) DEFAULT NULL,
  `warehouse_id` int(11) unsigned DEFAULT 1,
  `location_id` int(11) unsigned NOT NULL,
  `qty_change` decimal(18,4) NOT NULL,
  `unit_cost` decimal(18,4) DEFAULT NULL,
  `stock_source` varchar(30) DEFAULT NULL,
  `possible_vendor_id` int(10) unsigned DEFAULT NULL,
  `movement_type` varchar(50) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `location_id` (`location_id`),
  KEY `movement_type` (`movement_type`),
  KEY `reference_type` (`reference_type`),
  KEY `idx_sm_warehouse` (`warehouse_id`),
  KEY `idx_sm_variant` (`variant_id`),
  KEY `idx_sm_itemkey` (`item_key`),
  CONSTRAINT `fk_stock_movements_location` FOREIGN KEY (`location_id`) REFERENCES `warehouse_locations` (`id`),
  CONSTRAINT `fk_stock_movements_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=162 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subcontract_issue_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subcontract_issue_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `issue_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `quantity` decimal(15,3) NOT NULL DEFAULT 0.000,
  PRIMARY KEY (`id`),
  KEY `issue_id` (`issue_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subcontract_issues`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subcontract_issues` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `issue_number` varchar(100) DEFAULT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `issued_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subcontract_order_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subcontract_order_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `subcontract_order_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL COMMENT 'storable product being sent out',
  `variant_id` int(10) unsigned DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `qty_sent` decimal(15,3) NOT NULL DEFAULT 0.000,
  `qty_received` decimal(15,3) NOT NULL DEFAULT 0.000,
  `qty_scrap` decimal(15,3) NOT NULL DEFAULT 0.000,
  `warehouse_id` int(10) unsigned DEFAULT NULL,
  `location_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_scl_order` (`subcontract_order_id`),
  KEY `idx_scl_product` (`product_id`),
  KEY `idx_scl_variant` (`variant_id`),
  CONSTRAINT `fk_scl_order` FOREIGN KEY (`subcontract_order_id`) REFERENCES `subcontract_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subcontract_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subcontract_orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) DEFAULT NULL,
  `order_number` varchar(50) NOT NULL,
  `vendor_id` int(10) unsigned NOT NULL COMMENT 'vendor performing the service',
  `service_product_id` int(10) unsigned NOT NULL COMMENT 'service-type product being purchased',
  `service_variant_id` int(10) unsigned DEFAULT NULL,
  `po_id` int(10) unsigned DEFAULT NULL COMMENT 'optional link to purchase order',
  `status` varchar(20) NOT NULL DEFAULT 'draft' COMMENT 'draft|confirmed|issued|partial_return|done|cancelled',
  `quantity` decimal(15,3) NOT NULL DEFAULT 0.000 COMMENT 'total qty of service units',
  `unit_price` decimal(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'price per service unit',
  `currency` varchar(3) NOT NULL DEFAULT 'PKR',
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `issued_date` date DEFAULT NULL COMMENT 'date materials were sent out',
  `expected_return_date` date DEFAULT NULL,
  `actual_return_date` date DEFAULT NULL,
  `warehouse_id` int(10) unsigned DEFAULT NULL COMMENT 'source warehouse for material issue',
  `location_id` int(10) unsigned DEFAULT NULL COMMENT 'source location for material issue',
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sc_order_number` (`order_number`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_sc_vendor` (`vendor_id`),
  KEY `idx_sc_service_product` (`service_product_id`),
  KEY `idx_sc_status` (`status`),
  KEY `idx_sc_po` (`po_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `system_backup_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_backup_jobs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `public_id` char(36) NOT NULL,
  `job_type` varchar(20) NOT NULL DEFAULT 'manual',
  `backup_type` varchar(20) NOT NULL DEFAULT 'full',
  `status` varchar(20) NOT NULL DEFAULT 'queued',
  `environment_name` varchar(50) NOT NULL DEFAULT 'production',
  `app_root` varchar(255) DEFAULT NULL,
  `db_name` varchar(128) DEFAULT NULL,
  `archive_path` varchar(500) DEFAULT NULL,
  `archive_name` varchar(255) DEFAULT NULL,
  `archive_size_bytes` bigint(20) DEFAULT NULL,
  `archive_sha256` char(64) DEFAULT NULL,
  `manifest_path` varchar(500) DEFAULT NULL,
  `health_status` varchar(20) NOT NULL DEFAULT 'pending',
  `health_details_json` longtext DEFAULT NULL,
  `schedule_id` bigint(20) DEFAULT NULL,
  `initiated_by` int(11) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_system_backup_jobs_public_id` (`public_id`),
  KEY `idx_system_backup_jobs_status` (`status`),
  KEY `idx_system_backup_jobs_schedule` (`schedule_id`),
  KEY `idx_system_backup_jobs_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `system_backup_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_backup_schedules` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `backup_type` varchar(20) NOT NULL DEFAULT 'full',
  `frequency_type` varchar(20) NOT NULL DEFAULT 'daily',
  `interval_minutes` int(11) DEFAULT NULL,
  `day_of_week` tinyint(4) DEFAULT NULL,
  `time_of_day` char(5) DEFAULT NULL,
  `retention_count` int(11) NOT NULL DEFAULT 5,
  `last_run_at` datetime DEFAULT NULL,
  `next_run_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_system_backup_schedules_active` (`is_active`),
  KEY `idx_system_backup_schedules_next_run` (`next_run_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `id` int(10) unsigned NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `system_sync_environments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_sync_environments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `app_path` varchar(500) NOT NULL,
  `db_host` varchar(120) NOT NULL DEFAULT '127.0.0.1',
  `db_port` int(11) NOT NULL DEFAULT 3306,
  `db_name` varchar(120) NOT NULL,
  `db_user` varchar(120) NOT NULL DEFAULT 'root',
  `db_password` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_system_sync_env_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `system_sync_scans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_sync_scans` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `public_id` char(36) NOT NULL,
  `source_env_id` int(11) NOT NULL,
  `destination_env_id` int(11) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'scanned',
  `summary_json` longtext DEFAULT NULL,
  `safe_operations_json` longtext DEFAULT NULL,
  `report_path` varchar(500) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `applied_by` int(11) DEFAULT NULL,
  `applied_at` datetime DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_system_sync_scans_public_id` (`public_id`),
  KEY `idx_system_sync_scans_status` (`status`),
  KEY `idx_system_sync_scans_source_dest` (`source_env_id`,`destination_env_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tags` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tags_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tax_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_codes` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `rate` decimal(9,4) NOT NULL DEFAULT 0.0000,
  `is_compound` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_mfa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_mfa` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `mfa_secret` varchar(255) DEFAULT NULL COMMENT 'TOTP secret (encrypted)',
  `mfa_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `recovery_codes` text DEFAULT NULL COMMENT 'JSON array of hashed recovery codes',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mfa_user` (`user_id`),
  CONSTRAINT `user_mfa_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `role_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_role` (`user_id`,`role_id`),
  KEY `role_id` (`role_id`)
) ENGINE=InnoDB AUTO_INCREMENT=152 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `role` enum('admin','planner','production','qc','stores','accounts','viewer') NOT NULL DEFAULT 'viewer',
  `role_id` int(10) unsigned DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `failed_login_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `password_changed_at` timestamp NULL DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `documents_private` tinyint(1) NOT NULL DEFAULT 0,
  `can_make_documents_private` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `variant_exclusion_conditions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `variant_exclusion_conditions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rule_id` int(10) unsigned NOT NULL,
  `attribute_id` int(10) unsigned NOT NULL,
  `attribute_value_id` int(10) unsigned DEFAULT NULL COMMENT 'ID of the specific attribute value, null = all values for this attribute',
  `attribute_value_name` varchar(255) DEFAULT NULL COMMENT 'Denormalized value name for display/search',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `rule_id` (`rule_id`),
  KEY `attribute_id` (`attribute_id`),
  KEY `rule_id_attribute_id` (`rule_id`,`attribute_id`),
  CONSTRAINT `variant_exclusion_conditions_attribute_id_foreign` FOREIGN KEY (`attribute_id`) REFERENCES `product_attributes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `variant_exclusion_conditions_rule_id_foreign` FOREIGN KEY (`rule_id`) REFERENCES `variant_exclusion_rules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `variant_exclusion_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `variant_exclusion_rules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL COMMENT 'Exclusion rule name (e.g., "Feather Only for Flat Billet")',
  `description` text DEFAULT NULL COMMENT 'Detailed explanation of the exclusion rule',
  `rule_type` enum('include','exclude') NOT NULL DEFAULT 'exclude' COMMENT 'include = only generate for these values | exclude = skip these values',
  `is_active` tinyint(4) NOT NULL DEFAULT 1 COMMENT 'Whether this rule is currently applied',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `is_active` (`is_active`),
  KEY `product_id_is_active` (`product_id`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `variant_inventory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `variant_inventory` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `variant_id` int(11) unsigned NOT NULL,
  `warehouse_id` int(11) unsigned DEFAULT NULL,
  `quantity` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `reserved` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `variant_id_warehouse_id` (`variant_id`,`warehouse_id`),
  KEY `variant_id` (`variant_id`),
  KEY `warehouse_id` (`warehouse_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11767 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_advance_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_advance_adjustments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `advance_cheque_id` int(11) DEFAULT NULL,
  `advance_payment_id` int(11) DEFAULT NULL,
  `vendor_bill_id` int(11) NOT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `adjustment_date` date NOT NULL,
  `posted_entry_id` int(11) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vendor` (`vendor_id`),
  KEY `idx_cheque` (`advance_cheque_id`),
  KEY `idx_bill` (`vendor_bill_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_advances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_advances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `sales_order_id` int(11) DEFAULT NULL,
  `send_note_id` int(11) DEFAULT NULL,
  `amount` decimal(12,4) DEFAULT NULL,
  `currency` varchar(3) DEFAULT 'PKR',
  `advance_date` date DEFAULT NULL,
  `bill_id` int(11) DEFAULT NULL,
  `status` enum('draft','approved','paid','reconciled','reversed') DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vendor_id` (`vendor_id`),
  KEY `idx_send_note_id` (`send_note_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_bill_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_bill_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `vendor_bill_id` int(10) unsigned NOT NULL,
  `po_line_id` int(11) unsigned DEFAULT NULL,
  `product_id` int(11) unsigned DEFAULT NULL,
  `variant_id` int(11) unsigned DEFAULT NULL,
  `qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `unit_price` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `line_total` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `created_at` datetime DEFAULT NULL,
  `processing_record_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vendor_bill_id` (`vendor_bill_id`),
  KEY `idx_po_line_id` (`po_line_id`),
  KEY `idx_product_id` (`product_id`),
  KEY `fk_vendor_bill_lines_processing_record_id` (`processing_record_id`),
  CONSTRAINT `fk_vendor_bill_lines_processing_record_id` FOREIGN KEY (`processing_record_id`) REFERENCES `processing_records` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_bills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_bills` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) DEFAULT NULL,
  `vendor_id` int(10) unsigned NOT NULL,
  `bill_number` varchar(50) DEFAULT NULL,
  `bill_date` datetime DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `total_amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `tax_amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `discount_amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `balance` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `status` enum('draft','confirmed','cancelled','paid') NOT NULL DEFAULT 'draft',
  `currency_code` varchar(10) DEFAULT 'PKR',
  `po_id` int(11) unsigned DEFAULT NULL,
  `posted_entry_id` int(11) unsigned DEFAULT NULL,
  `based_on` enum('po_qty','grn_qty','manual','po_over_receipt','po_qty_adjustment') NOT NULL DEFAULT 'manual',
  `notes` text DEFAULT NULL,
  `created_by` int(11) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bill_number` (`bill_number`),
  UNIQUE KEY `idx_vendor_bills_public_id` (`public_id`),
  KEY `idx_vendor_id` (`vendor_id`),
  KEY `idx_status` (`status`),
  KEY `idx_po_id` (`po_id`),
  KEY `idx_vendor_bills_po_id` (`po_id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `name` varchar(190) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `cnic` varchar(25) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vendor` (`vendor_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_gatepasses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_gatepasses` (
  `id` int(10) unsigned NOT NULL,
  `gatepass_number` varchar(50) NOT NULL,
  `process_run_id` int(10) unsigned NOT NULL,
  `vendor_id` int(10) unsigned NOT NULL,
  `type` enum('out','in') NOT NULL,
  `quantity_sent` int(10) unsigned DEFAULT 0,
  `quantity_received` int(10) unsigned DEFAULT 0,
  `dispatch_date` date DEFAULT NULL,
  `return_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `gatepass_number` (`gatepass_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_ledger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_ledger` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `transaction_date` date DEFAULT NULL,
  `transaction_type` enum('send','receive','bill','advance','payment','variance_adjustment','reversal') NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `debit` decimal(14,4) DEFAULT 0.0000,
  `credit` decimal(14,4) DEFAULT 0.0000,
  `balance` decimal(14,4) DEFAULT NULL,
  `currency` varchar(3) DEFAULT 'PKR',
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vendor_id` (`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_payment_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_payment_allocations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_id` int(11) NOT NULL,
  `vendor_bill_id` int(11) DEFAULT NULL,
  `purchase_order_id` int(11) DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `amount_allocated` decimal(18,2) NOT NULL DEFAULT 0.00,
  `advance_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `allocated_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_payment` (`payment_id`),
  KEY `idx_po` (`purchase_order_id`),
  KEY `idx_vendor_bill` (`vendor_bill_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `po_id` int(11) DEFAULT NULL,
  `cheque_id` int(11) DEFAULT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(20) NOT NULL DEFAULT 'cash',
  `payment_type` varchar(20) NOT NULL DEFAULT 'settlement',
  `currency_code` varchar(10) NOT NULL DEFAULT 'PKR',
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `advance_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `source_account_id` int(11) DEFAULT NULL,
  `posted_entry_id` int(11) DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `memo` varchar(255) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `cheque_delivery_type` varchar(30) DEFAULT NULL,
  `cheque_number` varchar(50) DEFAULT NULL,
  `cheque_notes` varchar(500) DEFAULT NULL,
  `cheque_payee_name` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vendor` (`vendor_id`),
  KEY `idx_payment_method` (`payment_method`),
  KEY `idx_status` (`status`),
  KEY `idx_source_account` (`source_account_id`),
  KEY `idx_posted_entry` (`posted_entry_id`),
  KEY `idx_vendor_payments_po_id` (`po_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_qc_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_qc_records` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `receive_item_id` int(10) unsigned NOT NULL,
  `check_name` varchar(255) NOT NULL,
  `status` enum('pass','fail') NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vendor_qc_records_receive_item` (`receive_item_id`),
  KEY `idx_vendor_qc_records_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_receive_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_receive_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `receive_note_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `qty_received` decimal(10,4) NOT NULL,
  `qty_accepted` decimal(10,4) NOT NULL,
  `qty_rejected` decimal(10,4) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vendor_receive_items_receive_note` (`receive_note_id`),
  KEY `idx_vendor_receive_items_product` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_receive_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_receive_notes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(50) NOT NULL,
  `vendor_id` int(10) unsigned NOT NULL,
  `send_note_id` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `variance_amount` decimal(10,4) DEFAULT NULL,
  `variance_approved` tinyint(1) DEFAULT 0,
  `bill_id` int(11) DEFAULT NULL,
  `qc_required` tinyint(1) DEFAULT NULL,
  `qc_passed` tinyint(1) DEFAULT NULL,
  `rework_cycle` int(11) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_vendor_receive_notes_reference_no` (`reference_no`),
  KEY `idx_vendor_receive_notes_vendor` (`vendor_id`),
  KEY `idx_vendor_receive_notes_send_note` (`send_note_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_receive_rejections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_receive_rejections` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rejection_ref` varchar(50) NOT NULL,
  `receive_note_id` int(10) unsigned NOT NULL,
  `receive_item_id` int(10) unsigned NOT NULL,
  `send_note_id` int(10) unsigned DEFAULT NULL,
  `vendor_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `sales_order_id` int(10) unsigned DEFAULT NULL,
  `qty_rejected` decimal(10,4) NOT NULL,
  `unit` varchar(20) NOT NULL DEFAULT 'pcs',
  `action_type` varchar(40) NOT NULL DEFAULT 'hold',
  `status` varchar(40) NOT NULL DEFAULT 'on_hold',
  `rejection_reason_id` int(10) unsigned DEFAULT NULL,
  `rejection_reason_text` varchar(255) DEFAULT NULL,
  `custom_reason_tag` varchar(80) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `replacement_due_date` date DEFAULT NULL,
  `handled_by_user_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rejection_ref` (`rejection_ref`),
  KEY `vendor_receive_rejections_receive_note_id_foreign` (`receive_note_id`),
  KEY `vendor_receive_rejections_product_id_foreign` (`product_id`),
  KEY `receive_item_id` (`receive_item_id`),
  KEY `send_note_id` (`send_note_id`),
  KEY `vendor_id` (`vendor_id`),
  KEY `status` (`status`),
  CONSTRAINT `vendor_receive_rejections_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vendor_receive_rejections_receive_item_id_foreign` FOREIGN KEY (`receive_item_id`) REFERENCES `vendor_receive_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `vendor_receive_rejections_receive_note_id_foreign` FOREIGN KEY (`receive_note_id`) REFERENCES `vendor_receive_notes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `vendor_receive_rejections_send_note_id_foreign` FOREIGN KEY (`send_note_id`) REFERENCES `vendor_send_notes` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `vendor_receive_rejections_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_send_note_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_send_note_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `send_note_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `qty` decimal(10,4) NOT NULL,
  `unit_price` decimal(18,4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vendor_send_note_items_send_note` (`send_note_id`),
  KEY `idx_vendor_send_note_items_product` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_send_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_send_notes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(50) NOT NULL,
  `vendor_id` int(10) unsigned NOT NULL,
  `step_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `sales_order_id` int(10) unsigned DEFAULT NULL,
  `sales_order_line_id` int(10) unsigned DEFAULT NULL,
  `qty` decimal(10,4) NOT NULL,
  `from_location_id` int(10) unsigned NOT NULL,
  `to_location_id` int(10) unsigned NOT NULL,
  `status` enum('draft','sent','completed','cancelled') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `split_approved_by` int(11) DEFAULT NULL,
  `split_approval_date` timestamp NULL DEFAULT NULL,
  `advance_id` int(11) DEFAULT NULL,
  `qty_sent_date` timestamp NULL DEFAULT NULL,
  `locked_for_edit` tinyint(1) DEFAULT 0,
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_vendor_send_notes_reference_no` (`reference_no`),
  KEY `idx_vendor_send_notes_vendor` (`vendor_id`),
  KEY `idx_vendor_send_notes_step` (`step_id`),
  KEY `idx_vendor_send_notes_product` (`product_id`),
  KEY `idx_vendor_send_notes_from_loc` (`from_location_id`),
  KEY `idx_vendor_send_notes_to_loc` (`to_location_id`),
  KEY `idx_vendor_send_notes_so_id` (`sales_order_id`),
  KEY `idx_vendor_send_notes_so_line_id` (`sales_order_line_id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_service_bills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_service_bills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(50) DEFAULT NULL,
  `vendor_id` int(11) NOT NULL,
  `sales_order_id` int(11) DEFAULT NULL,
  `send_note_id` int(11) DEFAULT NULL,
  `receive_note_id` int(11) DEFAULT NULL,
  `billing_basis` enum('sent','received','accepted','advance') DEFAULT 'received',
  `billed_qty` decimal(10,4) DEFAULT NULL,
  `unit_rate` decimal(12,4) DEFAULT NULL,
  `total_amount` decimal(14,4) DEFAULT NULL,
  `currency` varchar(3) DEFAULT 'PKR',
  `advance_amount` decimal(12,4) DEFAULT 0.0000,
  `advance_id` int(11) DEFAULT NULL,
  `balance_due` decimal(14,4) DEFAULT NULL,
  `status` enum('draft','approved','sent','paid','reconciled','reversed') DEFAULT 'draft',
  `approval_by` int(11) DEFAULT NULL,
  `approval_date` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_no` (`reference_no`),
  KEY `idx_vendor_id` (`vendor_id`),
  KEY `idx_send_note_id` (`send_note_id`),
  KEY `idx_receive_note_id` (`receive_note_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_service_charges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_service_charges` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `vendor_send_note_id` int(10) unsigned NOT NULL,
  `step_id` int(10) unsigned NOT NULL,
  `service_description` varchar(255) DEFAULT NULL,
  `qty` decimal(10,4) NOT NULL DEFAULT 1.0000,
  `unit_price` decimal(15,4) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'PKR',
  `total_amount` decimal(15,4) DEFAULT NULL,
  `status` enum('pending','charged','paid','cancelled') NOT NULL DEFAULT 'pending',
  `vendor_invoice_id` int(10) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_vs_charge_invoice` (`vendor_invoice_id`),
  KEY `vendor_send_note_id` (`vendor_send_note_id`),
  KEY `step_id` (`step_id`),
  KEY `status` (`status`),
  CONSTRAINT `fk_vs_charge_invoice` FOREIGN KEY (`vendor_invoice_id`) REFERENCES `vendor_bills` (`id`) ON UPDATE SET NULL,
  CONSTRAINT `fk_vs_charge_sendnote` FOREIGN KEY (`vendor_send_note_id`) REFERENCES `vendor_send_notes` (`id`),
  CONSTRAINT `fk_vs_charge_step` FOREIGN KEY (`step_id`) REFERENCES `preparation_steps` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendors` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) DEFAULT NULL,
  `vendor_code` varchar(50) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_vendor_code` (`vendor_code`),
  UNIQUE KEY `idx_vendors_public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warehouse_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `warehouse_locations` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `warehouse_id` int(11) unsigned NOT NULL,
  `name` varchar(191) NOT NULL,
  `parent_id` int(11) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `warehouse_id` (`warehouse_id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warehouses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `warehouses` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `work_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `work_order_items` (
  `id` int(10) unsigned NOT NULL,
  `work_order_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `quantity_ordered` int(10) unsigned NOT NULL,
  `quantity_completed` int(10) unsigned DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `work_order_id` (`work_order_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `work_order_process_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `work_order_process_runs` (
  `id` int(10) unsigned NOT NULL,
  `work_order_id` int(10) unsigned NOT NULL,
  `process_id` int(10) unsigned NOT NULL,
  `run_number` int(11) NOT NULL DEFAULT 1,
  `quantity_in` int(10) unsigned NOT NULL,
  `quantity_out` int(10) unsigned DEFAULT 0,
  `quantity_scrap` int(10) unsigned DEFAULT 0,
  `quantity_pending` int(10) unsigned DEFAULT 0,
  `status` enum('pending','in_progress','completed','on_hold','cancelled') DEFAULT 'pending',
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `operator_id` int(10) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `work_order_id` (`work_order_id`),
  KEY `process_id` (`process_id`),
  KEY `operator_id` (`operator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `work_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `work_orders` (
  `id` int(10) unsigned NOT NULL,
  `wo_number` varchar(50) NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `quantity_ordered` int(10) unsigned NOT NULL,
  `quantity_completed` int(10) unsigned DEFAULT 0,
  `due_date` date NOT NULL,
  `status` enum('planned','in_progress','on_hold','completed','cancelled') DEFAULT 'planned',
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `wo_number` (`wo_number`),
  KEY `product_id` (`product_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_work_orders_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

