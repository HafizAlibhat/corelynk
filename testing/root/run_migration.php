<?php
// Execute the process templates database migration
require_once 'vendor/autoload.php';

// Database connection
$config = [
    'hostname' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'production_management_system',
    'driver'   => 'mysqli'
];

try {
    // Connect to database
    $mysqli = new mysqli($config['hostname'], $config['username'], $config['password'], $config['database']);
    
    if ($mysqli->connect_error) {
        die("Connection failed: " . $mysqli->connect_error);
    }
    
    echo "Connected to database successfully.\n";
    
    // Read and execute the SQL file
    $sqlFile = 'database/add_process_templates.sql';
    if (!file_exists($sqlFile)) {
        die("SQL file not found: $sqlFile\n");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Split SQL by semicolons and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue; // Skip empty lines and comments
        }
        
        if ($mysqli->query($statement)) {
            $successCount++;
            echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
        } else {
            $errorCount++;
            echo "✗ Error: " . $mysqli->error . "\n";
            echo "Statement: " . substr($statement, 0, 100) . "...\n";
        }
    }
    
    echo "\n=== Migration Summary ===\n";
    echo "Successful statements: $successCount\n";
    echo "Failed statements: $errorCount\n";
    
    if ($errorCount === 0) {
        echo "✅ Migration completed successfully!\n";
    } else {
        echo "⚠️ Migration completed with errors.\n";
    }
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
