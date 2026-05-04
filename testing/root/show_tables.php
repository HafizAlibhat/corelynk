<?php
$db = new mysqli('localhost', 'root', '', 'pro_sys');
if ($db->connect_error) die('Connection failed');

echo "Tables in pro_sys database:\n";
$result = $db->query('SHOW TABLES');
while ($row = $result->fetch_array()) {
    echo "- " . $row[0] . "\n";
}
