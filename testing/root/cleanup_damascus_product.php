<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');
if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error . PHP_EOL);
}

$productId = 3;
$variantIds = [];
$res = $mysqli->query("SELECT id FROM product_variants WHERE product_id = {$productId}");
while ($row = $res->fetch_assoc()) {
    $variantIds[] = (int)$row['id'];
}
$res->free();
$variantList = $variantIds ? implode(',', $variantIds) : '0';

$quote2LineIds = [];
$res = $mysqli->query("SELECT id FROM quotation_lines WHERE quotation_id = 2 AND (product_id = {$productId} OR (product_variant_id IS NOT NULL AND product_variant_id IN ({$variantList})))");
while ($row = $res->fetch_assoc()) {
    $quote2LineIds[] = (int)$row['id'];
}
$res->free();

$quote3LineIds = [];
$res = $mysqli->query("SELECT id FROM quotation_lines WHERE quotation_id = 3");
while ($row = $res->fetch_assoc()) {
    $quote3LineIds[] = (int)$row['id'];
}
$res->free();

$salesOrderLineIds = [];
$res = $mysqli->query("SELECT id FROM sales_order_lines WHERE sales_order_id = 2");
while ($row = $res->fetch_assoc()) {
    $salesOrderLineIds[] = (int)$row['id'];
}
$res->free();

$poLineIds = [];
$res = $mysqli->query("SELECT id FROM purchase_order_lines WHERE po_id = 3");
while ($row = $res->fetch_assoc()) {
    $poLineIds[] = (int)$row['id'];
}
$res->free();

$billLineIds = [];
$res = $mysqli->query("SELECT id FROM vendor_bill_lines WHERE vendor_bill_id = 11");
while ($row = $res->fetch_assoc()) {
    $billLineIds[] = (int)$row['id'];
}
$res->free();

$journalEntryId = 4;

$mysqli->begin_transaction();
try {
    if ($quote2LineIds) {
        $mysqli->query("DELETE FROM quotation_lines WHERE id IN (" . implode(',', $quote2LineIds) . ")");
        $sumRes = $mysqli->query("SELECT COALESCE(SUM(line_total),0) AS subtotal, COALESCE(SUM(tax_amount),0) AS tax_total FROM quotation_lines WHERE quotation_id = 2");
        $sumRow = $sumRes->fetch_assoc();
        $sumRes->free();
        $subtotal = (float)($sumRow['subtotal'] ?? 0);
        $taxTotal = (float)($sumRow['tax_total'] ?? 0);
        $total = $subtotal + $taxTotal;
        $stmt = $mysqli->prepare("UPDATE quotations SET subtotal = ?, tax_total = ?, total = ?, converted_to_sales_order_id = NULL WHERE id = 2");
        $stmt->bind_param('ddd', $subtotal, $taxTotal, $total);
        $stmt->execute();
        $stmt->close();
    }

    if ($salesOrderLineIds) {
        $mysqli->query("DELETE FROM sales_order_line_po_map WHERE sales_order_line_id IN (" . implode(',', $salesOrderLineIds) . ")");
        $mysqli->query("DELETE FROM sales_order_lines WHERE id IN (" . implode(',', $salesOrderLineIds) . ")");
    }
    $mysqli->query("DELETE FROM sales_orders WHERE id = 2");

    if ($billLineIds) {
        $mysqli->query("DELETE FROM vendor_bill_lines WHERE id IN (" . implode(',', $billLineIds) . ")");
    }
    $mysqli->query("UPDATE vendor_bills SET posted_entry_id = NULL WHERE id = 11");
    $mysqli->query("DELETE FROM vendor_bills WHERE id = 11");

    $mysqli->query("DELETE FROM journal_lines WHERE entry_id = {$journalEntryId}");
    $mysqli->query("DELETE FROM journal_entries WHERE id = {$journalEntryId}");

    $mysqli->query("DELETE FROM purchase_grn_lines WHERE po_line_id IN (SELECT id FROM purchase_order_lines WHERE po_id = 3)");
    $mysqli->query("DELETE FROM purchase_grns WHERE po_id = 3");
    if ($poLineIds) {
        $mysqli->query("DELETE FROM sales_order_line_po_map WHERE purchase_order_line_id IN (" . implode(',', $poLineIds) . ")");
        $mysqli->query("DELETE FROM purchase_order_lines WHERE id IN (" . implode(',', $poLineIds) . ")");
    }
    $mysqli->query("DELETE FROM purchase_orders WHERE id = 3");

    $mysqli->query("DELETE FROM purchase_rfq_lines WHERE rfq_id = 3");
    $mysqli->query("DELETE FROM purchase_rfqs WHERE id = 3");

    if ($quote3LineIds) {
        $mysqli->query("DELETE FROM quotation_lines WHERE id IN (" . implode(',', $quote3LineIds) . ")");
    }
    $mysqli->query("DELETE FROM quotations WHERE id = 3");

    if ($variantIds) {
        $mysqli->query("DELETE FROM variant_inventory WHERE variant_id IN (" . implode(',', $variantIds) . ")");
        if ($mysqli->query("SHOW TABLES LIKE 'stock_balances'")->num_rows > 0) {
            $mysqli->query("DELETE FROM stock_balances WHERE variant_id IN (" . implode(',', $variantIds) . ") OR product_id = {$productId}");
        }
        if ($mysqli->query("SHOW TABLES LIKE 'stock_movements'")->num_rows > 0) {
            $mysqli->query("DELETE FROM stock_movements WHERE variant_id IN (" . implode(',', $variantIds) . ") OR product_id = {$productId}");
        }
        $mysqli->query("DELETE FROM product_variants WHERE id IN (" . implode(',', $variantIds) . ")");
    }

    if ($mysqli->query("SHOW TABLES LIKE 'product_processes'")->num_rows > 0) {
        $mysqli->query("DELETE FROM product_processes WHERE product_id = {$productId}");
    }
    if ($mysqli->query("SHOW TABLES LIKE 'product_attribute_assignments'")->num_rows > 0) {
        $mysqli->query("DELETE FROM product_attribute_assignments WHERE product_id = {$productId}");
    }

    $mysqli->query("DELETE FROM products WHERE id = {$productId}");

    $mysqli->commit();
    echo "Cleanup complete for product {$productId}." . PHP_EOL;
} catch (Throwable $e) {
    $mysqli->rollback();
    die('Cleanup failed: ' . $e->getMessage() . PHP_EOL);
}

$mysqli->close();
