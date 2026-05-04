<?php
// Simple debug script for process templates
require_once 'vendor/autoload.php';

// Test if the controller class exists
if (class_exists('App\Controllers\ProcessTemplates')) {
    echo "✓ ProcessTemplates controller exists\n";
} else {
    echo "✗ ProcessTemplates controller not found\n";
}

// Test if the model classes exist
if (class_exists('App\Models\ProcessTemplateModel')) {
    echo "✓ ProcessTemplateModel exists\n";
} else {
    echo "✗ ProcessTemplateModel not found\n";
}

if (class_exists('App\Models\ProductProcessModel')) {
    echo "✓ ProductProcessModel exists\n";
} else {
    echo "✗ ProductProcessModel not found\n";
}

// Test database connection and check tables
try {
    $mysqli = new mysqli('localhost', 'root', '', 'production_management_system');
    
    // Check if tables exist
    $result = $mysqli->query("SHOW TABLES LIKE 'process_templates'");
    if ($result->num_rows > 0) {
        echo "✓ process_templates table exists\n";
    } else {
        echo "✗ process_templates table not found\n";
    }
    
    $result = $mysqli->query("SHOW TABLES LIKE 'product_processes'");
    if ($result->num_rows > 0) {
        echo "✓ product_processes table exists\n";
    } else {
        echo "✗ product_processes table not found\n";
    }
    
    $mysqli->close();
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
}

echo "\nDebug completed.\n";
?>
