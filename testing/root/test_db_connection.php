<?php
// Database connection test
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'corelynk_db';

echo "=== MySQL Connection Test ===\n\n";
echo "Configuration:\n";
echo "  Host: $host\n";
echo "  User: $user\n";
echo "  Password: " . (empty($password) ? "(empty)" : "(set)") . "\n";
echo "  Database: $database\n\n";

try {
    // Method 1: MySQLi (Procedural)
    echo "Attempting connection...\n";
    $conn = @mysqli_connect($host, $user, $password);
    
    if (!$conn) {
        echo "✗ Connection failed\n";
        echo "Error: " . mysqli_connect_error() . "\n\n";
        
        // Try with empty password explicitly
        echo "Retrying with explicit empty password...\n";
        $conn = mysqli_connect($host, $user, '', $database);
    }
    
    if ($conn) {
        echo "✓ Connected successfully to MySQL\n";
        echo "MySQL Version: " . mysqli_get_server_info($conn) . "\n\n";
        
        // Select database
        if (mysqli_select_db($conn, $database)) {
            echo "✓ Database '$database' selected\n";
            
            // Check tables
            $result = mysqli_query($conn, "SHOW TABLES;");
            $tableCount = mysqli_num_rows($result);
            echo "✓ Found $tableCount tables in database\n\n";
            
            // Show last 5 tables
            $result = mysqli_query($conn, "SHOW TABLES LIMIT 5;");
            echo "Sample tables:\n";
            while ($row = mysqli_fetch_array($result)) {
                echo "  - " . $row[0] . "\n";
            }
            
        } else {
            echo "✗ Could not select database: " . mysqli_error($conn) . "\n";
        }
        
        mysqli_close($conn);
        echo "\n✓ Connection test completed successfully!\n";
        
    } else {
        echo "✗ Connection failed: " . mysqli_connect_error() . "\n";
    }
    
} catch (\Throwable $e) {
    echo "✗ Exception: " . $e->getMessage() . "\n";
}
?>
