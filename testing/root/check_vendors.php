<?php
// Simple database connection
$mysqli = new mysqli('localhost', 'root', '', 'production_management_system');

if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

echo "Checking vendors table:\n";
$result = $mysqli->query('SELECT id, name, contact_person FROM vendors ORDER BY id');
$vendors = $result->fetch_all(MYSQLI_ASSOC);

foreach ($vendors as $vendor) {
    echo "ID: " . $vendor['id'] . " - Name: " . $vendor['name'] . " - Contact: " . $vendor['contact_person'] . "\n";
}

echo "\nTotal vendors: " . count($vendors) . "\n";

// Check if there are any processes with invalid vendor_id
echo "\nChecking processes with vendor_id:\n";
$result2 = $mysqli->query('SELECT id, name, vendor_id FROM processes WHERE vendor_id IS NOT NULL ORDER BY id');
$processesWithVendors = $result2->fetch_all(MYSQLI_ASSOC);

foreach ($processesWithVendors as $process) {
    echo "Process ID: " . $process['id'] . " - Name: " . $process['name'] . " - Vendor ID: " . $process['vendor_id'] . "\n";
}

echo "\nTotal processes with vendors: " . count($processesWithVendors) . "\n";
