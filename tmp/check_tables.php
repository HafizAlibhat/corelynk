<?php
$connect = mysqli_connect('localhost', 'root', '', 'corelynk_db');
if (!$connect) {
    echo 'Connection failed: ' . mysqli_connect_error();
    exit;
}

echo "=== TABLES IN corelynk_db ===\n";
$result = mysqli_query($connect, 'SHOW TABLES');
while ($row = mysqli_fetch_array($result)) {
    echo $row[0] . "\n";
}

echo "\n=== PRODUCT_ASSET_GROUPS TABLE (if exists) ===\n";
$result = mysqli_query($connect, 'SHOW TABLES LIKE "product_asset_groups"');
if (mysqli_num_rows($result) > 0) {
    echo "Table exists\n";
    $result = mysqli_query($connect, 'DESCRIBE product_asset_groups');
    while ($row = mysqli_fetch_assoc($result)) {
        echo $row['Field'] . " | " . $row['Type'] . "\n";
    }
} else {
    echo "Table does NOT exist\n";
}

echo "\n=== PRODUCT_ASSETS TABLE (if exists) ===\n";
$result = mysqli_query($connect, 'SHOW TABLES LIKE "product_assets"');
if (mysqli_num_rows($result) > 0) {
    echo "Table exists\n";
} else {
    echo "Table does NOT exist\n";
}

echo "\n=== CHANNELS TABLE (if exists) ===\n";
$result = mysqli_query($connect, 'SHOW TABLES LIKE "channels"');
if (mysqli_num_rows($result) > 0) {
    echo "Table exists\n";
} else {
    echo "Table does NOT exist\n";
}

mysqli_close($connect);
