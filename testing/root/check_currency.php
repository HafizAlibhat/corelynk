<?php
require 'vendor/autoload.php';
$db = \Config\Database::connect();
$inv = $db->query("SELECT currency_code FROM customer_invoices WHERE invoice_number = 'INV-RI-S0001'")->getRowArray();
$je = $db->query("SELECT currency_code FROM journal_entries WHERE id = 1")->getRowArray();
$jl = $db->query("SELECT currency_code FROM journal_lines WHERE entry_id = 1")->getResultArray();
echo "Invoice: " . ($inv['currency_code'] ?? 'N/A') . "\n";
echo "Journal Entry: " . ($je['currency_code'] ?? 'N/A') . "\n";
foreach($jl as $l) {
    echo "Journal Line Account: " . ($l['currency_code'] ?? 'N/A') . "\n";
}
