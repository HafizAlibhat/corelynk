<?php
// Simple database check and table creation
$mysqli = new mysqli('localhost', 'root', '', 'production_management_system');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "Connected successfully.\n";

// Check if tables exist
$tables = ['process_templates', 'product_processes'];
foreach ($tables as $table) {
    $result = $mysqli->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "Table $table already exists.\n";
    } else {
        echo "Table $table does not exist.\n";
    }
}

// Create process_templates table
echo "\nCreating process_templates table...\n";
$sql = "CREATE TABLE IF NOT EXISTS process_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    category VARCHAR(50) DEFAULT 'general',
    is_vendor_process BOOLEAN DEFAULT FALSE,
    vendor_id INT UNSIGNED NULL,
    standard_time_minutes INT DEFAULT 0,
    qc_checklist JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_process_templates_category (category),
    INDEX idx_process_templates_name (name)
) ENGINE=InnoDB";

if ($mysqli->query($sql)) {
    echo "✓ process_templates table created successfully.\n";
} else {
    echo "✗ Error creating process_templates: " . $mysqli->error . "\n";
}

// Create product_processes table
echo "\nCreating product_processes table...\n";
$sql = "CREATE TABLE IF NOT EXISTS product_processes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    process_template_id INT UNSIGNED NOT NULL,
    sequence_order INT NOT NULL DEFAULT 1,
    custom_time_minutes INT NULL,
    custom_notes TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_product_processes_product (product_id),
    INDEX idx_product_processes_template (process_template_id),
    INDEX idx_product_processes_sequence (product_id, sequence_order),
    UNIQUE KEY unique_product_sequence (product_id, sequence_order)
) ENGINE=InnoDB";

if ($mysqli->query($sql)) {
    echo "✓ product_processes table created successfully.\n";
} else {
    echo "✗ Error creating product_processes: " . $mysqli->error . "\n";
}

$mysqli->close();
echo "\nBasic table creation completed.\n";
?>
