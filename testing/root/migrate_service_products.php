<?php
/**
 * Migration: Add detailed_type and service_policy columns to products table.
 * Run once. Safe to re-run (checks before altering).
 */
$pdo = new PDO('mysql:host=localhost;dbname=corelynk_db', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$changes = [];

// 1. detailed_type: storable | consumable | service
$col = $pdo->query("SHOW COLUMNS FROM `products` LIKE 'detailed_type'")->fetch();
if (!$col) {
    $pdo->exec("ALTER TABLE `products` ADD COLUMN `detailed_type` VARCHAR(20) NOT NULL DEFAULT 'storable' AFTER `product_type`");
    $changes[] = 'Added products.detailed_type';
} else {
    $changes[] = 'products.detailed_type already exists';
}

// 2. service_policy: ordered_qty | delivered_qty (controls when service is invoiceable)
$col = $pdo->query("SHOW COLUMNS FROM `products` LIKE 'service_policy'")->fetch();
if (!$col) {
    $pdo->exec("ALTER TABLE `products` ADD COLUMN `service_policy` VARCHAR(20) NULL DEFAULT NULL AFTER `detailed_type`");
    $changes[] = 'Added products.service_policy';
} else {
    $changes[] = 'products.service_policy already exists';
}

// 3. Add index on detailed_type for filtering performance
try {
    $idx = $pdo->query("SHOW INDEX FROM `products` WHERE Key_name = 'idx_products_detailed_type'")->fetch();
    if (!$idx) {
        $pdo->exec("ALTER TABLE `products` ADD INDEX `idx_products_detailed_type` (`detailed_type`)");
        $changes[] = 'Added index idx_products_detailed_type';
    }
} catch (Exception $e) {
    $changes[] = 'Index already exists or skipped: ' . $e->getMessage();
}

echo "=== Products Service Columns Migration ===\n";
foreach ($changes as $c) {
    echo "  [OK] $c\n";
}
echo "Done.\n";
