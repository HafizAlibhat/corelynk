<?php
// Check database and create if needed
echo "=== MySQL Database Check ===\n\n";

// Try different connection methods
$attempts = [
    ['127.0.0.1', 'root', ''],
    ['localhost', 'root', ''],
    ['127.0.0.1', 'root', 'root'],
    ['localhost', 'root', 'root'],
];

$conn = null;
foreach ($attempts as [$host, $user, $pass]) {
    @$conn = mysqli_connect($host, $user, $pass);
    if ($conn) {
        echo "✓ Connected via: $user@$host (password: " . (empty($pass) ? 'empty' : 'set') . ")\n\n";
        break;
    }
}

if (!$conn) {
    echo "✗ All connection attempts failed\n";
    echo "Last error: " . mysqli_connect_error() . "\n";
    exit(1);
}

// Check databases
echo "Available databases:\n";
$result = mysqli_query($conn, "SHOW DATABASES;");
while ($row = mysqli_fetch_array($result)) {
    echo "  - " . $row[0] . "\n";
}

// Check if corelynk_db exists
$result = mysqli_query($conn, "SHOW DATABASES LIKE 'corelynk_db';");
if (mysqli_num_rows($result) > 0) {
    echo "\n✓ Database 'corelynk_db' exists\n";
    
    // Check tables in corelynk_db
    mysqli_select_db($conn, 'corelynk_db');
    $result = mysqli_query($conn, "SHOW TABLES LIMIT 10;");
    echo "\nTables in corelynk_db:\n";
    $count = 0;
    while ($row = mysqli_fetch_array($result)) {
        echo "  - " . $row[0] . "\n";
        $count++;
    }
    echo "  (showing first 10 of tables)\n";
} else {
    echo "\n✗ Database 'corelynk_db' not found\n";
    echo "\nCreating database...\n";
    if (mysqli_query($conn, "CREATE DATABASE corelynk_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;")) {
        echo "✓ Database created\n";
    } else {
        echo "✗ Failed to create: " . mysqli_error($conn) . "\n";
    }
}

mysqli_close($conn);
?>
