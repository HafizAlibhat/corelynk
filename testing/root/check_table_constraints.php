<?php
// Check table constraints and structure
$mysqli = new mysqli("localhost", "root", "", "production_management");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "Checking process_templates table constraints...\n\n";

// Check if table exists and its structure
$result = $mysqli->query("SHOW CREATE TABLE process_templates");
if ($result) {
    $row = $result->fetch_row();
    echo "Current process_templates structure:\n";
    echo $row[1] . "\n\n";
} else {
    echo "process_templates table does not exist or error: " . $mysqli->error . "\n\n";
}

// Check foreign key constraints referencing process_templates
echo "Checking foreign key constraints that reference process_templates:\n";
$result = $mysqli->query("
    SELECT 
        TABLE_NAME,
        COLUMN_NAME,
        CONSTRAINT_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE REFERENCED_TABLE_SCHEMA = 'production_management' 
    AND REFERENCED_TABLE_NAME = 'process_templates'
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Table: {$row['TABLE_NAME']}, Column: {$row['COLUMN_NAME']}, Constraint: {$row['CONSTRAINT_NAME']}\n";
    }
} else {
    echo "No foreign key constraints reference process_templates\n";
}

echo "\n";

// Check what's in the current process_templates table
$result = $mysqli->query("SELECT * FROM process_templates LIMIT 5");
if ($result) {
    echo "Current process_templates data (first 5 rows):\n";
    while ($row = $result->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "Error reading process_templates: " . $mysqli->error . "\n";
}

$mysqli->close();
?>
