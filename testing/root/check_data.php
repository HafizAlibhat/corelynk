<?php
$dsn = 'mysql:host=localhost;dbname=production_management_system;charset=utf8mb4';
$username = 'root';
$password = '';

try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== ACTIVE PROCESSES ===\n";
    $stmt = $pdo->query('SELECT id, name, category_id FROM processes WHERE is_active = 1 ORDER BY id');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: {$row['id']}, Name: {$row['name']}, Category: {$row['category_id']}\n";
    }
    
    echo "\n=== PRODUCTS ===\n";
    $stmt = $pdo->query('SELECT id, name FROM products WHERE is_active = 1 ORDER BY id LIMIT 3');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: {$row['id']}, Name: {$row['name']}\n";
    }
    
    echo "\n=== CURRENT PRODUCT_PROCESSES ===\n";
    $stmt = $pdo->query('SELECT product_id, process_id, process_template_id FROM product_processes WHERE is_active = 1');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Product: {$row['product_id']}, Process: {$row['process_id']}, Template: {$row['process_template_id']}\n";
    }
    
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
