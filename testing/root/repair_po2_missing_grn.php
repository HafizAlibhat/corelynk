<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');
if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error . PHP_EOL);
}

$poId = 2;
$productId = 1;
$poLineId = 2;
$warehouseId = 2;
$locationId = 2;
$receivedQty = 6.0;
$orderedQty = 5.0;
$overReceivedQty = 1.0;
$unitCost = 750.0;
$createdBy = 1;
$receivedAt = date('Y-m-d H:i:s');
$itemKey = 'p' . $productId;

$mysqli->begin_transaction();
try {
    $poRes = $mysqli->query("SELECT * FROM purchase_orders WHERE id = {$poId} FOR UPDATE");
    $po = $poRes->fetch_assoc();
    $poRes->free();
    if (!$po) {
        throw new RuntimeException('PO 2 not found');
    }

    $lineRes = $mysqli->query("SELECT * FROM purchase_order_lines WHERE id = {$poLineId} AND po_id = {$poId} FOR UPDATE");
    $poLine = $lineRes->fetch_assoc();
    $lineRes->free();
    if (!$poLine) {
        throw new RuntimeException('PO line not found');
    }

    $existsRes = $mysqli->query("SELECT id FROM purchase_grns WHERE po_id = {$poId} LIMIT 1");
    $existingGrn = $existsRes->fetch_assoc();
    $existsRes->free();
    if ($existingGrn) {
        throw new RuntimeException('A purchase GRN already exists for PO 2');
    }

    $grnNumber = 'GRN-000001';
    $grnNoRes = $mysqli->query("SELECT grn_number FROM purchase_grns ORDER BY id DESC LIMIT 1");
    if ($grnNoRes) {
        $last = $grnNoRes->fetch_assoc();
        $grnNoRes->free();
        if ($last && !empty($last['grn_number']) && preg_match('/(\d+)$/', $last['grn_number'], $m)) {
            $next = ((int)$m[1]) + 1;
            $grnNumber = 'GRN-' . str_pad((string)$next, 6, '0', STR_PAD_LEFT);
        }
    }

    $stmt = $mysqli->prepare("INSERT INTO purchase_grns (grn_number, po_id, vendor_id, received_at, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?)");
    $vendorId = (int)$po['vendor_id'];
    $createdAt = date('Y-m-d H:i:s');
    $stmt->bind_param('siisis', $grnNumber, $poId, $vendorId, $receivedAt, $createdBy, $createdAt);
    $stmt->execute();
    $grnId = $mysqli->insert_id;
    $stmt->close();

    if (!$grnId) {
        throw new RuntimeException('Failed to create purchase GRN');
    }

    $desc = '29cm - Penfield Dura Dissector';
    $reasonType = 'vendor_extra';
    $reasonDetails = 'Backfilled after missing GRN bug; extra 1 pc already billed.';
    $stmt = $mysqli->prepare("INSERT INTO purchase_grn_lines (grn_id, po_line_id, product_id, variant_id, description, qty_received, over_received_qty, over_receipt_reason_type, over_receipt_reason_details, unit_cost, created_at) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iiisddssds', $grnId, $poLineId, $productId, $desc, $receivedQty, $overReceivedQty, $reasonType, $reasonDetails, $unitCost, $createdAt);
    $stmt->execute();
    $stmt->close();

    $stmt = $mysqli->prepare("UPDATE purchase_order_lines SET qty_received = ? WHERE id = ?");
    $stmt->bind_param('di', $receivedQty, $poLineId);
    $stmt->execute();
    $stmt->close();

    $stmt = $mysqli->prepare("UPDATE purchase_orders SET status = 'closed', updated_at = ? WHERE id = ?");
    $stmt->bind_param('si', $createdAt, $poId);
    $stmt->execute();
    $stmt->close();

    $stockBalanceRes = $mysqli->query("SELECT id, quantity FROM stock_balances WHERE product_id = {$productId} AND variant_id IS NULL AND warehouse_id = {$warehouseId} AND location_id = {$locationId} LIMIT 1 FOR UPDATE");
    $stockBalance = $stockBalanceRes ? $stockBalanceRes->fetch_assoc() : null;
    if ($stockBalanceRes) $stockBalanceRes->free();

    if ($stockBalance) {
        $newQty = (float)$stockBalance['quantity'] + $receivedQty;
        $stmt = $mysqli->prepare("UPDATE stock_balances SET quantity = ?, updated_at = ? WHERE id = ?");
        $stockBalanceId = (int)$stockBalance['id'];
        $stmt->bind_param('dsi', $newQty, $createdAt, $stockBalanceId);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $mysqli->prepare("INSERT INTO stock_balances (product_id, variant_id, item_key, warehouse_id, location_id, quantity, created_at, updated_at) VALUES (?, NULL, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('isiddss', $productId, $itemKey, $warehouseId, $locationId, $receivedQty, $createdAt, $createdAt);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $mysqli->prepare("INSERT INTO stock_movements (product_id, variant_id, item_key, warehouse_id, location_id, qty_change, movement_type, reference_type, reference_id, created_by, created_at) VALUES (?, NULL, ?, ?, ?, ?, 'in', 'grn', ?, ?, ?)");
    $stmt->bind_param('isiddiis', $productId, $itemKey, $warehouseId, $locationId, $receivedQty, $grnId, $createdBy, $createdAt);
    $stmt->execute();
    $stmt->close();

    $productRes = $mysqli->query("SELECT current_stock FROM products WHERE id = {$productId} FOR UPDATE");
    $product = $productRes->fetch_assoc();
    $productRes->free();
    $currentStock = (float)($product['current_stock'] ?? 0);
    $newCurrentStock = $currentStock + $receivedQty;
    $stmt = $mysqli->prepare("UPDATE products SET current_stock = ?, updated_at = ? WHERE id = ?");
    $stmt->bind_param('dsi', $newCurrentStock, $createdAt, $productId);
    $stmt->execute();
    $stmt->close();

    // Normalize the already-created extra bill so it is clearly the over-receipt adjustment.
    $mysqli->query("UPDATE vendor_bills SET based_on = 'po_over_receipt', notes = 'Over-receipt adjustment from GRN #{$grnId} | Vendor sent extra quantity on {$createdAt}' WHERE id = 14 AND po_id = 2");

    $mysqli->commit();
    echo 'Repair complete. GRN ID: ' . $grnId . ', GRN Number: ' . $grnNumber . PHP_EOL;
} catch (Throwable $e) {
    $mysqli->rollback();
    die('Repair failed: ' . $e->getMessage() . PHP_EOL);
}

$mysqli->close();
