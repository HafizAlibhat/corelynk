<?php
define('ENVIRONMENT', 'development');
define('CI_ENVIRONMENT', 'development');

// Load CodeIgniter
require_once 'vendor/autoload.php';
require_once 'app/Config/Paths.php';

$paths = new \Config\Paths();
require_once 'app/Config/Boot.php'; 

try {
    $app = \Config\Services::codeigniter();
    $app->initialize();
    $app->boot();
    
    // Now we can use CI functions
    $db = \Config\Database::connect();
    
    echo "=== Using CodeIgniter Database ===\n\n";
    
    // Check allocations
    $result = $db->query("SELECT * FROM vendor_payment_allocations");
    $allocations = $result->getResultArray();
    
    echo "Allocations (" . count($allocations) . "):\n";
    foreach ($allocations as $row) {
        echo "  ID {$row['id']}: payment_id={$row['payment_id']}, bill_id={$row['vendor_bill_id']}, amount={$row['amount']}\n";
    }
    
    // Check payments
    $result = $db->query("SELECT * FROM vendor_payments");
    $payments = $result->getResultArray();
    
    echo "\nPayments (" . count($payments) . "):\n";
    foreach ($payments as $row) {
        echo "  ID {$row['id']}: vendor_id={$row['vendor_id']}, status={$row['status']}, amount={$row['amount']}\n";
    }
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
?>
