<?php
$mysqli = new mysqli('127.0.0.1', 'root', '', 'production_management_system');
if ($mysqli->connect_errno) {
    echo "CONN_ERR:" . $mysqli->connect_error . PHP_EOL;
    exit(1);
}
$res = $mysqli->query("SELECT id, username, email, role, is_active FROM users LIMIT 10");
if (!$res) {
    echo "Q_ERR:" . $mysqli->error . PHP_EOL;
    exit(1);
}
while ($row = $res->fetch_assoc()) {
    echo json_encode($row) . PHP_EOL;
}
$mysqli->close();
