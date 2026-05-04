<?php
// Simple MySQL check - no CI framework needed
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== Vendor Contacts Status ===\n\n";

// Check table
$result = $mysqli->query("SELECT COUNT(*) as cnt FROM vendor_contacts");
$row = $result->fetch_assoc();
echo "Total vendor contacts: " . $row['cnt'] . "\n\n";

// List all vendors with contact counts
echo "=== Vendors & Contact Counts ===\n";
$result = $mysqli->query("
    SELECT v.id, v.name, COUNT(vc.id) as contact_count
    FROM vendors v
    LEFT JOIN vendor_contacts vc ON v.id = vc.vendor_id
    GROUP BY v.id, v.name
    ORDER BY v.id
");

while ($row = $result->fetch_assoc()) {
    echo "Vendor {$row['id']}: {$row['name']} - {$row['contact_count']} contacts\n";
}

// Show actual contacts
echo "\n=== Actual Contacts ===\n";
$result = $mysqli->query("SELECT id, vendor_id, name, designation FROM vendor_contacts ORDER BY vendor_id, id");

if ($result->num_rows === 0) {
    echo "No contacts found!\n";
} else {
    while ($row = $result->fetch_assoc()) {
        echo "ID {$row['id']}: Vendor {$row['vendor_id']} - {$row['name']} ({$row['designation']})\n";
    }
}

$mysqli->close();
?>
