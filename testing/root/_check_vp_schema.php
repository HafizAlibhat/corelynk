<?php
$db = new PDO('mysql:host=localhost;dbname=corelynk_db','root','');
echo "=== cheques table ===\n";
$r = $db->query('DESCRIBE cheques');
foreach($r as $c) echo $c['Field'] . ' (' . $c['Type'] . ')' . PHP_EOL;

echo "\n=== payment #2 ===\n";
$r = $db->query('SELECT * FROM vendor_payments WHERE id=2');
print_r($r->fetch(PDO::FETCH_ASSOC));

echo "\n=== cheques data ===\n";
$r = $db->query('SELECT * FROM cheques LIMIT 5');
foreach($r as $c) print_r($c);

