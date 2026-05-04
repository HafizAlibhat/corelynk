<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

// Check if product_variants table exists
$result = $mysqli->query("SHOW TABLES LIKE 'product_variants'");
if ($result && $result->num_rows > 0) {
    echo "product_variants table exists\n\n";
    
    // Get count of variants
    $variantCount = $mysqli->query("SELECT COUNT(*) as cnt FROM product_variants")->fetch_assoc();
    echo "Total variants: " . $variantCount['cnt'] . "\n";
    
    // Get count of PO lines
    $poLineCount = $mysqli->query("SELECT COUNT(*) as cnt FROM purchase_order_lines")->fetch_assoc();
    echo "Total PO lines: " . $poLineCount['cnt'] . "\n";
    
    // Get count of PO lines with variant_id set
    $poLineVariantCount = $mysqli->query("SELECT COUNT(*) as cnt FROM purchase_order_lines WHERE variant_id IS NOT NULL")->fetch_assoc();
    echo "PO lines with variant_id: " . $poLineVariantCount['cnt'] . "\n";
    
    // Show sample PO lines  
    echo "\n=== Sample PO lines ===\n";
    $poLines = $mysqli->query("SELECT id, product_id, variant_id, description FROM purchase_order_lines LIMIT 5");
    while($row = $poLines->fetch_assoc()) {
        echo "PO Line " . $row['id'] . ": product_id=" . $row['product_id'] . ", variant_id=" . $row['variant_id'] . ", desc=" . $row['description'] . "\n";
    }
} else {
    echo "product_variants table does NOT exist\n";
}

$mysqli->close();
?>
