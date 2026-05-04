<?php
$mysqli = new mysqli('localhost', 'root', '', 'production_management_system');
$result = $mysqli->query("SHOW TABLES LIKE '%workflow%'");
echo "Workflow-related tables:\n";
while ($row = $result->fetch_row()) {
    echo "- " . $row[0] . "\n";
}

// Also check if our sample workflow exists
$result = $mysqli->query("SELECT * FROM process_workflow_templates LIMIT 3");
echo "\nSample workflow templates:\n";
while ($row = $result->fetch_assoc()) {
    echo "ID: {$row['id']}, Name: {$row['name']}\n";
}
?>
