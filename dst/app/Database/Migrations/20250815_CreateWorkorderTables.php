<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateWorkorderTables extends Migration
{
    public function up()
    {
        $sql = <<<'SQL'
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS `work_orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(100) NOT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'planned',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `work_order_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `work_order_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `planned_qty` decimal(12,3) NOT NULL DEFAULT '0.000',
  `unit` varchar(20) DEFAULT NULL,
  `sequence_order` int(11) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_work_order` (`work_order_id`),
  KEY `idx_product` (`product_id`),
  CONSTRAINT `fk_woitem_workorder` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `process_batches` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `work_order_item_id` int(10) unsigned NOT NULL,
  `process_id` int(10) unsigned NOT NULL,
  `batch_code` varchar(100) DEFAULT NULL,
  `planned_qty` decimal(12,3) NOT NULL DEFAULT '0.000',
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_woitem` (`work_order_item_id`),
  KEY `idx_process` (`process_id`),
  CONSTRAINT `fk_batch_woitem` FOREIGN KEY (`work_order_item_id`) REFERENCES `work_order_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `process_batch_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `process_batch_id` int(10) unsigned NOT NULL,
  `log_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `accepted_qty` decimal(12,3) NOT NULL DEFAULT '0.000',
  `repaired_qty` decimal(12,3) NOT NULL DEFAULT '0.000',
  `rejected_qty` decimal(12,3) NOT NULL DEFAULT '0.000',
  `operator_id` int(10) unsigned DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_batch` (`process_batch_id`),
  CONSTRAINT `fk_log_batch` FOREIGN KEY (`process_batch_id`) REFERENCES `process_batches`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
SQL;

        // Run SQL safely
        $this->db->query($sql);
    }

    public function down()
    {
        // Drop in reverse order to avoid FK constraint issues
        $this->forge->dropTable('process_batch_logs', true);
        $this->forge->dropTable('process_batches', true);
        $this->forge->dropTable('work_order_items', true);
        $this->forge->dropTable('work_orders', true);
    }
}
