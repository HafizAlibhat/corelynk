<?php
// Test PDF generation with logo
require_once __DIR__ . '/vendor/autoload.php'; // Adjust path if needed

use App\Libraries\InvoicePdfGenerator;

// Mock payload with company logo
$payload = [
    'invoice' => [
        'id' => 1,
        'invoice_number' => 'INV-TEST',
        'total' => 100.00,
        'currency' => 'USD',
    ],
    'lines' => [],
    'company' => [
        'name' => 'Test Company',
        'logo_path' => 'uploads/company/company-logo.png', // Adjust to actual path
    ],
    'customer' => ['name' => 'Test Customer'],
    'customerAddress' => [],
];

$generator = new InvoicePdfGenerator();
try {
    $pdfContent = $generator->generate($payload);
    file_put_contents(__DIR__ . '/test_invoice.pdf', $pdfContent);
    echo "PDF generated successfully. Check test_invoice.pdf\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>