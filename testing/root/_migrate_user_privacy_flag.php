<?php
$db = new mysqli('localhost', 'root', '', 'corelynk_db');
if ($db->connect_error) { die('Connect failed: ' . $db->connect_error); }

$r = $db->query("SHOW COLUMNS FROM users LIKE 'can_make_documents_private'");
if ($r->num_rows === 0) {
    $db->query('ALTER TABLE users ADD COLUMN can_make_documents_private TINYINT(1) NOT NULL DEFAULT 0');
    echo 'Added can_make_documents_private to users' . PHP_EOL;
} else {
    echo 'Column already exists' . PHP_EOL;
}
$db->close();
echo 'Done' . PHP_EOL;
