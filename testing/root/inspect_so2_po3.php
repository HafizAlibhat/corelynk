<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');
if ($mysqli->connect_error) die($mysqli->connect_error . PHP_EOL);
foreach ([['sales_orders','sales_order_lines','sales_order_id',2],['purchase_orders','purchase_order_lines','po_id',3],['vendor_bills','vendor_bill_lines','vendor_bill_id',11]] as $cfg) {
    [$head,$line,$fk,$id] = $cfg;
    echo strtoupper($head) . ' ' . $id . PHP_EOL;
    $r = $mysqli->query("SELECT * FROM `{$head}` WHERE id = {$id}");
    while ($row = $r->fetch_assoc()) echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    $r->free();
    $r = $mysqli->query("SELECT * FROM `{$line}` WHERE `{$fk}` = {$id} ORDER BY id");
    while ($row = $r->fetch_assoc()) echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    $r->free();
}
$mysqli->close();
