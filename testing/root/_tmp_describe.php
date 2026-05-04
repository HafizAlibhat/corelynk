<?php
$db = new mysqli('localhost','root','','corelynk_db');
$r = $db->query('DESCRIBE vendor_payments');
while($row = $r->fetch_assoc()) echo $row['Field'].' | '.$row['Type']."\n";

echo "\n--- vendor_payment_allocations ---\n";
$r2 = $db->query('DESCRIBE vendor_payment_allocations');
while($row = $r2->fetch_assoc()) echo $row['Field'].' | '.$row['Type']."\n";

echo "\n--- vendor_bills sample (ID 7) ---\n";
$r3 = $db->query('SELECT * FROM vendor_bills WHERE id=7');
if($row = $r3->fetch_assoc()) print_r($row);

echo "\n--- payment #3 data ---\n";
$r4 = $db->query('SELECT * FROM vendor_payments WHERE id=3');
if($row = $r4->fetch_assoc()) print_r($row);

echo "\n--- cheques table (if linked) ---\n";
$r5 = $db->query('SELECT * FROM cheques WHERE id IN (SELECT cheque_id FROM vendor_payments WHERE id=3 AND cheque_id>0) LIMIT 1');
if($r5 && $row = $r5->fetch_assoc()) print_r($row);
else echo "No linked cheque\n";

echo "\n--- vendor_bills DESCRIBE ---\n";
$r6 = $db->query('DESCRIBE vendor_bills');
while($row = $r6->fetch_assoc()) echo $row['Field'].' | '.$row['Type']."\n";
