<?php
$host = 'localhost';
$dbname = 'production_management_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Checking foreign key dependencies...\n";
    
    // Check product_categories
    $stmt = $pdo->query('SELECT COUNT(*) FROM product_categories');
    echo "Product categories: " . $stmt->fetchColumn() . "\n";
    
    // Check if there are any users (for created_by, updated_by)
    $stmt = $pdo->query('SELECT COUNT(*) FROM users');
    echo "Users: " . $stmt->fetchColumn() . "\n";
    
    // Check table structure for products
    $stmt = $pdo->query('DESCRIBE products');
    echo "\nProducts table structure:\n";
    while ($row = $stmt->fetch()) {
        echo "- {$row['Field']}: {$row['Type']} " . ($row['Null'] == 'YES' ? '(nullable)' : '(required)') . "\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
