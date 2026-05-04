<?php
$db = new mysqli('localhost','root','','corelynk_db');
if ($db->connect_error) { die('DB error: ' . $db->connect_error); }

echo "=== quotations columns ===\n";
$r = $db->query('DESCRIBE quotations');
while ($row = $r->fetch_assoc()) echo "  {$row['Field']} {$row['Type']} {$row['Null']} default={$row['Default']}\n";

echo "\n=== quotation_lines columns ===\n";
$r2 = $db->query('DESCRIBE quotation_lines');
while ($row = $r2->fetch_assoc()) echo "  {$row['Field']} {$row['Type']} {$row['Null']} default={$row['Default']}\n";

echo "\n=== Quotation ID=1 ===\n";
$r3 = $db->query('SELECT * FROM quotations WHERE id=1');
$q = $r3->fetch_assoc();
if ($q) print_r($q); else echo "NOT FOUND\n";

echo "\n=== quotation_lines for quote 1 ===\n";
$r4 = $db->query('SELECT * FROM quotation_lines WHERE quotation_id=1');
while ($row = $r4->fetch_assoc()) print_r($row);

$db->close();
echo "\nDone.\n";
