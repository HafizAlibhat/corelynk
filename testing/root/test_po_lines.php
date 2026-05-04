<?php
$db = new mysqli('localhost', 'root', '', 'corelynk_db');
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

echo "Connected to database\n\n";

// Get a sample PO ID
$result = $db->query("SELECT * FROM purchase_orders ORDER BY id DESC LIMIT 1");
$po = $result->fetch_assoc();
if (!$po) {
    echo "No PO found in database\n";
    exit;
}

echo "Testing PO ID: " . $po['id'] . "\n";
echo "PO Code: " . $po['code'] . "\n\n";

// Get PO lines
$lines_result = $db->query("SELECT * FROM purchase_order_lines WHERE po_id = " . $po['id']);
$lines = [];
while ($row = $lines_result->fetch_assoc()) {
    $lines[] = $row;
}

echo "Found " . count($lines) . " lines\n\n";

if (empty($lines)) {
    echo "No lines found for this PO\n";
    exit;
}

// Check what columns exist
echo "=== First line columns ===\n";
print_r(array_keys($lines[0]));

echo "\n\n=== First line data ===\n";
print_r($lines[0]);
