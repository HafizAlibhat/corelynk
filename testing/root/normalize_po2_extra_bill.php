<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');
if ($mysqli->connect_error) die('Connection failed: ' . $mysqli->connect_error . PHP_EOL);
$stmt = $mysqli->prepare("UPDATE vendor_bills SET based_on = ?, notes = ? WHERE id = 14 AND po_id = 2");
$basedOn = 'po_over_receipt';
$notes = 'Over-receipt adjustment from GRN #4 | Vendor sent extra quantity on 2026-04-03 16:01:42';
$stmt->bind_param('ss', $basedOn, $notes);
$stmt->execute();
echo 'affected=' . $stmt->affected_rows . PHP_EOL;
$stmt->close();
$res = $mysqli->query("SELECT id, based_on, notes, status FROM vendor_bills WHERE id = 14");
while ($row = $res->fetch_assoc()) echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
$res->free();
$mysqli->close();
