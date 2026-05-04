CREATE TABLE IF NOT EXISTS `vendor_receive_notes` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference_no` VARCHAR(50) NOT NULL,
  `vendor_id` INT(10) UNSIGNED NOT NULL,
  `send_note_id` INT(10) UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_vendor_receive_notes_reference_no` (`reference_no`),
  KEY `idx_vendor_receive_notes_vendor` (`vendor_id`),
  KEY `idx_vendor_receive_notes_send_note` (`send_note_id`),
  CONSTRAINT `fk_vendor_receive_notes_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_vendor_receive_notes_send_note` FOREIGN KEY (`send_note_id`) REFERENCES `vendor_send_notes` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `vendor_receive_items` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `receive_note_id` INT(10) UNSIGNED NOT NULL,
  `product_id` INT(10) UNSIGNED NOT NULL,
  `qty_received` DECIMAL(10,4) NOT NULL,
  `qty_accepted` DECIMAL(10,4) NOT NULL,
  `qty_rejected` DECIMAL(10,4) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vendor_receive_items_receive_note` (`receive_note_id`),
  KEY `idx_vendor_receive_items_product` (`product_id`),
  CONSTRAINT `fk_vendor_receive_items_receive_note` FOREIGN KEY (`receive_note_id`) REFERENCES `vendor_receive_notes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_vendor_receive_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `vendor_qc_records` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `receive_item_id` INT(10) UNSIGNED NOT NULL,
  `check_name` VARCHAR(255) NOT NULL,
  `status` ENUM('pass','fail') NOT NULL,
  `remarks` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vendor_qc_records_receive_item` (`receive_item_id`),
  KEY `idx_vendor_qc_records_status` (`status`),
  CONSTRAINT `fk_vendor_qc_records_receive_item` FOREIGN KEY (`receive_item_id`) REFERENCES `vendor_receive_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `qc_rejection_reasons` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_qc_rejection_reasons_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `qc_rejection_reasons` (`name`, `is_active`)
SELECT * FROM (
  SELECT 'Visual defect', 1
  UNION ALL SELECT 'Dimension mismatch', 1
  UNION ALL SELECT 'Material defect', 1
  UNION ALL SELECT 'Performance failure', 1
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `qc_rejection_reasons` LIMIT 1);
