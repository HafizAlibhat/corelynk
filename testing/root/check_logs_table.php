<?php
// Check process_batch_logs table structure
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'production_management_system';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== PROCESS_BATCH_LOGS TABLE STRUCTURE ===\n";
    $stmt = $pdo->query('DESCRIBE process_batch_logs');
    while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "{$col['Field']} - {$col['Type']} - {$col['Null']} - {$col['Key']}\n";
    }
    
    echo "\n=== SAMPLE DATA ===\n";
    $stmt = $pdo->query('SELECT * FROM process_batch_logs LIMIT 3');
    while ($log = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($log);
    }
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>