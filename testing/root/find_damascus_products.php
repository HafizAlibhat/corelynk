<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');
if ($mysqli->connect_error) die($mysqli->connect_error . PHP_EOL);
$res = $mysqli->query("SELECT id, name, code, product_type, attributes_definitions FROM products WHERE name LIKE '%Damascus%' OR description LIKE '%Damascus%' ORDER BY id ASC");
while ($row = $res->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
$mysqli->close();
