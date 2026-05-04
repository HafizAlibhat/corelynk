<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');
if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error . PHP_EOL);
}

$sql = "SELECT LOWER(TRIM(name)) AS name_key, COUNT(*) AS cnt FROM product_attributes GROUP BY LOWER(TRIM(name)) HAVING COUNT(*) > 1";
$res = $mysqli->query($sql);
if (!$res) {
    die('Query failed: ' . $mysqli->error . PHP_EOL);
}

echo 'duplicate-groups=' . $res->num_rows . PHP_EOL;
while ($row = $res->fetch_assoc()) {
    echo $row['name_key'] . ' => ' . $row['cnt'] . PHP_EOL;
}

$mysqli->close();
