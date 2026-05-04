<?php
// CLI script to inspect journal_entries schema and test dashboard queries
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');
if ($mysqli->connect_error) {
    die('DB connection failed: ' . $mysqli->connect_error . "\n");
}

// 1. Print journal_entries schema
$res = $mysqli->query("SHOW COLUMNS FROM journal_entries");
echo "--- journal_entries columns ---\n";
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}

// 2. Test dashboard queries
$currency = 'PKR';

// Revenue
$q = "SELECT SUM(CASE WHEN jl.currency_code = ? THEN jl.credit ELSE jl.credit * jl.fx_rate END) as revenue FROM journal_lines jl JOIN accounts a ON jl.account_id = a.id WHERE a.type = 'Revenue'";
$stmt = $mysqli->prepare($q);
$stmt->bind_param('s', $currency);
$stmt->execute();
$stmt->bind_result($revenue);
$stmt->fetch();
$stmt->close();
echo "Revenue: $revenue\n";

// Expenses
$q = "SELECT SUM(CASE WHEN jl.currency_code = ? THEN jl.debit ELSE jl.debit * jl.fx_rate END) as expenses FROM journal_lines jl JOIN accounts a ON jl.account_id = a.id WHERE a.type = 'Expense'";
$stmt = $mysqli->prepare($q);
$stmt->bind_param('s', $currency);
$stmt->execute();
$stmt->bind_result($expenses);
$stmt->fetch();
$stmt->close();
echo "Expenses: $expenses\n";

// Cash Position
$q = "SELECT SUM(CASE WHEN jl.currency_code = ? THEN jl.debit - jl.credit ELSE (jl.debit - jl.credit) * jl.fx_rate END) as balance FROM journal_lines jl JOIN accounts a ON jl.account_id = a.id WHERE (a.name LIKE '%cash%' OR a.name LIKE '%bank%' OR a.code LIKE '1%')";
$stmt = $mysqli->prepare($q);
$stmt->bind_param('s', $currency);
$stmt->execute();
$stmt->bind_result($cash);
$stmt->fetch();
$stmt->close();
echo "Cash Position: $cash\n";

// Recent Activity (only select columns that exist)

$q = "SELECT je.id, je.entry_date, SUM(CASE WHEN jl.currency_code = ? THEN jl.debit - jl.credit ELSE (jl.debit - jl.credit) * jl.fx_rate END) as total_amount, COUNT(jl.id) as line_count FROM journal_entries je LEFT JOIN journal_lines jl ON je.id = jl.entry_id GROUP BY je.id ORDER BY je.entry_date DESC, je.id DESC LIMIT 5";
$stmt = $mysqli->prepare($q);
$stmt->bind_param('s', $currency);
$stmt->execute();
$res = $stmt->get_result();
echo "--- Recent Activity ---\n";
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
$stmt->close();

$mysqli->close();
