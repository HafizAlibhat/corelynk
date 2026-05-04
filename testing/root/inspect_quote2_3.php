<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');
if ($mysqli->connect_error) die($mysqli->connect_error . PHP_EOL);
foreach ([2,3] as $qid) {
    echo 'QUOTATION '.$qid.PHP_EOL;
    $r = $mysqli->query("SELECT * FROM quotations WHERE id = {$qid}");
    while ($row = $r->fetch_assoc()) echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    $r->free();
    $r = $mysqli->query("SELECT * FROM quotation_lines WHERE quotation_id = {$qid} ORDER BY id");
    while ($row = $r->fetch_assoc()) echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    $r->free();
}
$mysqli->close();
