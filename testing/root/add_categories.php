<?php
$host = 'localhost';
$dbname = 'production_management_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if product_categories table exists and has data
    $stmt = $pdo->query('SELECT COUNT(*) FROM product_categories');
    $count = $stmt->fetchColumn();
    echo "Current product categories: $count\n";
    
    if ($count == 0) {
        echo "Adding default product categories...\n";
        
        $categories = [
            ['Electronics', 'Electronic components and devices'],
            ['Mechanical', 'Mechanical parts and assemblies'],
            ['Chemical', 'Chemical products and materials'],
            ['Textile', 'Fabric and textile products'],
            ['Food & Beverage', 'Food and beverage products'],
            ['Pharmaceutical', 'Medical and pharmaceutical products'],
            ['Automotive', 'Automotive parts and components'],
            ['Construction', 'Building and construction materials']
        ];
        
        $stmt = $pdo->prepare('INSERT INTO product_categories (name, description, is_active, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW())');
        
        foreach ($categories as $category) {
            $stmt->execute([$category[0], $category[1]]);
            echo "Added: {$category[0]}\n";
        }
        
        echo "Product categories added successfully!\n";
    } else {
        echo "Product categories already exist.\n";
        
        // Show existing categories
        $stmt = $pdo->query('SELECT id, name FROM product_categories ORDER BY name');
        while ($row = $stmt->fetch()) {
            echo "- {$row['id']}: {$row['name']}\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
