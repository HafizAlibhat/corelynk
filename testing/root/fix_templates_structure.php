<?php
$mysqli = new mysqli('localhost', 'root', '', 'production_management_system');
if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

echo "Fixing process_templates table structure...\n";

// Step 1: Add category_id column if it doesn't exist
$result = $mysqli->query("SHOW COLUMNS FROM process_templates LIKE 'category_id'");
if ($result->num_rows == 0) {
    echo "Adding category_id column...\n";
    $mysqli->query("ALTER TABLE process_templates ADD COLUMN category_id INT UNSIGNED NULL AFTER description");
    $mysqli->query("ALTER TABLE process_templates ADD FOREIGN KEY (category_id) REFERENCES process_categories(id) ON DELETE SET NULL");
}

// Step 2: Get process categories
echo "Getting process categories...\n";
$categories = [];
$result = $mysqli->query("SELECT id, name FROM process_categories");
while ($row = $result->fetch_assoc()) {
    $categories[strtolower($row['name'])] = $row['id'];
    echo "Category: " . $row['name'] . " (ID: " . $row['id'] . ")\n";
}

// Step 3: Map string categories to IDs
echo "\nMapping categories...\n";
$categoryMap = [
    'machining' => $categories['machining'] ?? null,
    'assembly' => $categories['assembly'] ?? null,
    'finishing' => $categories['finishing'] ?? null,
    'quality' => $categories['quality control'] ?? null,
    'packaging' => $categories['packaging'] ?? null,
    'testing' => $categories['testing'] ?? null,
];

// Step 4: Update records
$stmt = $mysqli->prepare("UPDATE process_templates SET category_id = ? WHERE category = ?");
foreach ($categoryMap as $categoryString => $categoryId) {
    if ($categoryId) {
        $stmt->bind_param("is", $categoryId, $categoryString);
        $stmt->execute();
        echo "✓ Updated $categoryString to category_id $categoryId\n";
    } else {
        echo "✗ No matching category found for $categoryString\n";
    }
}

// Step 5: Check results
echo "\nChecking results...\n";
$result = $mysqli->query("SELECT id, name, category, category_id FROM process_templates LIMIT 5");
while ($row = $result->fetch_assoc()) {
    echo "Template: " . $row['name'] . " | Old category: " . $row['category'] . " | New category_id: " . $row['category_id'] . "\n";
}

$stmt->close();
$mysqli->close();
echo "\nProcess templates table structure fixed!\n";
?>
