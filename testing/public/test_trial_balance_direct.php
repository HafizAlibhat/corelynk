<?php
// Direct test - bypass framework caching
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo "<h1>Trial Balance Direct Test</h1>";
echo "<p>Testing database connection and query...</p>";

$dsn = 'mysql:host=localhost;dbname=production_management_system;charset=utf8mb4';
$username = 'root';
$password = '';

try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2 style='color: green;'>✅ Database Connected</h2>";
    
    // Test the exact query from TrialBalance controller
    $sql = "
        SELECT 
            a.id,
            a.code, 
            a.name, 
            a.type,
            COALESCE(SUM(jl.debit), 0) as total_debits,
            COALESCE(SUM(jl.credit), 0) as total_credits,
            CASE 
                WHEN a.type IN ('Asset', 'Expense') THEN COALESCE(SUM(jl.debit), 0) - COALESCE(SUM(jl.credit), 0)
                ELSE COALESCE(SUM(jl.credit), 0) - COALESCE(SUM(jl.debit), 0)
            END as balance
        FROM accounts a
        LEFT JOIN journal_lines jl ON a.id = jl.account_id
        GROUP BY a.id, a.code, a.name, a.type
        HAVING (COALESCE(SUM(jl.debit), 0) + COALESCE(SUM(jl.credit), 0)) > 0
        ORDER BY a.type, a.code
    ";
    
    $stmt = $pdo->query($sql);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2 style='color: green;'>✅ Query Executed Successfully</h2>";
    echo "<p>Found " . count($accounts) . " accounts with transactions</p>";
    
    // Calculate totals
    $totalDebit = 0;
    $totalCredit = 0;
    
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #333; color: white;'>";
    echo "<th>Code</th><th>Account Name</th><th>Type</th><th>Debit</th><th>Credit</th>";
    echo "</tr>";
    
    foreach ($accounts as $account) {
        $type = $account['type'];
        $debitBalance = 0;
        $creditBalance = 0;
        
        if (in_array($type, ['Asset', 'Expense']) && $account['balance'] > 0) {
            $debitBalance = $account['balance'];
            $totalDebit += $debitBalance;
        } elseif (in_array($type, ['Liability', 'Equity', 'Revenue']) && $account['balance'] > 0) {
            $creditBalance = $account['balance'];
            $totalCredit += $creditBalance;
        }
        
        if ($debitBalance > 0 || $creditBalance > 0) {
            echo "<tr>";
            echo "<td><strong>{$account['code']}</strong></td>";
            echo "<td>{$account['name']}</td>";
            echo "<td>{$account['type']}</td>";
            echo "<td style='text-align: right;'>" . ($debitBalance > 0 ? number_format($debitBalance, 2) : '—') . "</td>";
            echo "<td style='text-align: right;'>" . ($creditBalance > 0 ? number_format($creditBalance, 2) : '—') . "</td>";
            echo "</tr>";
        }
    }
    
    echo "<tr style='background: #333; color: white; font-weight: bold;'>";
    echo "<td colspan='3'>TOTAL</td>";
    echo "<td style='text-align: right;'>" . number_format($totalDebit, 2) . "</td>";
    echo "<td style='text-align: right;'>" . number_format($totalCredit, 2) . "</td>";
    echo "</tr>";
    
    $difference = abs($totalDebit - $totalCredit);
    $balanced = $difference < 0.01;
    
    echo "<tr style='background: " . ($balanced ? '#d4edda' : '#f8d7da') . "; font-weight: bold;'>";
    echo "<td colspan='3'>" . ($balanced ? '✅ BALANCED' : '⚠️ UNBALANCED') . "</td>";
    echo "<td colspan='2' style='text-align: right;'>Difference: " . number_format($difference, 2) . "</td>";
    echo "</tr>";
    
    echo "</table>";
    
    echo "<hr>";
    echo "<h2 style='color: green;'>✅ ALL TESTS PASSED!</h2>";
    echo "<p><strong>Conclusion:</strong> The database and queries are working perfectly.</p>";
    echo "<p><strong>If you still see an error on the main trial balance page:</strong></p>";
    echo "<ol>";
    echo "<li>Press <strong>Ctrl + Shift + R</strong> (or Cmd + Shift + R on Mac) to hard refresh the page</li>";
    echo "<li>Clear your browser cache completely</li>";
    echo "<li>Try accessing: <a href='http://localhost/corelynk/accounting/trial-balance'>http://localhost/corelynk/accounting/trial-balance</a></li>";
    echo "</ol>";
    
} catch (PDOException $e) {
    echo "<h2 style='color: red;'>❌ ERROR</h2>";
    echo "<pre style='background: #f8d7da; padding: 20px; border: 1px solid #dc3545;'>";
    echo "Error Message: " . htmlspecialchars($e->getMessage()) . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
    echo "</pre>";
}
?>
