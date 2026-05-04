<?php
$db = new mysqli('localhost', 'root', '', 'corelynk_db');
$r = $db->query('SHOW CREATE TABLE products');
$row = $r->fetch_assoc();
echo $row['Create Table'] . PHP_EOL;
$db->close();
