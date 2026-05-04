<?php
$db = new mysqli('localhost', 'root', '', 'corelynk_db');
if ($db->connect_error) { die('Connect failed: ' . $db->connect_error); }

$result = $db->query("SHOW COLUMNS FROM role_data_access LIKE 'can_make_documents_private'");
if ($result->num_rows === 0) {
    $db->query('ALTER TABLE role_data_access ADD COLUMN can_make_documents_private TINYINT(1) NOT NULL DEFAULT 0');
    echo 'Added can_make_documents_private to role_data_access' . PHP_EOL;
} else {
    echo 'can_make_documents_private already exists in role_data_access' . PHP_EOL;
}

$result = $db->query("SHOW COLUMNS FROM users LIKE 'documents_private'");
if ($result->num_rows === 0) {
    $db->query('ALTER TABLE users ADD COLUMN documents_private TINYINT(1) NOT NULL DEFAULT 0');
    echo 'Added documents_private to users' . PHP_EOL;
} else {
    echo 'documents_private already exists in users' . PHP_EOL;
}
$db->close();
echo 'Done' . PHP_EOL;
