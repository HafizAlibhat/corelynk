<?php
/**
 * CLI Cheque Insert Smoke Test
 * Usage: php test_cheque_insert.php
 * Ensures a cheque, lines, and journal entry can be created without web UI.
 */
require_once __DIR__.'/vendor/autoload.php';
use Config\Database;

function out($msg){ echo $msg."\n"; }

$db = Database::connect();
if (!$db) { out('❌ DB connect failed'); exit(1); }

// Find bank account
$bank = $db->query("SELECT id,name FROM accounts WHERE is_bank = 1 ORDER BY id ASC LIMIT 1")->getRowArray();
if (!$bank) {
    $bank = $db->query("SELECT id,name FROM accounts WHERE type='Asset' AND name LIKE '%Bank%' ORDER BY id ASC LIMIT 1")->getRowArray();
}
if (!$bank) { out('❌ No bank account found. Mark one as is_bank first.'); exit(1); }

// Find an expense account
$exp = $db->query("SELECT id,code,name FROM accounts WHERE type='Expense' ORDER BY id ASC LIMIT 1")->getRowArray();
if (!$exp) { out('❌ No expense account found.'); exit(1); }

$today = date('Y-m-d');
$db->transBegin();
try {
    // Sequence number (simple)
    $seq = $db->query('SELECT * FROM cheque_sequences WHERE bank_account_id=?', [$bank['id']])->getRowArray();
    if (!$seq) { $db->query('INSERT INTO cheque_sequences (bank_account_id,next_number) VALUES (?,1)', [$bank['id']]); $seq=['next_number'=>1,'prefix'=>null,'suffix'=>null]; }
    $num = (int)($seq['next_number'] ?? 1);
    $chequeNumber = ($seq['prefix'] ?? '').$num.($seq['suffix'] ?? '');
    $db->query('UPDATE cheque_sequences SET next_number = next_number + 1, last_issued_at = NOW() WHERE bank_account_id=?', [$bank['id']]);

    // Insert cheque
    $db->query('INSERT INTO cheques (bank_account_id,cheque_number,cheque_date,payee_type,payee_name,delivery_type,status,amount,created_at) VALUES (?,?,?,?,?,?,?, ?, NOW())', [
        $bank['id'],$chequeNumber,$today,'self','Self','ac_payee','posted',100.00
    ]);
    $chequeId = (int)$db->insertID();

    // Insert line
    $db->query('INSERT INTO cheque_lines (cheque_id,account_id,description,amount) VALUES (?,?,?,?)', [$chequeId,$exp['id'],'Test expense',100.00]);

    // Journal Entry
    $db->query('INSERT INTO journal_entries (entry_date,memo,currency_code,total_debits,total_credits,created_at) VALUES (?,?,?,?,?,NOW())', [
        $today,'Cheque #'.$chequeNumber.' - Self','PKR',100.00,100.00
    ]);
    $jeId = (int)$db->insertID();

    // Journal lines: debit expense, credit bank
    $db->query('INSERT INTO journal_lines (entry_id,account_id,description,debit,credit,currency_code,fx_rate,base_amount) VALUES (?,?,?,?,?,?,?,?)', [
        $jeId,$exp['id'],'Cheque #'.$chequeNumber.'',100.00,0,'PKR',1,100.00
    ]);
    $db->query('INSERT INTO journal_lines (entry_id,account_id,description,debit,credit,currency_code,fx_rate,base_amount) VALUES (?,?,?,?,?,?,?,?)', [
        $jeId,$bank['id'],'Cheque #'.$chequeNumber.'',0,100.00,'PKR',1,100.00
    ]);

    // Link cheque to journal
    $db->query('UPDATE cheques SET posted_entry_id=? WHERE id=?', [$jeId,$chequeId]);

    if ($db->transStatus() === false) { throw new RuntimeException('Transaction status false'); }
    $db->transCommit();
    out('✅ Cheque test inserted: ID '.$chequeId.' #'.$chequeNumber.' (Journal '.$jeId.')');
} catch (Throwable $e) {
    $db->transRollback();
    out('❌ Error inserting cheque: '.$e->getMessage());
    exit(1);
}

// Show last 3 cheques
$rows = $db->query('SELECT id,cheque_number,cheque_date,amount FROM cheques ORDER BY id DESC LIMIT 3')->getResultArray();
out("Last cheques:");
foreach ($rows as $r) { out('  • '.$r['id'].' #'.$r['cheque_number'].' '.$r['cheque_date'].' amount '.$r['amount']); }
