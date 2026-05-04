<?php
$c = new mysqli('localhost','root','','corelynk_db');
echo "=== STOCK_MOVEMENTS COLUMNS ===\n";
$r = $c->query("DESCRIBE stock_movements");
while ($row = $r->fetch_assoc()) {
    echo "{$row['Field']} | {$row['Type']} | Null:{$row['Null']}\n";
}

echo "\n=== SAMPLE DATA (last 10 movements) ===\n";
$r = $c->query("SELECT sm.*, p.name as pname, pv.name as vname FROM stock_movements sm LEFT JOIN products p ON p.id=sm.product_id LEFT JOIN product_variants pv ON pv.id=sm.variant_id ORDER BY sm.id DESC LIMIT 10");
while ($row = $r->fetch_assoc()) {
    echo json_encode($row)."\n";
}

echo "\n=== DISTINCT movement_type values ===\n";
$r = $c->query("SELECT DISTINCT movement_type FROM stock_movements");
while ($row = $r->fetch_assoc()) echo $row['movement_type']."\n";

echo "\n=== DISTINCT reference_type values ===\n";
$r = $c->query("SELECT DISTINCT reference_type FROM stock_movements");
while ($row = $r->fetch_assoc()) echo ($row['reference_type'] ?? 'NULL')."\n";

echo "\n=== Users table columns ===\n";
$r = $c->query("SHOW COLUMNS FROM users LIKE 'username'");
echo "username column exists: " . ($r->num_rows > 0 ? 'YES' : 'NO') . "\n";
$r = $c->query("SHOW COLUMNS FROM users LIKE 'name'");
echo "name column exists: " . ($r->num_rows > 0 ? 'YES' : 'NO') . "\n";
$r = $c->query("SHOW COLUMNS FROM users LIKE 'email'");
echo "email column exists: " . ($r->num_rows > 0 ? 'YES' : 'NO') . "\n";
$r = $c->query("SELECT id, CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')) as fullname FROM users LIMIT 3");
if ($r && $r->num_rows > 0) { echo "Users sample: "; while($row=$r->fetch_assoc()) echo json_encode($row)." "; echo "\n"; }
else {
    $r = $c->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='corelynk_db' AND TABLE_NAME='users' LIMIT 20");
    echo "Users cols: ";
    while($row=$r->fetch_assoc()) echo $row['COLUMN_NAME'].", ";
    echo "\n";
}
$c->close();
