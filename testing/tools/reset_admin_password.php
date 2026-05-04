<?php
// Usage: php reset_admin_password.php [new_password]
$pwd = $argv[1] ?? 'AdminPass123!';
$mysqli = new mysqli('127.0.0.1', 'root', '', 'production_management_system');
if ($mysqli->connect_errno) {
    echo "CONN_ERR:" . $mysqli->connect_error . PHP_EOL;
    exit(1);
}
// Try to find admin by username or email
$admin = null;
$res = $mysqli->query("SELECT id, username, email FROM users WHERE username='admin' OR email='admin@production.local' LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    $admin = $row;
}
if (!$admin) {
    echo "NO_ADMIN: could not find user with username 'admin' or email 'admin@production.local'\n";
    exit(1);
}
$hash = password_hash($pwd, PASSWORD_DEFAULT);
$stmt = $mysqli->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
if (!$stmt) {
    echo "PREPARE_ERR: " . $mysqli->error . PHP_EOL;
    exit(1);
}
$stmt->bind_param('si', $hash, $admin['id']);
if ($stmt->execute()) {
    echo "UPDATED: user=" . $admin['username'] . " email=" . $admin['email'] . " new_password=" . $pwd . PHP_EOL;
} else {
    echo "EXEC_ERR: " . $stmt->error . PHP_EOL;
}
$stmt->close();
$mysqli->close();
