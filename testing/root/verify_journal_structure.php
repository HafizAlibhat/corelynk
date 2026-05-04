<?php
$dsn = 'mysql:host=localhost;dbname=production_management_system;charset=utf8mb4';
$username = 'root';
$password = '';

try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== JOURNAL_LINES TABLE STRUCTURE ===\n";
    $stmt = $pdo->query('DESCRIBE journal_lines');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "{$row['Field']} - {$row['Type']}\n";
    }
    
    echo "\n=== SAMPLE JOURNAL_LINES DATA ===\n";
    $stmt = $pdo->query('SELECT * FROM journal_lines LIMIT 2');
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($data)) {
        print_r($data);
    } else {
        echo "No data in table\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
