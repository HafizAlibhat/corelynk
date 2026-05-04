<?php
// Import simple_schema.sql
$db = new mysqli('localhost', 'root', '', 'pro_sys');
if ($db->connect_error) die('Connection failed: ' . $db->connect_error);

$sql = file_get_contents('simple_schema.sql');

// Split by ; and execute each statement
$statements = array_filter(array_map('trim', explode(';', $sql)));

echo "Importing schema...\n";
$success = 0;
$errors = 0;

foreach ($statements as $statement) {
    if (empty($statement)) continue;
    
    if ($db->query($statement)) {
        $success++;
        // Extract table name for feedback
        if (preg_match('/CREATE TABLE\s+`?(\w+)`?/i', $statement, $matches)) {
            echo "✓ Created table: {$matches[1]}\n";
        }
    } else {
        $errors++;
        echo "✗ Error: " . $db->error . "\n";
    }
}

echo "\nSummary: {$success} statements executed successfully, {$errors} errors\n";
$db->close();
