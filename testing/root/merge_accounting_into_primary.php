<?php
// Raw MySQL merge script (no CI bootstrap) to unify accounting tables into primary DB.
// Assumes both databases are accessible with same root credentials.
// Usage: php merge_accounting_into_primary.php

$host = 'localhost';
$user = 'root';
$pass = '';
$primaryDb = 'corelynk_db';
$secondaryDb = 'corelynk_acc_db';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $primary = new mysqli($host, $user, $pass, $primaryDb);
} catch (Throwable $e) {
    echo "Cannot connect primary DB $primaryDb: " . $e->getMessage() . "\n"; exit(1);
}
try {
    $secondary = new mysqli($host, $user, $pass, $secondaryDb);
} catch (Throwable $e) {
    echo "Secondary accounting DB $secondaryDb not found; nothing to merge.\n"; exit(0);
}

$tables = [
    'accounts' => "CREATE TABLE accounts (\n        id INT AUTO_INCREMENT PRIMARY KEY,\n        code VARCHAR(50) NOT NULL,\n        name VARCHAR(190) NOT NULL,\n        type VARCHAR(50) NOT NULL,\n        currency_code VARCHAR(10) DEFAULT 'PKR',\n        is_active TINYINT(1) DEFAULT 1,\n        parent_id INT NULL,\n        created_at DATETIME NULL,\n        updated_at DATETIME NULL,\n        KEY idx_code (code)\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    'currencies' => "CREATE TABLE currencies (\n        code VARCHAR(10) PRIMARY KEY,\n        name VARCHAR(100) NOT NULL,\n        symbol VARCHAR(10) NULL,\n        is_base TINYINT(1) DEFAULT 0,\n        decimals INT DEFAULT 2\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    'tax_codes' => "CREATE TABLE tax_codes (\n        id INT AUTO_INCREMENT PRIMARY KEY,\n        code VARCHAR(50) NOT NULL,\n        name VARCHAR(150) NOT NULL,\n        rate DECIMAL(9,4) NOT NULL DEFAULT 0,\n        is_compound TINYINT(1) DEFAULT 0\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    'journal_entries' => "CREATE TABLE journal_entries (\n        id INT AUTO_INCREMENT PRIMARY KEY,\n        entry_date DATE NOT NULL,\n        memo VARCHAR(255) NULL,\n        currency_code VARCHAR(10) DEFAULT 'PKR',\n        total_debits DECIMAL(18,2) NOT NULL DEFAULT 0,\n        total_credits DECIMAL(18,2) NOT NULL DEFAULT 0\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    'journal_lines' => "CREATE TABLE journal_lines (\n        id INT AUTO_INCREMENT PRIMARY KEY,\n        entry_id INT NOT NULL,\n        account_id INT NOT NULL,\n        description VARCHAR(255) NULL,\n        debit DECIMAL(18,2) NOT NULL DEFAULT 0,\n        credit DECIMAL(18,2) NOT NULL DEFAULT 0,\n        currency_code VARCHAR(10) DEFAULT 'PKR',\n        fx_rate DECIMAL(18,8) NULL,\n        base_amount DECIMAL(18,2) NULL,\n        KEY idx_entry (entry_id),\n        KEY idx_account (account_id)\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    'credit_notes' => "CREATE TABLE credit_notes (\n        id INT AUTO_INCREMENT PRIMARY KEY,\n        party_type VARCHAR(30) NOT NULL,\n        party_id INT NOT NULL,\n        account_id INT NOT NULL,\n        reference VARCHAR(100) NULL,\n        note TEXT NULL,\n        amount DECIMAL(18,2) NOT NULL DEFAULT 0,\n        applied_amount DECIMAL(18,2) NOT NULL DEFAULT 0,\n        status VARCHAR(30) NOT NULL DEFAULT 'open',\n        created_at DATETIME NULL,\n        updated_at DATETIME NULL,\n        KEY idx_party (party_type, party_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    'purchase_orders' => "CREATE TABLE purchase_orders (\n        id INT AUTO_INCREMENT PRIMARY KEY,\n        vendor_id INT NOT NULL,\n        order_date DATE NOT NULL,\n        status VARCHAR(30) NOT NULL DEFAULT 'draft',\n        currency_code VARCHAR(10) DEFAULT 'PKR',\n        subtotal DECIMAL(18,2) NOT NULL DEFAULT 0,\n        tax_total DECIMAL(18,2) NOT NULL DEFAULT 0,\n        total DECIMAL(18,2) NOT NULL DEFAULT 0,\n        created_at DATETIME NULL,\n        updated_at DATETIME NULL,
        KEY idx_vendor (vendor_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    'purchase_order_lines' => "CREATE TABLE purchase_order_lines (\n        id INT AUTO_INCREMENT PRIMARY KEY,\n        po_id INT NOT NULL,\n        product_id INT NULL,\n        description VARCHAR(255) NULL,\n        qty DECIMAL(18,4) NOT NULL DEFAULT 0,\n        unit_price DECIMAL(18,4) NOT NULL DEFAULT 0,\n        tax_code_id INT NULL,\n        line_total DECIMAL(18,2) NOT NULL DEFAULT 0,\n        KEY idx_po (po_id)\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
];

foreach ($tables as $t => $ddl) {
    $res = $primary->query("SHOW TABLES LIKE '$t'");
    if ($res->num_rows === 0) {
        echo "Creating missing table $t in primary DB...\n";
        $primary->query($ddl);
    }
    // Source rows: if table doesn't exist in secondary DB, treat as empty (new feature tables)
    $srcExists = $secondary->query("SHOW TABLES LIKE '$t'");
    if ($srcExists->num_rows === 0) { echo "Source table $t not found in secondary DB; skipping copy.\n"; continue; }
    $src = $secondary->query("SELECT * FROM $t");
    $inserted = 0; $skipped = 0;
    while ($row = $src->fetch_assoc()) {
        if (array_key_exists('id', $row)) { $id = $row['id']; unset($row['id']); }
        $exists = false;
        if ($t === 'accounts') {
            $stmt = $primary->prepare('SELECT id FROM accounts WHERE code = ? LIMIT 1');
            $stmt->bind_param('s', $row['code']);
        } elseif ($t === 'currencies') {
            $stmt = $primary->prepare('SELECT code FROM currencies WHERE code = ? LIMIT 1');
            $stmt->bind_param('s', $row['code']);
        } elseif ($t === 'tax_codes') {
            $stmt = $primary->prepare('SELECT id FROM tax_codes WHERE code = ? LIMIT 1');
            $stmt->bind_param('s', $row['code']);
        } elseif ($t === 'journal_entries') {
            $stmt = $primary->prepare('SELECT id FROM journal_entries WHERE entry_date = ? AND memo <=> ? AND total_debits = ? AND total_credits = ? LIMIT 1');
            $stmt->bind_param('ssdd', $row['entry_date'], $row['memo'], $row['total_debits'], $row['total_credits']);
        } elseif ($t === 'journal_lines') {
            $stmt = $primary->prepare('SELECT id FROM journal_lines WHERE entry_id = ? AND account_id = ? AND debit = ? AND credit = ? LIMIT 1');
            $stmt->bind_param('iidd', $row['entry_id'], $row['account_id'], $row['debit'], $row['credit']);
        } elseif ($t === 'credit_notes') {
            $stmt = $primary->prepare('SELECT id FROM credit_notes WHERE party_type = ? AND party_id = ? AND amount = ? AND reference <=> ? LIMIT 1');
            $stmt->bind_param('sids', $row['party_type'], $row['party_id'], $row['amount'], $row['reference']);
        } elseif ($t === 'purchase_orders') {
            $stmt = $primary->prepare('SELECT id FROM purchase_orders WHERE vendor_id = ? AND order_date = ? AND total = ? LIMIT 1');
            $stmt->bind_param('isd', $row['vendor_id'], $row['order_date'], $row['total']);
        } elseif ($t === 'purchase_order_lines') {
            $stmt = $primary->prepare('SELECT id FROM purchase_order_lines WHERE po_id = ? AND description <=> ? AND qty = ? AND unit_price = ? LIMIT 1');
            $stmt->bind_param('isdd', $row['po_id'], $row['description'], $row['qty'], $row['unit_price']);
        }
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows > 0) { $exists = true; }
        $stmt->close();
        if ($exists) { $skipped++; continue; }
        // Build insert dynamically
        $cols = array_keys($row);
        $placeholders = rtrim(str_repeat('?,', count($cols)), ',');
        $insertSql = 'INSERT INTO ' . $t . ' (' . implode(',', $cols) . ') VALUES (' . $placeholders . ')';
        $stmt2 = $primary->prepare($insertSql);
        // Bind params dynamically
        $types = '';
        $vals = [];
        foreach ($row as $v) {
            if (is_int($v)) { $types .= 'i'; }
            elseif (is_float($v) || preg_match('/^\d+\.\d+$/', (string)$v)) { $types .= 'd'; $v = (float)$v; }
            else { $types .= 's'; }
            $vals[] = $v;
        }
        $stmt2->bind_param($types, ...$vals);
        $stmt2->execute();
        $stmt2->close();
        $inserted++;
    }
    $totalRes = $primary->query("SELECT COUNT(*) c FROM $t");
    $total = $totalRes->fetch_assoc()['c'];
    echo "Table $t merged: inserted=$inserted skipped=$skipped total=$total\n";
}

echo "Merge complete. You can drop database $secondaryDb after verification and remove accounting group from Config/Database.php.\n";