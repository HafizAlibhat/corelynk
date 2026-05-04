<?php
// Direct database query to check invoices for customer 939
require 'vendor/autoload.php';

$db = \Config\Database::connect();

$customerId = 939;

echo "=== DEBUG: Customer 939 Invoices ===\n\n";

// 1. Check customer exists
$customer = $db->table('customers')->where('id', $customerId)->get()->getRow();
if (!$customer) {
    die("ERROR: Customer {$customerId} not found\n");
}
echo "✓ Customer found: {$customer->name} ({$customer->customer_code})\n\n";

// 2. List ALL invoices for this customer (no filters)
echo "2. ALL INVOICES (raw table):\n";
$allInvoices = $db->table('customer_invoices')
    ->where('customer_id', $customerId)
    ->orderBy('id', 'DESC')
    ->get()
    ->getResultArray();

if (empty($allInvoices)) {
    echo "   ❌ NO INVOICES FOUND in database for customer 939\n";
} else {
    echo "   Found " . count($allInvoices) . " invoices:\n";
    foreach ($allInvoices as $inv) {
        $status = isset($inv['status']) ? $inv['status'] : 'N/A';
        $deleted = isset($inv['deleted_at']) ? $inv['deleted_at'] : 'NULL';
        $amount = isset($inv['total_amount']) ? $inv['total_amount'] : (isset($inv['total']) ? $inv['total'] : (isset($inv['amount']) ? $inv['amount'] : 0));
        $invNumber = isset($inv['invoice_number']) ? $inv['invoice_number'] : 'N/A';
        echo "   - ID: {$inv['id']}, Number: {$invNumber}, Amount: {$amount}, Status: {$status}, Deleted: {$deleted}\n";
    }
}

echo "\n3. FILTERED INVOICES (deleted_at IS NULL AND status != 'cancelled'):\n";
$filtered = $db->table('customer_invoices')
    ->where('customer_id', $customerId)
    ->where('deleted_at IS NULL', null, false)
    ->where('status !=', 'cancelled')
    ->orderBy('id', 'DESC')
    ->get()
    ->getResultArray();

if (empty($filtered)) {
    echo "   ❌ NO INVOICES PASSED FILTERS\n";
    echo "\n   Let's check why each invoice was filtered out:\n";
    foreach ($allInvoices as $inv) {
        $reason = array();
        if (!is_null($inv['deleted_at']) && $inv['deleted_at'] !== '') {
            $reason[] = "deleted_at = " . $inv['deleted_at'];
        }
        if ($inv['status'] === 'cancelled') {
            $reason[] = "status = cancelled";
        }
        echo "   - Invoice {$inv['id']}: " . (empty($reason) ? "No reason to filter (BUG?)" : implode(", ", $reason)) . "\n";
    }
} else {
    echo "   Found " . count($filtered) . " invoices after filtering\n";
    foreach ($filtered as $inv) {
        $invNumber = isset($inv['invoice_number']) ? $inv['invoice_number'] : 'N/A';
        echo "   - {$inv['id']}: {$invNumber}\n";
    }
}

echo "\n4. CHECK PAYMENTS for each invoice:\n";
foreach ($allInvoices as $inv) {
    $invId = $inv['id'];
    $amount = isset($inv['total_amount']) ? $inv['total_amount'] : (isset($inv['total']) ? $inv['total'] : (isset($inv['amount']) ? $inv['amount'] : 0));
    
    $payments = $db->table('customer_payment_allocations cpa')
        ->select('cpa.id, cpa.amount, cp.customer_id, cp.status')
        ->join('customer_payments cp', 'cp.id = cpa.payment_id', 'left')
        ->where('cpa.invoice_id', $invId)
        ->get()
        ->getResultArray();
    
    $totalPaid = array_sum(array_column($payments, 'amount'));
    $outstanding = $amount - $totalPaid;
    
    echo "   Invoice {$invId}:\n";
    echo "     Amount: {$amount}\n";
    echo "     Paid: {$totalPaid}\n";
    echo "     Outstanding: {$outstanding}\n";
}

echo "\n=== END DEBUG ===\n";
?>
