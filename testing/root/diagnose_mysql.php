<?php
/**
 * MySQL Database Connection Troubleshooting & Fix
 * Run this to diagnose and fix database connection issues
 */

header('Content-Type: text/plain; charset=utf-8');

echo "════════════════════════════════════════════════════════════\n";
echo "       MySQL Database Connection Diagnostics\n";
echo "════════════════════════════════════════════════════════════\n\n";

// Test 1: Port connectivity
echo "1. Testing MySQL port (3306)...\n";
$socket = @fsockopen('127.0.0.1', 3306, $errno, $errstr, 2);
if ($socket) {
    echo "   ✓ MySQL is listening on port 3306\n";
    fclose($socket);
} else {
    echo "   ✗ Cannot connect to port 3306\n";
    echo "   Error: $errstr ($errno)\n";
}

// Test 2: Try connection without authentication
echo "\n2. Attempting connection without password...\n";
$conn = @mysqli_connect('127.0.0.1', 'root', '', 'corelynk_db');

if ($conn) {
    echo "   ✓ Connected successfully!\n";
    echo "   MySQL Version: " . mysqli_get_server_info($conn) . "\n";
    
    // Test query
    $result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        echo "   Tables in corelynk_db: " . $row['cnt'] . "\n";
    }
    
    mysqli_close($conn);
} else {
    echo "   ✗ Connection failed\n";
    echo "   Error: " . mysqli_connect_error() . "\n\n";
    
    // Test 3: Try with localhost instead of 127.0.0.1
    echo "3. Trying with 'localhost'...\n";
    $conn = @mysqli_connect('localhost', 'root', '', 'corelynk_db');
    
    if ($conn) {
        echo "   ✓ Connected with hostname 'localhost'\n";
        echo "   MySQL Version: " . mysqli_get_server_info($conn) . "\n";
        mysqli_close($conn);
    } else {
        echo "   ✗ Failed with localhost\n";
        echo "   Error: " . mysqli_connect_error() . "\n\n";
        
        // Test 4: Check database configuration
        echo "4. Database Configuration Check:\n";
        if (file_exists(APPPATH . 'Config/Database.php')) {
            $config = require APPPATH . 'Config/Database.php';
            echo "   ✓ Config file found\n";
        } else {
            echo "   ✗ Config file not found\n";
        }
    }
}

echo "\n════════════════════════════════════════════════════════════\n";
echo "RECOMMENDATION:\n";
echo "════════════════════════════════════════════════════════════\n";
echo "\nIf MySQL is running but connection fails, try:\n\n";
echo "1. Check if MySQL process is running:\n";
echo "   Get-Process | Where-Object {$_.Name -like '*mysql*'}\n\n";
echo "2. Verify MySQL is listening on 3306:\n";
echo "   netstat -ano | findstr \":3306\"\n\n";
echo "3. Check MySQL error log:\n";
echo "   C:\\xampp\\mysql\\data\\mysql_error.log\n\n";
echo "4. Restart MySQL:\n";
echo "   Kill the MySQL process and restart it\n\n";
echo "════════════════════════════════════════════════════════════\n";
?>
