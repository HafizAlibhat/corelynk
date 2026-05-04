<?php
// Reset MySQL root password using ALTER USER command
$conn = mysqli_connect('localhost', 'root', '');

if ($conn) {
    echo "✓ Connected to MySQL\n\n";
    echo "Resetting root user password...\n";
    
    // MariaDB/MySQL password reset using ALTER USER
    $cmd = "ALTER USER 'root'@'localhost' IDENTIFIED BY '';";
    
    if (mysqli_query($conn, $cmd)) {
        echo "✓ ALTER USER succeeded\n";
    } else {
        $error = mysqli_error($conn);
        echo "✗ ALTER USER failed: $error\n";
        
        // Try alternative: SET PASSWORD
        echo "\nTrying SET PASSWORD...\n";
        $cmd = "SET PASSWORD FOR 'root'@'localhost' = '';";
        if (mysqli_query($conn, $cmd)) {
            echo "✓ SET PASSWORD succeeded\n";
        } else {
            echo "✗ SET PASSWORD failed: " . mysqli_error($conn) . "\n";
        }
    }
    
    // Flush privileges
    if (mysqli_query($conn, "FLUSH PRIVILEGES;")) {
        echo "✓ FLUSH PRIVILEGES succeeded\n";
    }
    
    mysqli_close($conn);
    echo "\n✓ Reset attempt complete!\n";
    echo "\nNote: You may need to restart MySQL for changes to take effect.\n";
} else {
    echo "✗ Could not connect: " . mysqli_connect_error() . "\n";
}
?>
