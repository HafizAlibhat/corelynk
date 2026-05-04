<?php
// Fix MySQL root user - MariaDB method
$conn = mysqli_connect('localhost', 'root', '');

if (!$conn) {
    echo "Connection failed: " . mysqli_connect_error() . "\n";
    exit(1);
}

echo "✓ Connected to MySQL\n\n";

// Switch to mysql database  
mysqli_select_db($conn, 'mysql');

// Check current structure
$result = mysqli_query($conn, "SHOW COLUMNS FROM user;");
echo "User table columns:\n";
$columns = [];
while ($row = mysqli_fetch_assoc($result)) {
    echo "  - " . $row['Field'] . "\n";
    $columns[] = $row['Field'];
}

// Determine which column to use for password
$passwordCol = in_array('authentication_string', $columns) ? 'authentication_string' : 'Password';
echo "\nUsing password column: $passwordCol\n\n";

// Delete all root users and recreate with no password
echo "Clearing root user entries...\n";
if (mysqli_query($conn, "DELETE FROM user WHERE User='root';")) {
    echo "✓ Root users cleared\n";
}

// Create localhost root without password
echo "\nCreating root@localhost with no password...\n";
$createQuery = "INSERT INTO user (Host, User, Select_priv, Insert_priv, Update_priv, Delete_priv, Create_priv, Drop_priv, Reload_priv, Shutdown_priv, Process_priv, File_priv, Grant_priv, References_priv, Index_priv, Alter_priv, Show_db_priv, Super_priv, Create_tmp_table_priv, Lock_tables_priv, Execute_priv, Repl_slave_priv, Repl_client_priv, Create_view_priv, Show_view_priv, Create_routine_priv, Alter_routine_priv, Trigger_priv, Create_user_priv, Event_priv, Create_tablespace_priv, $passwordCol) VALUES ('localhost', 'root', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', '');";

if (mysqli_query($conn, $createQuery)) {
    echo "✓ Root user created\n";
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
}

// Flush privileges  
echo "\nFlushing privileges...\n";
if (mysqli_query($conn, "FLUSH PRIVILEGES;")) {
    echo "✓ Privileges flushed\n";
}

// Show current users
echo "\nCurrent root users:\n";
$result = mysqli_query($conn, "SELECT User, Host FROM user WHERE User='root';");
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo "  ✓ {$row['User']}@{$row['Host']}\n";
    }
} else {
    echo "  (none found)\n";
}

mysqli_close($conn);

echo "\n✓ Root user authentication reset complete!\n";
echo "\nNow restart MySQL (without skip-grant-tables) to apply changes.\n";
?>
