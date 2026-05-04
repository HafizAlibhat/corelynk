<?php
require_once 'vendor/autoload.php';
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$db = new mysqli($_ENV['database_hostname'], $_ENV['database_username'], $_ENV['database_password'], $_ENV['database_name']);

if ($db->connect_error) {
    die("Connection error: " . $db->connect_error);
}

$result = $db->query("DESCRIBE customer_invoice_lines;");
echo "=== customer_invoice_lines columns ===\n";
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " (" . $row['Type'] . ") " . ($row['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
}

$result = $db->query("SELECT id, invoice_id, product_id, product_code, product_name, product_variant_id, description, quantity FROM customer_invoice_lines LIMIT 1;");
echo "\n=== Sample row from customer_invoice_lines ===\n";
if ($row = $result->fetch_assoc()) {
    print_r($row);
} else {
    echo "No rows found\n";
}

$db->close();
?>
