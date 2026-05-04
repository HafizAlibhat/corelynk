<?php
$c = new mysqli('localhost','root','','corelynk_db');
echo "=== delivery_orders table ===\n";
$r=$c->query("SELECT id, sales_order_id, do_number, status FROM delivery_orders ORDER BY id DESC LIMIT 10");
while($row=$r->fetch_assoc()) echo json_encode($row)."\n";
echo "\n=== Check routes for delivery-orders/progress ===\n";
echo "done\n";
$c->close();
