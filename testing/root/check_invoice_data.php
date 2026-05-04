<?php
// Check what data is in the invoice and products
require_once 'vendor/autoload.php';
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$db = new mysqli($_ENV['database_hostname'], $_ENV['database_username'], $_ENV['database_password'], $_ENV['database_name']);

if ($db->connect_error) {
    die("Connection error: " . $db->connect_error);
}

// Get invoice data
echo "=== Invoice INV-RI-S0002 ===\n";
$result = $db->query("SELECT id, invoice_number FROM customer_invoices WHERE invoice_number = 'INV-RI-S0002' LIMIT 1;");
if ($row = $result->fetch_assoc()) {
    $inv_id = $row['id'];
    echo "Invoice ID: " . $inv_id . "\n\n";
    
    // Get the lines
    echo "=== Invoice Lines ===\n";
    $result = $db->query("SELECT id, product_id, product_code, product_name, product_variant_id, description, quantity FROM customer_invoice_lines WHERE invoice_id = $inv_id LIMIT 3;");
    while ($row = $result->fetch_assoc()) {
        echo "Line ID: " . $row['id'] . "\n";
        echo "  product_id: " . ($row['product_id'] ?? 'NULL') . "\n";
        echo "  product_code: " . ($row['product_code'] ?? 'NULL') . "\n";
        echo "  product_name: " . ($row['product_name'] ?? 'NULL') . "\n";
        echo "  product_variant_id: " . ($row['product_variant_id'] ?? 'NULL') . "\n";
        echo "  description: " . substr($row['description'] ?? '', 0, 60) . "\n\n";
        
        // Look up the product
        if (!empty($row['product_id'])) {
            $pid = $row['product_id'];
            $pres = $db->query("SELECT id, code, name FROM products WHERE id = $pid LIMIT 1;");
            if ($prow = $pres->fetch_assoc()) {
                echo "  Product in DB:\n";
                echo "    id: " . $prow['id'] . "\n";
                echo "    code: " . ($prow['code'] ?? 'NULL') . "\n";
                echo "    name: " . ($prow['name'] ?? 'NULL') . "\n\n";
            }
        }
    }
} else {
    echo "Invoice not found\n";
}

$db->close();
?>
