<?php
$mysqli = new mysqli('127.0.0.1','root','','corelynk_db');
if ($mysqli->connect_errno) { echo "CONNECT_ERR:".$mysqli->connect_error; exit(1); }
$res = $mysqli->query('SELECT id,vendor_id,name FROM vendor_contacts ORDER BY id ASC');
if (!$res) { echo "QUERY_ERR:".$mysqli->error; exit(1); }
while ($row = $res->fetch_assoc()) {
    echo $row['id'] . '|' . $row['vendor_id'] . '|' . addcslashes($row['name'], "\n\r") . PHP_EOL;
}
?>