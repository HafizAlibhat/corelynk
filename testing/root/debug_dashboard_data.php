<?php
// Debug script for corelynk_db dashboard data issues
// Run: php debug_dashboard_data.php

$db = new mysqli('localhost', 'root', '', 'corelynk_db');
if ($db->connect_error) {
    die('DB connection failed: ' . $db->connect_error . "\n");
}
echo "\n--- ACCOUNTS (id, code, name, type) ---\n";
$res = $db->query("SELECT id, code, name, type FROM accounts ORDER BY id");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
echo "\n--- JOURNAL LINES (id, entry_id, account_id, debit, credit, currency_code, fx_rate) ---\n";
$res = $db->query("SELECT id, entry_id, account_id, debit, credit, currency_code, fx_rate FROM journal_lines ORDER BY id");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
echo "\n--- JOURNAL ENTRIES (id, entry_date, currency_code, total_debits, total_credits) ---\n";
$res = $db->query("SELECT id, entry_date, currency_code, total_debits, total_credits FROM journal_entries ORDER BY id");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
$db->close();
