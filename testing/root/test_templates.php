<?php
require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap CodeIgniter
$app = \Config\Services::codeigniter();
$app->initialize();

// Test the ProcessTemplateModel
$processTemplateModel = new \App\Models\ProcessTemplateModel();
$processCategoryModel = new \App\Models\ProcessCategoryModel();

echo "Testing process templates loading...\n\n";

// Test 1: Get all categories
echo "1. Process Categories:\n";
$categories = $processCategoryModel->getActiveCategories();
foreach ($categories as $id => $name) {
    echo "   $id => $name\n";
}

echo "\n2. Process Templates with Categories:\n";
$templates = $processTemplateModel->getProcessTemplatesWithCategories();
foreach ($templates as $template) {
    echo "   " . $template['id'] . ": " . $template['name'] . " (Category: " . ($template['category_name'] ?? 'None') . ")\n";
}

echo "\nTotal templates: " . count($templates) . "\n";

if (count($templates) == 0) {
    echo "ERROR: No templates found!\n";
} else {
    echo "SUCCESS: Templates are loading correctly!\n";
}
?>
