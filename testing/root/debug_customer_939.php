<?php
// Debug script for customer 939 invoices

require 'vendor/autoload.php';

$db = \Config\Database::connect();

$customer_id = 939;

echo "=== CUSTOMER 939 DEBUG ===\n\n";

// Check customer exists
$customer = $db->table('customers')->where('id', $customer_id)->get()->getResultArray();
echo "1. CUSTOMER:\n";
var_dump($customer);
echo "\n";

// Check all invoices for this customer (no filters)
$invoices = $db->table('customer_invoices')
    ->where('customer_id', $customer_id)
    ->get()
    ->getResultArray();

echo "2. ALL INVOICES (no filters):\n";
echo "Count: " . count($invoices) . "\n";
var_dump($invoices);
echo "\n";

// Check with status filter
$invoices_filtered = $db->table('customer_invoices')
    ->where('customer_id', $customer_id)
    ->where('status !=', 'cancelled')
    ->where('is_deleted', 0)
    ->get()
    ->getResultArray();

echo "3. FILTERED INVOICES (status != cancelled, not deleted):\n";
echo "Count: " . count($invoices_filtered) . "\n";
var_dump($invoices_filtered);
echo "\n";

// Check if there's an outstanding amount
if (!empty($invoices_filtered)) {
    echo "4. CHECKING PAYMENTS FOR EACH INVOICE:\n";
    foreach ($invoices_filtered as $inv) {
        $inv_id = $inv['id'];
        $total_amount = $inv['total_amount'] ?? $inv['total'] ?? $inv['amount'] ?? 0;
        
        echo "\nInvoice ID: {$inv_id}, Amount: {$total_amount}\n";
        
        // Get payments for this invoice
        $payments = $db->table('customer_payment_allocations')
            ->where('invoice_id', $inv_id)
            ->get()
            ->getResultArray();
        
        $paid_amount = array_sum(array_column($payments, 'amount'));
        echo "  Paid: {$paid_amount}\n";
        echo "  Outstanding: " . ($total_amount - $paid_amount) . "\n";
    }
}

// Check the view/calculation with database joins
echo "\n5. QUERY INSPECTION - Direct calculation:\n";
$query = $db->table('customer_invoices ci')
    ->select('ci.id, ci.number, ci.total_amount, ci.status, COALESCE(SUM(cpa.amount), 0) as paid_amount')
    ->where('ci.customer_id', $customer_id)
    ->where('ci.is_deleted', 0)
    ->where('ci.status !=', 'cancelled')
    ->join('customer_payment_allocations cpa', 'cpa.invoice_id = ci.id', 'left')
    ->groupBy('ci.id, ci.number, ci.total_amount, ci.status')
    ->get();

var_dump($query->getResultArray());
