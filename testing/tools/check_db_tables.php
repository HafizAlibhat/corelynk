<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'corelynk_db';
$port = 3306;

$mysqli = @new mysqli($host, $user, $pass, $db, $port);
if ($mysqli->connect_errno) {
    echo "CONNECT_ERROR: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error . PHP_EOL;
    exit(1);
}

$tables = ['quotations','quotation_lines'];
foreach ($tables as $t) {
    $res = $mysqli->query("SHOW TABLES LIKE '" . $mysqli->real_escape_string($t) . "'");
    if (!$res) {
        echo "ERROR querying for $t: " . $mysqli->error . PHP_EOL;
        continue;
    }
    $found = $res->num_rows > 0;
    echo "$t => " . ($found ? 'FOUND' : 'MISSING') . PHP_EOL;
}

$mysqli->close();
