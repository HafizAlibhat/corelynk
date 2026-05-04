<?php
// Quick database checker
$_SERVER['CI_ENVIRONMENT'] = 'development';
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

try {
    require FCPATH . 'vendor/autoload.php';
    
    $config = new \Config\Database();
    $db = $config->connect();
    
    echo "=== Database Connection OK ===\n\n";
    
    // Check vendor_contacts table
    $result = $db->query('SELECT COUNT(*) as cnt FROM vendor_contacts')->getRow();
    echo "Total vendor contacts in DB: " . $result->cnt . "\n\n";
    
    // Show contacts by vendor
    $contacts = $db->query('
        SELECT v.id as vendor_id, v.name as vendor_name, 
               COUNT(vc.id) as contact_count
        FROM vendors v 
        LEFT JOIN vendor_contacts vc ON v.id = vc.vendor_id
        GROUP BY v.id
        ORDER BY v.id
    ')->getResultArray();
    
    foreach ($contacts as $row) {
        echo "Vendor {$row['vendor_id']}: {$row['vendor_name']} - {$row['contact_count']} contacts\n";
    }
    
    // Show actual contacts
    echo "\n=== All Contacts ===\n";
    $allContacts = $db->query('SELECT id, vendor_id, name FROM vendor_contacts ORDER BY vendor_id')->getResultArray();
    foreach ($allContacts as $c) {
        echo "ID {$c['id']}: Vendor {$c['vendor_id']} - {$c['name']}\n";
    }
    
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
?>
