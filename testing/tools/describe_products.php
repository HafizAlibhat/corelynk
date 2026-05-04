<?php
$db = new mysqli('localhost','root','','production_management_system');
if ($db->connect_errno) { echo "connect_failed: " . $db->connect_error . "\n"; exit(1); }
$res = $db->query("SHOW COLUMNS FROM products");
if (!$res) { echo "Query failed: " . $db->error . "\n"; exit(2);} 
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\t" . $row['Type'] . "\t" . $row['Null'] . "\t" . $row['Key'] . "\t" . $row['Default'] . "\t" . $row['Extra'] . "\n";
}
