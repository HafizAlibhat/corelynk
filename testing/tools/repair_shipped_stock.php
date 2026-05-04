<?php
$pdo = new PDO('mysql:host=localhost;dbname=corelynk_db', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$dos = $pdo->query("SELECT id FROM delivery_orders WHERE status IN ('confirmed','delivered')")
    ->fetchAll(PDO::FETCH_COLUMN);

foreach ($dos as $doId) {
    $linesStmt = $pdo->prepare("SELECT product_id, variant_id, qty_to_ship FROM delivery_order_lines WHERE delivery_order_id = ?");
    $linesStmt->execute([$doId]);
    $lines = $linesStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($lines as $line) {
        $productId = (int)($line['product_id'] ?? 0);
        $variantId = !empty($line['variant_id']) ? (int)$line['variant_id'] : null;
        $qtyToShip = (float)($line['qty_to_ship'] ?? 0);

        if ($productId <= 0 || $qtyToShip <= 0 || !$variantId) {
            continue;
        }

        $productType = $pdo->prepare("SELECT product_type FROM products WHERE id = ?");
        $productType->execute([$productId]);
        $ptype = $productType->fetchColumn();
        if ($ptype !== 'variable') {
            continue;
        }

        // Detect legacy bug row: negative balance with NULL variant
        $bugRow = $pdo->prepare("SELECT id FROM stock_balances WHERE product_id = ? AND variant_id IS NULL AND quantity < 0 LIMIT 1");
        $bugRow->execute([$productId]);
        $hasBug = $bugRow->fetchColumn();
        if (!$hasBug) {
            continue;
        }

        // Deduct from actual variant balances
        $remaining = $qtyToShip;
        $balStmt = $pdo->prepare("SELECT id, quantity FROM stock_balances WHERE product_id = ? AND variant_id = ? ORDER BY quantity DESC");
        $balStmt->execute([$productId, $variantId]);
        $balances = $balStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($balances as $bal) {
            if ($remaining <= 0) break;
            $available = (float)$bal['quantity'];
            if ($available <= 0) continue;

            $deduct = min($available, $remaining);
            $newQty = $available - $deduct;

            $upd = $pdo->prepare("UPDATE stock_balances SET quantity = ?, updated_at = NOW() WHERE id = ?");
            $upd->execute([$newQty, (int)$bal['id']]);

            $remaining -= $deduct;
        }

        // Clean up bug rows for this product
        $pdo->prepare("DELETE FROM stock_balances WHERE product_id = ? AND variant_id IS NULL AND quantity < 0")
            ->execute([$productId]);
    }
}

echo "Repair complete.";
