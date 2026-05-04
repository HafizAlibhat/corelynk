<?php
echo "=== MySQL Connection Test ===\n\n";

echo "Testing MySQL on 127.0.0.1:3306...\n";
$conn = @mysqli_connect('127.0.0.1', 'root', '', 'corelynk_db');

if ($conn) {
    echo "SUCCESS: Connected to MySQL!\n";
    echo "Version: " . mysqli_get_server_info($conn) . "\n";
    
    $result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM information_schema.TABLES");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        echo "Tables found: " . $row['cnt'] . "\n";
    }
    
    mysqli_close($conn);
} else {
    echo "FAILED: " . mysqli_connect_error() . "\n";
}
?>
