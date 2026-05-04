<?php
// One-time sync: update variant_inventory from stock_balances
$db = new mysqli('localhost', 'root', '', 'corelynk_db');
if ($db->connect_error) { die('Connect failed: ' . $db->connect_error); }

$result = $db->query('SELECT variant_id, warehouse_id, SUM(quantity) as total FROM stock_balances WHERE variant_id IS NOT NULL GROUP BY variant_id, warehouse_id');
if (!$result) { die('Query failed: ' . $db->error); }

$updated = 0; $inserted = 0;
while ($row = $result->fetch_assoc()) {
    $vid  = (int)$row['variant_id'];
    $whid = (int)$row['warehouse_id'];
    $qty  = (float)$row['total'];

    $check = $db->query("SELECT id FROM variant_inventory WHERE variant_id=$vid AND warehouse_id=$whid");
    if ($existing = $check->fetch_assoc()) {
        $db->query("UPDATE variant_inventory SET quantity=$qty WHERE id=" . (int)$existing['id']);
        $updated++;
    } else {
        $stmt = $db->prepare("INSERT INTO variant_inventory (variant_id, warehouse_id, quantity, reserved) VALUES (?,?,?,0)");
        $stmt->bind_param('iid', $vid, $whid, $qty);
        $stmt->execute();
        $inserted++;
    }
}
echo "Done. Updated: $updated, Inserted: $inserted\n";
