<?php
$db = new mysqli('127.0.0.1','root','','corelynk_db');
if ($db->connect_errno) { echo 'connect error: ' . $db->connect_error . PHP_EOL; exit(1); }
$res = $db->query('SHOW COLUMNS FROM quotations');
if (!$res) { echo 'query failed: ' . $db->error . PHP_EOL; exit(1); }
while ($r = $res->fetch_assoc()) {
    echo $r['Field'] . "\t" . $r['Type'] . PHP_EOL;
}
