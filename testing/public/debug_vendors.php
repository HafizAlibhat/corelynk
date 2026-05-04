<?php
// Simple debug script to check vendors

require_once 'vendor/autoload.php';

// Load CodeIgniter
$app = require_once FCPATH . '../app/Config/App.php';
$db = \Config\Database::connect();

// Check vendors table
$query = $db->query("SELECT * FROM vendors ORDER BY created_at DESC LIMIT 10");
$vendors = $query->getResultArray();

echo "<h3>Recent Vendors (Last 10):</h3>";
echo "<pre>";
print_r($vendors);
echo "</pre>";

echo "<h3>Total Vendor Count:</h3>";
$countQuery = $db->query("SELECT COUNT(*) as count FROM vendors");
$count = $countQuery->getRow();
echo "Total vendors: " . $count->count;

echo "<h3>Active Vendors:</h3>";
$activeQuery = $db->query("SELECT COUNT(*) as count FROM vendors WHERE is_active = 1");
$activeCount = $activeQuery->getRow();
echo "Active vendors: " . $activeCount->count;

echo "<h3>Sample Vendor Query:</h3>";
$sampleQuery = $db->query("SELECT vendors.*, COUNT(processes.id) as process_count FROM vendors LEFT JOIN processes ON processes.vendor_id = vendors.id GROUP BY vendors.id ORDER BY vendors.name ASC LIMIT 5");
$sample = $sampleQuery->getResultArray();
echo "<pre>";
print_r($sample);
echo "</pre>";
?>
