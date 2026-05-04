<?php
/**
 * Phase-2: Create sales_order_line_po_map table
 * 
 * Purpose: Track which PO lines were created from which SO lines
 * This prevents duplicate PO creation and enables traceability
 */

require 'vendor/autoload.php';

$db = \Config\Database::connect();

echo "Creating sales_order_line_po_map table...\n";

$sql = "
CREATE TABLE IF NOT EXISTS `sales_order_line_po_map` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sales_order_id` INT UNSIGNED NOT NULL,
    `sales_order_line_id` INT UNSIGNED NOT NULL,
    `purchase_order_id` INT UNSIGNED NOT NULL,
    `purchase_order_line_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX `idx_so_id` (`sales_order_id`),
    INDEX `idx_so_line_id` (`sales_order_line_id`),
    INDEX `idx_po_id` (`purchase_order_id`),
    INDEX `idx_po_line_id` (`purchase_order_line_id`),
    
    UNIQUE KEY `unique_so_line_po_line` (`sales_order_line_id`, `purchase_order_line_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $db->query($sql);
    echo "✓ Table sales_order_line_po_map created successfully.\n";
} catch (\Exception $e) {
    echo "✗ Error creating table: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✓ Migration completed successfully.\n";
