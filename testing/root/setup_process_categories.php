<?php
$host = 'localhost';
$dbname = 'production_management_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Creating process_categories table...\n";
    
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'process_categories'");
    if ($stmt->rowCount() > 0) {
        echo "Table already exists. Checking structure...\n";
    } else {
        $sql = "CREATE TABLE process_categories (
            id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_name (name),
            INDEX idx_active (is_active),
            INDEX idx_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "✓ Process categories table created successfully!\n";
    }
    
    // Add default categories
    $stmt = $pdo->query('SELECT COUNT(*) FROM process_categories');
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        echo "Adding default process categories...\n";
        
        $categories = [
            ['Manufacturing', 'Core manufacturing and production processes'],
            ['Quality Control', 'Quality assurance and inspection processes'],
            ['Assembly', 'Product assembly and sub-assembly processes'],
            ['Packaging', 'Packaging and labeling processes'],
            ['Testing', 'Product testing and validation processes'],
            ['Machining', 'Machining and cutting processes'],
            ['Welding', 'Welding and joining processes'],
            ['Finishing', 'Surface finishing and coating processes'],
            ['Material Handling', 'Material movement and handling processes'],
            ['Setup', 'Machine and equipment setup processes']
        ];
        
        $stmt = $pdo->prepare('INSERT INTO process_categories (name, description, is_active, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW())');
        
        foreach ($categories as $category) {
            $stmt->execute([$category[0], $category[1]]);
            echo "Added: {$category[0]}\n";
        }
        
        echo "✓ Default process categories added successfully!\n";
    } else {
        echo "Process categories already exist ($count total).\n";
    }
    
    // Update existing process_templates table to add category_id if it doesn't exist
    echo "\nChecking process_templates table for category_id column...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM process_templates LIKE 'category_id'");
    if ($stmt->rowCount() == 0) {
        echo "Adding category_id column to process_templates...\n";
        $pdo->exec("ALTER TABLE process_templates ADD COLUMN category_id INT(10) UNSIGNED NULL AFTER name");
        $pdo->exec("ALTER TABLE process_templates ADD FOREIGN KEY (category_id) REFERENCES process_categories(id) ON DELETE SET NULL");
        echo "✓ Category column added to process_templates!\n";
    } else {
        echo "Category column already exists in process_templates.\n";
    }
    
    // Update existing processes table to add category_id if it doesn't exist
    echo "\nChecking processes table for category_id column...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM processes LIKE 'category_id'");
    if ($stmt->rowCount() == 0) {
        echo "Adding category_id column to processes...\n";
        $pdo->exec("ALTER TABLE processes ADD COLUMN category_id INT(10) UNSIGNED NULL AFTER name");
        $pdo->exec("ALTER TABLE processes ADD FOREIGN KEY (category_id) REFERENCES process_categories(id) ON DELETE SET NULL");
        echo "✓ Category column added to processes!\n";
    } else {
        echo "Category column already exists in processes.\n";
    }
    
    echo "\n✅ Process categories setup completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
