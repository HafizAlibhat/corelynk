<?php
// Fix the product_processes table structure
$mysqli = new mysqli('localhost', 'root', '', 'production_management_system');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "Fixing product_processes table structure...\n";

// Drop the existing table and recreate it with correct structure
echo "Dropping existing product_processes table...\n";
if ($mysqli->query("DROP TABLE IF EXISTS product_processes")) {
    echo "✓ Table dropped successfully.\n";
} else {
    echo "✗ Error dropping table: " . $mysqli->error . "\n";
}

// Create the correct table structure
echo "Creating new product_processes table...\n";
$sql = "CREATE TABLE product_processes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    process_template_id INT UNSIGNED NOT NULL,
    sequence_order INT NOT NULL DEFAULT 1,
    custom_time_minutes INT NULL,
    custom_notes TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (process_template_id) REFERENCES process_templates(id) ON DELETE CASCADE,
    INDEX idx_product_processes_product (product_id),
    INDEX idx_product_processes_template (process_template_id),
    INDEX idx_product_processes_sequence (product_id, sequence_order),
    UNIQUE KEY unique_product_sequence (product_id, sequence_order)
) ENGINE=InnoDB";

if ($mysqli->query($sql)) {
    echo "✓ New product_processes table created successfully.\n";
} else {
    echo "✗ Error creating table: " . $mysqli->error . "\n";
}

$mysqli->close();
echo "\n✅ Table structure fixed!\n";
?>
