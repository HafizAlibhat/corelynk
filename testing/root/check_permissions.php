<?php
// Check database for permissions and user roles
$db = new \mysqli('localhost', 'root', '', 'corelynk_db');

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

echo "=== Checking Permissions System ===\n\n";

// Check if roles/permissions tables exist
$tables = ['roles', 'permissions', 'role_permissions', 'user_roles', 'users'];
foreach ($tables as $table) {
    $result = $db->query("SHOW TABLES LIKE '$table'");
    echo "Table '$table': " . ($result->num_rows > 0 ? "EXISTS ✓" : "MISSING ✗") . "\n";
}

echo "\n=== Users ===\n";
$result = $db->query("SELECT id, username, email FROM users LIMIT 5");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "User #{$row['id']}: {$row['username']} ({$row['email']})\n";
    }
}

echo "\n=== Checking sales_orders.edit Permission ===\n";
$result = $db->query("SELECT * FROM permissions WHERE name LIKE '%sales_order%' OR name LIKE '%edit%' LIMIT 10");
if ($result && $result->num_rows > 0) {
    echo "Found permissions:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - " . ($row['name'] ?? $row['id']) . "\n";
    }
} else {
    echo "✗ No permissions found - Permission system may not be set up\n";
}

echo "\n=== Authorization Check ===\n";
echo "If permission system is not set up:\n";
echo "- requirePermission() checks will fail\n";
echo "- Need to either set up permissions or bypass the check\n";

$db->close();
