<?php
$connect = mysqli_connect('localhost', 'root', '', 'corelynk_db');
if (!$connect) {
    echo 'Connection failed: ' . mysqli_connect_error();
    exit;
}

echo "=== CHANNELS TABLE ===\n";
$result = mysqli_query($connect, 'DESCRIBE channels');
while ($row = mysqli_fetch_assoc($result)) {
    echo $row['Field'] . ' | ' . $row['Type'] . ' | ' . ($row['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
}

echo "\n=== PRODUCT_ASSET_GROUPS TABLE ===\n";
$result = mysqli_query($connect, 'DESCRIBE product_asset_groups');
while ($row = mysqli_fetch_assoc($result)) {
    echo $row['Field'] . ' | ' . $row['Type'] . ' | ' . ($row['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
}

echo "\n=== PRODUCT_ASSETS TABLE ===\n";
$result = mysqli_query($connect, 'DESCRIBE product_assets');
while ($row = mysqli_fetch_assoc($result)) {
    echo $row['Field'] . ' | ' . $row['Type'] . ' | ' . ($row['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
}

echo "\n=== PRODUCT_ASSET_LISTINGS TABLE ===\n";
$result = mysqli_query($connect, 'DESCRIBE product_asset_listings');
while ($row = mysqli_fetch_assoc($result)) {
    echo $row['Field'] . ' | ' . $row['Type'] . ' | ' . ($row['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
}

echo "\n=== Sample Channel Data ===\n";
$result = mysqli_query($connect, 'SELECT * FROM channels LIMIT 1');
if ($row = mysqli_fetch_assoc($result)) {
    print_r($row);
}

mysqli_close($connect);
