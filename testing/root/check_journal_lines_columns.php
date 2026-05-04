<?php
$dsn = 'mysql:host=localhost;dbname=production_management_system;charset=utf8mb4';
$username = 'root';
$password = '';

try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== ALL TABLES ===\n";
    $stmt = $pdo->query('SHOW TABLES');
    $tables = [];
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
        if (stripos($row[0], 'journal') !== false || stripos($row[0], 'account') !== false) {
            echo "✓ {$row[0]}\n";
        }
    }
    
    echo "\n=== CHECKING FOR JOURNAL/ACCOUNTING TABLES ===\n";
    $accountingTables = array_filter($tables, function($t) {
        return stripos($t, 'journal') !== false || stripos($t, 'account') !== false || stripos($t, 'ledger') !== false;
    });
    
    if (empty($accountingTables)) {
        echo "No accounting tables found!\n";
    }
    
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage();
}
