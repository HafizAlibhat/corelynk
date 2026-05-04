<?php
// Update admin password script
$mysqli = new mysqli('localhost', 'root', '', 'production_management_system');

if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

echo "Checking users table structure...\n";

// Check table structure first
$result = $mysqli->query("DESCRIBE users");
echo "Users table columns:\n";
while ($row = $result->fetch_assoc()) {
    echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\nChecking current admin user...\n";

// Check current admin user - first without password column
$result = $mysqli->query("SELECT * FROM users WHERE email='admin@production.local' OR username='admin' LIMIT 1");

if ($row = $result->fetch_assoc()) {
    echo "Found admin user:\n";
    foreach ($row as $column => $value) {
        echo "$column: $value\n";
    }
    
    // Generate new password hash
    $new_password = 'admin123';
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    echo "\nUpdating password to 'admin123'...\n";
    
    // Update password
    $stmt = $mysqli->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->bind_param("si", $password_hash, $row['id']);
    
    if ($stmt->execute()) {
        echo "✅ Password updated successfully!\n";
        echo "You can now login with:\n";
        echo "Email: " . $row['email'] . "\n";
        echo "Password: admin123\n";
    } else {
        echo "❌ Error updating password: " . $mysqli->error . "\n";
    }
    
    $stmt->close();
} else {
    echo "❌ No admin user found!\n";
    echo "Looking for any users...\n";
    
    $result = $mysqli->query("SELECT id, username, email FROM users LIMIT 5");
    if ($result->num_rows > 0) {
        echo "Found users:\n";
        while ($row = $result->fetch_assoc()) {
            echo "- ID: " . $row['id'] . ", Username: " . $row['username'] . ", Email: " . $row['email'] . "\n";
        }
    } else {
        echo "No users found in database!\n";
    }
}

$mysqli->close();
?>
