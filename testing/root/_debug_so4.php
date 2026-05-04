<?php
$db = new mysqli('localhost','root','','corelynk_db');

echo "=== SO #4 Lines ===" . PHP_EOL;
$r = $db->query("SELECT sol.id, sol.product_id, sol.product_variant_id, sol.quantity, p.name, p.product_type, p.detailed_type FROM sales_order_lines sol JOIN products p ON p.id=sol.product_id WHERE sol.sales_order_id=4");
while ($row = $r->fetch_assoc()) { echo json_encode($row) . PHP_EOL; }

echo PHP_EOL . "=== variant_inventory for these variants ===" . PHP_EOL;
$r2 = $db->query("SELECT vi.* FROM variant_inventory vi WHERE vi.variant_id IN (SELECT product_variant_id FROM sales_order_lines WHERE sales_order_id=4)");
while ($row = $r2->fetch_assoc()) { echo json_encode($row) . PHP_EOL; }

echo PHP_EOL . "=== stock_balances for these products ===" . PHP_EOL;
$r3 = $db->query("SELECT sb.* FROM stock_balances sb WHERE sb.product_id IN (SELECT product_id FROM sales_order_lines WHERE sales_order_id=4)");
while ($row = $r3->fetch_assoc()) { echo json_encode($row) . PHP_EOL; }

echo PHP_EOL . "=== product_variants for SO4 products ===" . PHP_EOL;
$r4 = $db->query("SELECT pv.id, pv.product_id, pv.sku, pv.variant_name FROM product_variants pv WHERE pv.product_id IN (SELECT DISTINCT product_id FROM sales_order_lines WHERE sales_order_id=4) LIMIT 15");
while ($row = $r4->fetch_assoc()) { echo json_encode($row) . PHP_EOL; }
