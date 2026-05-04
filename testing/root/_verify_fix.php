<?php
$c = new mysqli('localhost','root','','corelynk_db');
$r = $c->query("SELECT variant_id, SUM(quantity) as qty, SUM(reserved) as res FROM variant_inventory WHERE variant_id IN (2781,2782,2783,2784,2785) GROUP BY variant_id");
echo "=== SUM query results (what getAvailability will now return) ===\n";
while ($row = $r->fetch_assoc()) {
    echo "variant_id={$row['variant_id']} qty={$row['qty']} reserved={$row['res']}\n";
}
$c->close();
