
<?php
// Insert sample revenue and expense journal lines for dashboard testing
$db = new mysqli('localhost', 'root', '', 'corelynk_db');
if ($db->connect_error) {
	die('Connection failed: ' . $db->connect_error);
}

// Insert a revenue entry (Sales Revenue)
$db->query("INSERT INTO journal_entries (entry_date, currency_code, total_debits, total_credits) VALUES (NOW(), 'PKR', 0, 15000)");
$entryId = $db->insert_id;
$db->query("INSERT INTO journal_lines (entry_id, account_id, debit, credit, currency_code, fx_rate) VALUES ($entryId, 1, 15000, 0, 'PKR', 1.0)"); // Cash (Asset)
$db->query("INSERT INTO journal_lines (entry_id, account_id, debit, credit, currency_code, fx_rate) VALUES ($entryId, 12, 0, 15000, 'PKR', 1.0)"); // Sales Revenue (Revenue)

// Insert an expense entry (Rent Expense)
$db->query("INSERT INTO journal_entries (entry_date, currency_code, total_debits, total_credits) VALUES (NOW(), 'PKR', 2000, 0)");
$entryId = $db->insert_id;
$db->query("INSERT INTO journal_lines (entry_id, account_id, debit, credit, currency_code, fx_rate) VALUES ($entryId, 15, 2000, 0, 'PKR', 1.0)"); // Rent Expense (Expense)
$db->query("INSERT INTO journal_lines (entry_id, account_id, debit, credit, currency_code, fx_rate) VALUES ($entryId, 1, 0, 2000, 'PKR', 1.0)"); // Cash (Asset)

echo "Sample revenue and expense journal lines inserted.\n";
