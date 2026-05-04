<?php
// Simple test to access process templates directly
require_once 'vendor/autoload.php';

try {
    // Initialize CodeIgniter
    $app = \Config\Services::codeigniter();
    $app->initialize();
    
    // Create process templates model
    $processTemplateModel = new \App\Models\ProcessTemplateModel();
    
    // Try to get some templates
    $templates = $processTemplateModel->findAll();
    
    echo "Found " . count($templates) . " process templates:\n";
    
    foreach (array_slice($templates, 0, 5) as $template) {
        echo "- {$template['name']} ({$template['category']})\n";
    }
    
    echo "\n✅ Direct model access works!\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
