<?php
$connect = mysqli_connect('localhost', 'root', '', 'corelynk_db');
if (!$connect) {
    echo 'Connection failed: ' . mysqli_connect_error();
    exit;
}

echo "=== MIGRATIONS TABLE ===\n";
$result = mysqli_query($connect, 'SELECT * FROM migrations ORDER BY id DESC LIMIT 15');
while ($row = mysqli_fetch_assoc($result)) {
    echo "Version: " . $row['version'] . " | Batch: " . $row['batch'] . " | Time: " . $row['time'] . "\n";
}

echo "\n=== Product Assets Module Migration Status ===\n";
$result = mysqli_query($connect, 'SELECT * FROM migrations WHERE version LIKE "%ProductAssets%"');
if (mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    print_r($row);
} else {
    echo "Migration not in migrations table. May have failed silently.\n";
}

mysqli_close($connect);
