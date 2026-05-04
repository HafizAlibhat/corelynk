<?php
// Direct insertion test bypassing CI models to validate unified tables
// Usage: php quick_journal_direct.php
$host='localhost'; $user='root'; $pass=''; $db='corelynk_db';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$cn = new mysqli($host,$user,$pass,$db);
$date = date('Y-m-d');
$memo = 'Direct Test Entry';
$amount = 123.45;
// Ensure two accounts exist (Cash & Sales Revenue)
$accCash = $cn->query("SELECT id FROM accounts WHERE code='1000'")->fetch_assoc()['id'] ?? null;
$accSales = $cn->query("SELECT id FROM accounts WHERE code='4000'")->fetch_assoc()['id'] ?? null;
if(!$accCash || !$accSales){ echo "Required accounts missing (1000/4000). Aborting.\n"; exit(1); }
$cn->query("INSERT INTO journal_entries(entry_date,memo,total_debits,total_credits) VALUES('$date','$memo',$amount,$amount)");
$entryId = $cn->insert_id;
$cn->query("INSERT INTO journal_lines(entry_id,account_id,description,debit,credit) VALUES($entryId,$accCash,'$memo',$amount,0)");
$cn->query("INSERT INTO journal_lines(entry_id,account_id,description,debit,credit) VALUES($entryId,$accSales,'$memo',0,$amount)");
$counts = [
  'entries' => $cn->query('SELECT COUNT(*) c FROM journal_entries')->fetch_assoc()['c'],
  'lines' => $cn->query('SELECT COUNT(*) c FROM journal_lines')->fetch_assoc()['c'],
  'lines_for_entry' => $cn->query('SELECT COUNT(*) c FROM journal_lines WHERE entry_id=' . (int)$entryId)->fetch_assoc()['c'],
];
echo "Inserted entry ID=$entryId counts=" . json_encode($counts) . "\n";