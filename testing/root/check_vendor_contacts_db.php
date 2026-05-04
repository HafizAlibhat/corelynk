<?php
require 'vendor/autoload.php';
$config = new \Config\Database();
$db = $config->connect();

echo "=== Checking Vendor Contacts in Database ===\n\n";

// Check if table exists
$tables = $db->query("SHOW TABLES LIKE 'vendor_contacts'")->getResultArray();
if (empty($tables)) {
    echo "ERROR: vendor_contacts table does not exist!\n";
    exit(1);
}

// Get all contacts
$result = $db->query('SELECT id, vendor_id, name, phone, designation FROM vendor_contacts ORDER BY vendor_id, id')->getResultArray();

if (empty($result)) {
    echo "No contacts found in database.\n";
} else {
    echo "Found " . count($result) . " contacts:\n\n";
    foreach ($result as $row) {
        echo "ID: " . $row['id'] . " | Vendor: " . $row['vendor_id'] . " | Name: " . $row['name'] . " | Phone: " . $row['phone'] . " | Designation: " . $row['designation'] . "\n";
    }
}

// Get vendors with contact count
echo "\n=== Vendors with Contact Count ===\n\n";
$vendorResult = $db->query('
    SELECT v.id, v.name, COUNT(vc.id) as contact_count 
    FROM vendors v 
    LEFT JOIN vendor_contacts vc ON v.id = vc.vendor_id 
    GROUP BY v.id 
    ORDER BY v.id
')->getResultArray();

foreach ($vendorResult as $vendor) {
    echo "Vendor " . $vendor['id'] . " (" . $vendor['name'] . "): " . $vendor['contact_count'] . " contacts\n";
}
?>
