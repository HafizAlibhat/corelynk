<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');
if ($mysqli->connect_error) die($mysqli->connect_error . PHP_EOL);
foreach ([
    "SELECT * FROM warehouses ORDER BY id",
    "SELECT * FROM warehouse_locations ORDER BY warehouse_id, id",
] as $sql) {
    echo $sql . PHP_EOL;
    $res = $mysqli->query($sql);
    if (!$res) { echo 'ERR: '.$mysqli->error.PHP_EOL; continue; }
    while ($row = $res->fetch_assoc()) echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    $res->free();
}
$mysqli->close();
