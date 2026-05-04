<?php
require 'vendor/autoload.php';

$config = new \Config\Database();
$db = \CodeIgniter\Database\Config::connect();

echo "=== Testing Process Categories Integration ===\n\n";

echo "1. Checking processes table structure:\n";
$query = $db->query('DESCRIBE processes');
$fields = $query->getResultArray();
foreach($fields as $field) {
    if (strpos($field['Field'], 'category') !== false) {
        echo "✓ " . $field['Field'] . ' - ' . $field['Type'] . "\n";
    }
}

echo "\n2. Checking process categories:\n";
$query = $db->query('SELECT * FROM process_categories WHERE is_active = 1');
$categories = $query->getResultArray();
foreach($categories as $category) {
    echo "✓ Category: " . $category['name'] . " (ID: " . $category['id'] . ")\n";
}

echo "\n3. Checking processes with categories:\n";
$query = $db->query('
    SELECT p.id, p.name, p.category_id, pc.name as category_name 
    FROM processes p 
    LEFT JOIN process_categories pc ON pc.id = p.category_id 
    LIMIT 5
');
$processes = $query->getResultArray();
foreach($processes as $process) {
    $categoryInfo = $process['category_name'] ? $process['category_name'] : 'No Category';
    echo "✓ Process: " . $process['name'] . " | Category: " . $categoryInfo . "\n";
}

echo "\n4. Testing category assignment:\n";
if (!empty($categories) && !empty($processes)) {
    $firstProcess = $processes[0];
    $firstCategory = $categories[0];
    
    // Update first process with first category
    $query = $db->query('UPDATE processes SET category_id = ? WHERE id = ?', 
                       [$firstCategory['id'], $firstProcess['id']]);
    
    if ($query) {
        echo "✓ Successfully assigned category '" . $firstCategory['name'] . "' to process '" . $firstProcess['name'] . "'\n";
        
        // Verify the assignment
        $verifyQuery = $db->query('
            SELECT p.name, pc.name as category_name 
            FROM processes p 
            JOIN process_categories pc ON pc.id = p.category_id 
            WHERE p.id = ?
        ', [$firstProcess['id']]);
        
        $result = $verifyQuery->getRowArray();
        if ($result) {
            echo "✓ Verification: Process '" . $result['name'] . "' has category '" . $result['category_name'] . "'\n";
        }
    } else {
        echo "✗ Failed to assign category\n";
    }
}

echo "\n=== Test Complete ===\n";
?>
