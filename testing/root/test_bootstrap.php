<?php
// Simple test file to check if application works
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    echo "PHP Version: " . PHP_VERSION . "\n\n";
    
    // Check if Composer autoloader exists
    $composerPath = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($composerPath)) {
        die("ERROR: Composer autoloader not found at: $composerPath\n");
    }
    echo "✓ Composer autoloader found\n";
    
    // Load Composer
    require $composerPath;
    echo "✓ Composer loaded\n";
    
    // Check Database connection
    $db = mysqli_connect('localhost', 'root', '', 'corelynk_db');
    if (!$db) {
        die("ERROR: Database connection failed: " . mysqli_connect_error() . "\n");
    }
    echo "✓ Database connection successful\n";
    
    // Check for tables
    $result = $db->query("SHOW TABLES");
    $tableCount = $result->num_rows;
    echo "✓ Database has $tableCount tables\n\n";
    
    // Try to load CodeIgniter
    echo "Attempting to bootstrap CodeIgniter...\n\n";
    
    $paths = new Config\Paths();
    echo "✓ Paths config loaded\n";
    
    require $paths->systemDirectory . '/Boot.php';
    echo "✓ Boot file loaded\n";
    
    echo "\n SUCCESS - Application should work!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
?>
