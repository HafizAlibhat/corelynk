<?php
// Direct controller test
require __DIR__ . '/vendor/autoload.php';

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/vendors/8';

try {
    $config = new \Config\Database();
    $db = $config->connect();
    
    // Test the query directly
    $vendorId = 8;
    $vendorContactModel = new \App\Models\VendorContactModel();
    $contacts = $vendorContactModel->where('vendor_id', $vendorId)
                                   ->orderBy('is_primary', 'DESC')
                                   ->orderBy('name', 'ASC')
                                   ->findAll();
    
    echo "=== Direct Model Test ===\n";
    echo "Vendor ID: $vendorId\n";
    echo "Contacts found: " . count($contacts) . "\n\n";
    
    if (count($contacts) > 0) {
        echo "=== Contacts ===\n";
        foreach ($contacts as $c) {
            echo "ID {$c['id']}: {$c['name']}\n";
        }
    }
    
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
?>
