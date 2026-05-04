<?php
// Local helper: generate a bcrypt hash for a password. DELETE this file after use.
// Usage: http://localhost/pro_sys/public/generate_password_hash.php?pwd=Admin@123

declare(strict_types=1);

header('Content-Type: text/plain');

// Only allow from localhost for safety
$allowed = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
if (!$allowed) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

$pwd = $_GET['pwd'] ?? 'Admin@123';
if ($pwd === '') {
    http_response_code(400);
    echo "Provide ?pwd=YourNewPassword";
    exit;
}

$hash = password_hash($pwd, PASSWORD_DEFAULT);
echo "Generated hash:\n";
echo $hash, PHP_EOL, PHP_EOL;

$email = $_GET['email'] ?? 'admin@example.com';
$username = $_GET['username'] ?? 'admin';
$first = $_GET['first'] ?? 'System';
$last = $_GET['last'] ?? 'Administrator';

echo "SQL to UPDATE existing admin (copy into phpMyAdmin > SQL):\n";
echo "UPDATE users SET password_hash = '" . addslashes($hash) . "', is_active = 1 WHERE email = '" . addslashes($email) . "' OR username = '" . addslashes($username) . "';\n\n";

echo "If no row exists, run this INSERT:\n";
echo "INSERT INTO users (username, email, password_hash, first_name, last_name, role, is_active, created_at, updated_at) VALUES\n";
echo "('" . addslashes($username) . "', '" . addslashes($email) . "', '" . addslashes($hash) . "', '" . addslashes($first) . "', '" . addslashes($last) . "', 'admin', 1, NOW(), NOW());\n";
