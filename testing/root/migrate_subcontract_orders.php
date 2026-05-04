<?php
/**
 * Migration: Create subcontract_orders and subcontract_order_lines tables.
 * Safe to re-run.
 */
$pdo = new PDO('mysql:host=localhost;dbname=corelynk_db', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$changes = [];

// 1. subcontract_orders — master record
$exists = $pdo->query("SHOW TABLES LIKE 'subcontract_orders'")->fetch();
if (!$exists) {
    $pdo->exec("
        CREATE TABLE `subcontract_orders` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `order_number` VARCHAR(50) NOT NULL,
            `vendor_id` INT UNSIGNED NOT NULL COMMENT 'vendor performing the service',
            `service_product_id` INT UNSIGNED NOT NULL COMMENT 'service-type product being purchased',
            `service_variant_id` INT UNSIGNED NULL DEFAULT NULL,
            `po_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'optional link to purchase order',
            `status` VARCHAR(20) NOT NULL DEFAULT 'draft' COMMENT 'draft|confirmed|issued|partial_return|done|cancelled',
            `quantity` DECIMAL(15,3) NOT NULL DEFAULT 0.000 COMMENT 'total qty of service units',
            `unit_price` DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'price per service unit',
            `currency` VARCHAR(3) NOT NULL DEFAULT 'PKR',
            `total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `issued_date` DATE NULL DEFAULT NULL COMMENT 'date materials were sent out',
            `expected_return_date` DATE NULL DEFAULT NULL,
            `actual_return_date` DATE NULL DEFAULT NULL,
            `warehouse_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'source warehouse for material issue',
            `location_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'source location for material issue',
            `notes` TEXT NULL,
            `created_by` INT UNSIGNED NULL DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_sc_order_number` (`order_number`),
            KEY `idx_sc_vendor` (`vendor_id`),
            KEY `idx_sc_service_product` (`service_product_id`),
            KEY `idx_sc_status` (`status`),
            KEY `idx_sc_po` (`po_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $changes[] = 'Created table subcontract_orders';
} else {
    $changes[] = 'subcontract_orders already exists';
}

// 2. subcontract_order_lines — materials sent to vendor for processing
$exists = $pdo->query("SHOW TABLES LIKE 'subcontract_order_lines'")->fetch();
if (!$exists) {
    $pdo->exec("
        CREATE TABLE `subcontract_order_lines` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `subcontract_order_id` INT UNSIGNED NOT NULL,
            `product_id` INT UNSIGNED NOT NULL COMMENT 'storable product being sent out',
            `variant_id` INT UNSIGNED NULL DEFAULT NULL,
            `description` VARCHAR(500) NULL,
            `qty_sent` DECIMAL(15,3) NOT NULL DEFAULT 0.000,
            `qty_received` DECIMAL(15,3) NOT NULL DEFAULT 0.000,
            `qty_scrap` DECIMAL(15,3) NOT NULL DEFAULT 0.000,
            `warehouse_id` INT UNSIGNED NULL DEFAULT NULL,
            `location_id` INT UNSIGNED NULL DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_scl_order` (`subcontract_order_id`),
            KEY `idx_scl_product` (`product_id`),
            KEY `idx_scl_variant` (`variant_id`),
            CONSTRAINT `fk_scl_order` FOREIGN KEY (`subcontract_order_id`) REFERENCES `subcontract_orders` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $changes[] = 'Created table subcontract_order_lines';
} else {
    $changes[] = 'subcontract_order_lines already exists';
}

// 3. Add variant_id to stock_movements if missing (needed for variant-level tracking)
$col = $pdo->query("SHOW COLUMNS FROM `stock_movements` LIKE 'variant_id'")->fetch();
if (!$col) {
    $pdo->exec("ALTER TABLE `stock_movements` ADD COLUMN `variant_id` INT UNSIGNED NULL DEFAULT NULL AFTER `product_id`");
    $pdo->exec("ALTER TABLE `stock_movements` ADD INDEX `idx_sm_variant` (`variant_id`)");
    $changes[] = 'Added stock_movements.variant_id';
} else {
    $changes[] = 'stock_movements.variant_id already exists';
}

// 4. Sequence for subcontract order numbers
try {
    $exists = $pdo->query("SELECT 1 FROM `sequences` WHERE `code` = 'SC'")->fetch();
    if (!$exists) {
        $pdo->exec("INSERT INTO `sequences` (`code`, `prefix`, `next_number`, `padding`) VALUES ('SC', 'SC-', 1, 5) ON DUPLICATE KEY UPDATE code=code");
        $changes[] = 'Added sequence SC for subcontract orders';
    } else {
        $changes[] = 'Sequence SC already exists';
    }
} catch (Exception $e) {
    // sequences table may have different structure, handle gracefully
    $changes[] = 'Skipped sequence insert: ' . $e->getMessage();
}

echo "=== Subcontract Orders Migration ===\n";
foreach ($changes as $c) {
    echo "  [OK] $c\n";
}
echo "Done.\n";
