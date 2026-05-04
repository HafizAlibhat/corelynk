<?php
// Try to connect with suppressed errors
mysqli_report(MYSQLI_REPORT_OFF);

echo "=== Testing MySQL Connections ===\n\n";

$attempts = [
    '127.0.0.1:root:' ,
    'localhost:root:' ,
    '127.0.0.1:root:root',
    'localhost:root:root',
];

foreach ($attempts as $attempt) {
    [$host, $user, $pass] = explode(':', $attempt);
    echo "Trying: $user@$host (password:" . (empty($pass) ? 'empty' : $pass) . ") ... ";
    
    $conn = mysqli_connect($host, $user, $pass);
    
    if ($conn) {
        echo "✓ SUCCESS\n";
        echo "MySQL Version: " . mysqli_get_server_info($conn) . "\n\n";
        echo "Databases:\n";
        
        $result = mysqli_query($conn, "SHOW DATABASES;");
        while ($row = mysqli_fetch_array($result)) {
            echo "  - " . $row[0] . "\n";
        }
        
        mysqli_close($conn);
        exit(0);
    } else {
        echo "✗ " . mysqli_connect_error() . "\n";
    }
}

echo "\n✗ None of the connection attempts succeeded\n";
exit(1);
?>
