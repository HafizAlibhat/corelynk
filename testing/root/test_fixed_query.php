<?php
$dsn = 'mysql:host=localhost;dbname=production_management_system;charset=utf8mb4';
$username = 'root';
$password = '';

try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== TESTING FIXED QUERY ===\n\n";
    
    // Test the corrected query from AccountModel
    $sql = "SELECT a.id,a.code,a.name,a.type,COALESCE(SUM(jl.debit),0) debits,COALESCE(SUM(jl.credit),0) credits
            FROM accounts a
            LEFT JOIN journal_lines jl ON jl.account_id = a.id
            LEFT JOIN journal_entries je ON je.id = jl.journal_entry_id
            GROUP BY a.id,a.code,a.name,a.type
            ORDER BY a.type,a.code";
    
    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✅ Query executed successfully!\n";
    echo "Found " . count($results) . " accounts\n\n";
    
    echo "Sample accounts:\n";
    foreach (array_slice($results, 0, 5) as $row) {
        echo sprintf("  %s - %s: Dr %.2f, Cr %.2f\n", 
            $row['code'], 
            $row['name'], 
            $row['debits'], 
            $row['credits']
        );
    }
    
    echo "\n=== CALCULATING TRIAL BALANCE ===\n";
    $totalDebit = 0;
    $totalCredit = 0;
    
    foreach ($results as $row) {
        $d = (float)$row['debits'];
        $c = (float)$row['credits'];
        
        if (in_array($row['type'], ['Asset', 'Expense'])) {
            $balance = $d - $c;
            if ($balance > 0) {
                $totalDebit += $balance;
                echo sprintf("  Dr: %s (%s) = %.2f\n", $row['code'], $row['name'], $balance);
            }
        } elseif (in_array($row['type'], ['Liability', 'Equity', 'Revenue'])) {
            $balance = $c - $d;
            if ($balance > 0) {
                $totalCredit += $balance;
                echo sprintf("  Cr: %s (%s) = %.2f\n", $row['code'], $row['name'], $balance);
            }
        }
    }
    
    echo "\n=== TOTALS ===\n";
    echo sprintf("Total Debit:  ₨ %s\n", number_format($totalDebit, 2));
    echo sprintf("Total Credit: ₨ %s\n", number_format($totalCredit, 2));
    echo sprintf("Difference:   ₨ %s\n", number_format(abs($totalDebit - $totalCredit), 2));
    
    if (abs($totalDebit - $totalCredit) < 0.01) {
        echo "✅ BALANCED!\n";
    } else {
        echo "⚠️  UNBALANCED (Expected for testing - Entry JE-2025-003 has 100 PKR error)\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
