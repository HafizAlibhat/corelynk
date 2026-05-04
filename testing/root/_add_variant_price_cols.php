<?php
$db = new mysqli('localhost', 'root', '', 'corelynk_db');
$sql = "ALTER TABLE `product_variants`
  ADD COLUMN IF NOT EXISTS `sale_currency` VARCHAR(10) NULL DEFAULT NULL AFTER `price`,
  ADD COLUMN IF NOT EXISTS `cost_currency` VARCHAR(10) NULL DEFAULT NULL AFTER `cost`,
  ADD COLUMN IF NOT EXISTS `vendor_price` DECIMAL(15,4) NULL DEFAULT NULL AFTER `vendor_id`,
  ADD COLUMN IF NOT EXISTS `vendor_currency` VARCHAR(10) NULL DEFAULT NULL AFTER `vendor_price`";
if ($db->query($sql)) {
    echo "Columns added OK\n";
} else {
    echo "Error: " . $db->error . "\n";
}
// Verify
$result = $db->query("SHOW COLUMNS FROM product_variants");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
