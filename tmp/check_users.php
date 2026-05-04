<?php
$connect = mysqli_connect('localhost', 'root', '', 'corelynk_db');
if (!$connect) {
    echo 'Connection failed: ' . mysqli_connect_error();
    exit;
}

echo "=== USERS IN DATABASE ===\n";
$result = mysqli_query($connect, 'SELECT id, email, first_name, last_name, is_active FROM users LIMIT 10');
while ($row = mysqli_fetch_assoc($result)) {
    echo "ID: " . $row['id'] . " | Email: " . $row['email'] . " | Name: " . $row['first_name'] . " " . $row['last_name'] . " | Active: " . $row['is_active'] . "\n";
}

mysqli_close($connect);
