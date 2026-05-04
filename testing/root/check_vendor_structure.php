<?php
$mysqli = new mysqli('localhost', 'root', '', 'production_management_system');

echo "Vendors table structure:\n";
$result = $mysqli->query('DESCRIBE vendors');
while ($row = $result->fetch_assoc()) {
    echo "- {$row['Field']} ({$row['Type']})\n";
}

echo "\nSample vendor data:\n";
$result = $mysqli->query('SELECT * FROM vendors LIMIT 1');
if ($result && $result->num_rows > 0) {
    $vendor = $result->fetch_assoc();
    foreach ($vendor as $key => $value) {
        echo "- $key: " . ($value ?? 'NULL') . "\n";
    }
} else {
    echo "No vendors found\n";
}

$mysqli->close();
?>
