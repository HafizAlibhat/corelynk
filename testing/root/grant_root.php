<?php
// Use GRANT method to create root user with no password
$conn = mysqli_connect('localhost', 'root', '');

if (!$conn) {
    echo "Error: " . mysqli_connect_error() . "\n";
    exit(1);
}

echo "✓ Connected to MySQL\n\n";

// GRANT all privileges to root@localhost with empty password
$query = "GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' IDENTIFIED BY '' WITH GRANT OPTION;";

echo "Executing: GRANT ALL PRIVILEGES to root@localhost\n";
if (mysqli_query($conn, $query)) {
    echo "✓ Grant successful\n\n";
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
}

// Flush privileges
echo "Flushing privileges...\n";
if (mysqli_query($conn, "FLUSH PRIVILEGES;")) {
    echo "✓ Privileges flushed\n";
}

mysqli_close($conn);

echo "\n✓ Root user authentication reset!\n";
echo "\nNow restart MySQL normally to apply changes.\n";
?>
