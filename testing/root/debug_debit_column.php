<?php
$dsn = 'mysql:host=localhost;dbname=production_management_system;charset=utf8mb4';
$username = 'root';
$password = '';

try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CHECKING ALL TABLES FOR 'debit' COLUMN ===\n\n";
    
    // Get all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("DESCRIBE `$table`");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $hasDebit = false;
        $hasCredit = false;
        
        foreach ($columns as $col) {
            if ($col['Field'] === 'debit') $hasDebit = true;
            if ($col['Field'] === 'credit') $hasCredit = true;
        }
        
        if ($hasDebit || $hasCredit) {
            echo "Table: $table\n";
            echo "  Has 'debit': " . ($hasDebit ? 'YES' : 'NO') . "\n";
            echo "  Has 'credit': " . ($hasCredit ? 'YES' : 'NO') . "\n";
            echo "  All columns: " . implode(', ', array_column($columns, 'Field')) . "\n\n";
        }
    }
    
    echo "\n=== TESTING THE EXACT QUERY FROM TrialBalance CONTROLLER ===\n\n";
    
    $sql = "
        SELECT 
            a.id,
            a.code, 
            a.name, 
            a.type,
            COALESCE(SUM(jl.debit), 0) as total_debits,
            COALESCE(SUM(jl.credit), 0) as total_credits
        FROM accounts a
        LEFT JOIN journal_lines jl ON a.id = jl.account_id
        GROUP BY a.id, a.code, a.name, a.type
        LIMIT 3
    ";
    
    echo "Running query...\n";
    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✅ SUCCESS! Query returned " . count($results) . " rows\n\n";
    
    foreach ($results as $row) {
        echo "  {$row['code']} - {$row['name']}: Dr {$row['total_debits']}, Cr {$row['total_credits']}\n";
    }
    
} catch (PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "SQL State: " . $e->getCode() . "\n";
}
