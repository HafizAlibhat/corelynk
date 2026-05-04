<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $pdo = new PDO('mysql:host=localhost;dbname=production_management_system', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Checking product_processes for product ID 10:\n";
    $stmt = $pdo->query('SELECT pp.id, pp.process_template_id, pt.name as template_name FROM product_processes pp LEFT JOIN process_templates pt ON pt.id = pp.process_template_id WHERE pp.product_id = 10');
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Product Process ID: " . $row['id'] . "\n";
        echo "Process Template ID: " . ($row['process_template_id'] ?? 'NULL') . "\n";
        echo "Template Name: " . ($row['template_name'] ?? 'NULL') . "\n";
        echo "----\n";
    }
    
    echo "\nChecking process_templates:\n";
    $stmt2 = $pdo->query('SELECT id, name FROM process_templates WHERE id IN (SELECT DISTINCT process_template_id FROM product_processes WHERE product_id = 10)');
    while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
        echo "Template ID: " . $row['id'] . ", Name: " . $row['name'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
