<?php
// One-time admin reset tool. DELETE this file after use.
// Usage: http://localhost/pro_sys/public/reset_admin.php?email=admin@example.com&username=admin&pwd=Admin@123

declare(strict_types=1);

use CodeIgniter\I18n\Time;

// Bootstrap CodeIgniter framework to access Database config
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);
require dirname(__DIR__) . '/app/Config/Paths.php';
$paths = new Config\Paths();
chdir(dirname(__DIR__));
require rtrim($paths->systemDirectory, '\\/') . '/bootstrap.php';

header('Content-Type: text/plain');

// Lock to localhost only
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($ip, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

$email = $_GET['email'] ?? 'admin@example.com';
$username = $_GET['username'] ?? 'admin';
$pwd = $_GET['pwd'] ?? 'Admin@123';

if ($pwd === '') {
    http_response_code(400);
    echo "Missing ?pwd=...";
    exit;
}

$db = \Config\Database::connect();
$hash = password_hash($pwd, PASSWORD_DEFAULT);

// Ensure users table exists
$tables = $db->listTables();
if (!in_array('users', $tables, true)) {
    http_response_code(500);
    echo "Users table not found. Import schema first.";
    exit;
}

// Check if user exists
$builder = $db->table('users');
$existing = $builder->where('email', $email)->orWhere('username', $username)->get()->getRowArray();

$now = date('Y-m-d H:i:s');
if ($existing) {
    $builder->where('id', $existing['id'])->update([
        'password_hash' => $hash,
        'email'         => $email,
        'username'      => $username,
        'is_active'     => 1,
        'updated_at'    => $now,
    ]);
    echo "Updated admin user (ID: {$existing['id']}).\n";
} else {
    $builder->insert([
        'username'   => $username,
        'email'      => $email,
        'password_hash' => $hash,
        'first_name' => 'System',
        'last_name'  => 'Administrator',
        'role'       => 'admin',
        'is_active'  => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    echo "Inserted new admin user.\n";
}

echo "Login with email: {$email} and your chosen password.\n";
echo "IMPORTANT: Delete public/reset_admin.php after success.\n";
