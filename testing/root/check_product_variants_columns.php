<?php
echo "Starting...\n";
$db = new mysqli('localhost','root','','corelynk_db');
if ($db->connect_error) { die("DB connect error: " . $db->connect_error . "\n"); }
$res = $db->query('SHOW COLUMNS FROM product_variants');
if (!$res) { die("Query error: " . $db->error . "\n"); }
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
