<?php
$db = new mysqli('localhost', 'root', '', 'corelynk_db');

// Check quotation_lines
$result = $db->query("SELECT * FROM quotation_lines WHERE quotation_id = 2 LIMIT 1;");
if ($row = $result->fetch_assoc()) {
    echo "Quotation Lines columns: " . implode(', ', array_keys($row)) . "\n";
}

// Check if they have product_code or product_variant_id
$result = $db->query("SELECT id, product_id, product_code, product_name, description FROM quotation_lines WHERE quotation_id = 2 LIMIT 3;");
echo "\n=== Quotation Lines ===\n";
while ($row = $result->fetch_assoc()) {
    print_r($row);
}

$db->close();
?>
