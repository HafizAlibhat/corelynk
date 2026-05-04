<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');
if ($mysqli->connect_error) die($mysqli->connect_error . PHP_EOL);
$res = $mysqli->query("SELECT id, quote_number, subtotal, total FROM quotations ORDER BY id");
while ($row = $res->fetch_assoc()) echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
$res->free();
$mysqli->close();
