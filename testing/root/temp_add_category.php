<?php
$host = '127.0.0.1';
$user = 'root';
$pass = '';
$db   = 'production_management_system';
$port = 3306;
$mysqli = new mysqli($host, $user, $pass, $db, $port);
if ($mysqli->connect_errno) {
    echo "CONNECT_ERR: " . $mysqli->connect_error . PHP_EOL;
    exit(1);
}

$res = $mysqli->query("SHOW COLUMNS FROM `processes` LIKE 'category_id'");
if ($res && $res->num_rows > 0) {
    echo "SKIP: column already exists\n";
} else {
    $sql = "ALTER TABLE `processes` ADD COLUMN `category_id` INT(10) UNSIGNED NULL DEFAULT NULL";
    if ($mysqli->query($sql)) {
        echo "OK: column added\n";
    } else {
        echo "ERR: " . $mysqli->error . PHP_EOL;
        exit(1);
    }
}

// Show final describe
$res2 = $mysqli->query('DESCRIBE `processes`');
if (! $res2) {
    echo "DESCRIBE_ERR: " . $mysqli->error . PHP_EOL;
    exit(1);
}
while ($row = $res2->fetch_assoc()) {
    echo $row['Field'] . "\t" . $row['Type'] . "\n";
}
$mysqli->close();
