<?php
$mysqli = new mysqli('localhost', 'root', '', 'production_management_system');
if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

echo "Testing process templates data...\n\n";

// Test the query that should be used in the controller
$sql = "SELECT pt.*, pc.name as category_name 
        FROM process_templates pt 
        LEFT JOIN process_categories pc ON pc.id = pt.category_id 
        WHERE pt.is_active = 1 
        ORDER BY pc.name, pt.name";

$result = $mysqli->query($sql);

echo "Query: " . $sql . "\n\n";
echo "Results:\n";

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . " | Name: " . $row['name'] . " | Category: " . ($row['category_name'] ?? 'None') . " | Category ID: " . $row['category_id'] . "\n";
    }
    echo "\nTotal templates found: " . $result->num_rows . "\n";
} else {
    echo "No templates found!\n";
}

$mysqli->close();
?>
