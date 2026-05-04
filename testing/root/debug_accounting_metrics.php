<?php
// CLI script: php debug_accounting_metrics.php
// Outputs key accounting metrics in PKR and USD views for reconciliation.
use Config\Database;

require __DIR__ . '/vendor/autoload.php';

$db = Database::connect();
if (!$db) { echo "DB connect failed\n"; exit(1); }

function q($db,$sql,$params=[]){ try { return $db->query($sql,$params)->getRowArray(); } catch (\Throwable $e){ return ['error'=>$e->getMessage()]; } }

$journalTotals = q($db,'SELECT SUM(total_debits) td, SUM(total_credits) tc, COUNT(*) cnt FROM journal_entries');
$lineCounts = q($db,'SELECT COUNT(*) lines FROM journal_lines');

// PKR view totals (stored base amounts)
$revenuePKR = q($db,"SELECT SUM(jl.credit) rev FROM journal_lines jl JOIN accounts a ON jl.account_id=a.id WHERE a.type='Revenue'");
$expensesPKR = q($db,"SELECT SUM(jl.debit) exp FROM journal_lines jl JOIN accounts a ON jl.account_id=a.id WHERE a.type='Expense'");
$cashPKR = q($db,"SELECT SUM(jl.debit - jl.credit) cash FROM journal_lines jl JOIN accounts a ON jl.account_id=a.id WHERE (a.name LIKE '%cash%' OR a.name LIKE '%bank%' OR a.code LIKE '1%')");

// USD view translation (use base_amount for USD rows, divide PKR by fx_rate)
$revenueUSD = q($db,"SELECT SUM(CASE WHEN jl.currency_code='USD' THEN jl.base_amount WHEN jl.currency_code='PKR' AND jl.fx_rate>0 THEN jl.credit / jl.fx_rate ELSE 0 END) rev FROM journal_lines jl JOIN accounts a ON jl.account_id=a.id WHERE a.type='Revenue'");
$expensesUSD = q($db,"SELECT SUM(CASE WHEN jl.currency_code='USD' THEN jl.base_amount WHEN jl.currency_code='PKR' AND jl.fx_rate>0 THEN jl.debit / jl.fx_rate ELSE 0 END) exp FROM journal_lines jl JOIN accounts a ON jl.account_id=a.id WHERE a.type='Expense'");
$cashUSD = q($db,"SELECT SUM(CASE WHEN jl.currency_code='USD' THEN jl.base_amount WHEN jl.currency_code='PKR' AND jl.fx_rate>0 THEN (jl.debit - jl.credit)/ jl.fx_rate ELSE 0 END) cash FROM journal_lines jl JOIN accounts a ON jl.account_id=a.id WHERE (a.name LIKE '%cash%' OR a.name LIKE '%bank%' OR a.code LIKE '1%')");

$rows = [
  ['Metric','PKR','USD','Notes'],
  ['Journal Entries Debits',$journalTotals['td'] ?? 0,'-','Sum of total_debits'],
  ['Journal Entries Credits',$journalTotals['tc'] ?? 0,'-','Sum of total_credits'],
  ['Entries Count',$journalTotals['cnt'] ?? 0,'-','Rows in journal_entries'],
  ['Lines Count',$lineCounts['lines'] ?? 0,'-','Rows in journal_lines'],
  ['Revenue', $revenuePKR['rev'] ?? 0, $revenueUSD['rev'] ?? 0,'PKR stored vs translated'],
  ['Expenses', $expensesPKR['exp'] ?? 0, $expensesUSD['exp'] ?? 0,'PKR stored vs translated'],
  ['Cash Position', $cashPKR['cash'] ?? 0, $cashUSD['cash'] ?? 0,'Aggregated asset cash/bank'],
];

$colWidths = [20,18,18,30];
function pad($v,$w){ $s=(string)$v; if(strlen($s)>$w) return substr($s,0,$w); return $s . str_repeat(' ', $w-strlen($s)); }

foreach ($rows as $i=>$r){
  $line = pad($r[0],$colWidths[0]).pad(number_format($r[1],2),$colWidths[1]).pad(number_format($r[2],2),$colWidths[2]).pad($r[3],$colWidths[3]);
  echo $line, "\n";
}

// Simple reconciliation check
$delta = ($revenuePKR['rev'] ?? 0) - ($journalTotals['tc'] ?? 0);
if (abs($delta) > 0.01) {
  echo "WARNING: Revenue PKR (".number_format($revenuePKR['rev']??0,2).") differs from sum of entry credits (".number_format($journalTotals['tc']??0,2).") by ".number_format($delta,2)."\n";
}

echo "Done.";