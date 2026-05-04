<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');
if ($mysqli->connect_error) die('Connection failed: ' . $mysqli->connect_error . PHP_EOL);
foreach (['products','variant_inventory','stock_balances','stock_movements','grns','grn_lines','purchase_grns','purchase_grn_lines'] as $table) {
    echo strtoupper($table) . PHP_EOL;
    $res = $mysqli->query("SHOW COLUMNS FROM `{$table}`");
    if (!$res) { echo 'ERR: '.$mysqli->error.PHP_EOL; continue; }
    while ($row = $res->fetch_assoc()) {
        echo '  - ' . $row['Field'] . PHP_EOL;
    }
    $res->free();
}

echo "PRODUCT 1" . PHP_EOL;
$res = $mysqli->query("SELECT * FROM products WHERE id = 1");
while ($row = $res->fetch_assoc()) echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
$res->free();

$queries = [
    "SELECT * FROM stock_balances WHERE product_id = 1",
    "SELECT * FROM variant_inventory LIMIT 5",
    "SELECT * FROM stock_movements WHERE product_id = 1 ORDER BY id DESC LIMIT 20",
];
foreach ($queries as $sql) {
    echo $sql . PHP_EOL;
    $res = $mysqli->query($sql);
    if (!$res) { echo 'ERR: '.$mysqli->error.PHP_EOL; continue; }
    while ($row = $res->fetch_assoc()) echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    $res->free();
}
$mysqli->close();
