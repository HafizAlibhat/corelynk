<?php
// Quick setup script to add cheques module tables and fields.
$mysqli = new mysqli('127.0.0.1', 'root', '', 'production_management_system', 3306);
if ($mysqli->connect_errno) {
    echo 'CONNECT_ERR: ' . $mysqli->connect_error . PHP_EOL;
    exit(1);
}

function execSQL(mysqli $db, string $sql, string $label) {
    echo $label . '...' . PHP_EOL;
    if ($db->query($sql)) { echo "✅ $label OK" . PHP_EOL; }
    else { echo "❌ $label: " . $db->error . PHP_EOL; }
}

// Add is_bank to accounts if missing
$res = $mysqli->query("SHOW COLUMNS FROM accounts LIKE 'is_bank'");
if (!$res || $res->num_rows === 0) {
    execSQL($mysqli, "ALTER TABLE accounts ADD COLUMN is_bank TINYINT(1) DEFAULT 0 AFTER currency_code", 'Add is_bank to accounts');
} else {
    echo "ℹ️ is_bank already exists on accounts" . PHP_EOL;
}

// Add account_number to accounts if missing
$res = $mysqli->query("SHOW COLUMNS FROM accounts LIKE 'account_number'");
if (!$res || $res->num_rows === 0) {
    execSQL($mysqli, "ALTER TABLE accounts ADD COLUMN account_number VARCHAR(50) NULL AFTER name", 'Add account_number to accounts');
} else {
    echo "ℹ️ account_number already exists on accounts" . PHP_EOL;
}

// Create cheques table
execSQL($mysqli, "CREATE TABLE IF NOT EXISTS cheques (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bank_account_id INT NOT NULL,
    cheque_number VARCHAR(50) NOT NULL,
    cheque_date DATE NOT NULL,
    payee_type VARCHAR(20) NOT NULL,
    vendor_id INT NULL,
    contact_id INT NULL,
    payee_name VARCHAR(190) NULL,
    delivery_type VARCHAR(20) DEFAULT 'ac_payee',
    status VARCHAR(20) DEFAULT 'draft',
    amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    posted_entry_id INT NULL,
    notes TEXT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    KEY idx_bank (bank_account_id),
    KEY idx_vendor (vendor_id),
    KEY idx_date (cheque_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'Create cheques table');

// Create cheque_lines
execSQL($mysqli, "CREATE TABLE IF NOT EXISTS cheque_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cheque_id INT NOT NULL,
    account_id INT NOT NULL,
    description VARCHAR(255) NULL,
    amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    KEY idx_cheque (cheque_id),
    KEY idx_account (account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'Create cheque_lines table');

// Create cheque_sequences
execSQL($mysqli, "CREATE TABLE IF NOT EXISTS cheque_sequences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bank_account_id INT NOT NULL UNIQUE,
    prefix VARCHAR(10) NULL,
    next_number INT NOT NULL DEFAULT 1,
    suffix VARCHAR(10) NULL,
    last_issued_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'Create cheque_sequences table');

// Create vendor_contacts
execSQL($mysqli, "CREATE TABLE IF NOT EXISTS vendor_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    name VARCHAR(190) NOT NULL,
    phone VARCHAR(50) NULL,
    cnic VARCHAR(25) NULL,
    email VARCHAR(190) NULL,
    designation VARCHAR(100) NULL,
    is_primary TINYINT(1) DEFAULT 0,
    notes TEXT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    KEY idx_vendor (vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'Create vendor_contacts table');

// Create vendor_payments header table
execSQL($mysqli, "CREATE TABLE IF NOT EXISTS vendor_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    cheque_id INT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    notes VARCHAR(255) NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    KEY idx_vendor (vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'Create vendor_payments table');

// Create vendor_payment_allocations table
execSQL($mysqli, "CREATE TABLE IF NOT EXISTS vendor_payment_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    purchase_order_id INT NOT NULL,
    amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    KEY idx_payment (payment_id),
    KEY idx_po (purchase_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'Create vendor_payment_allocations table');

// Final check
$res = $mysqli->query('SHOW TABLES LIKE "cheque%"');
while ($row = $res->fetch_array()) { echo '- ' . $row[0] . PHP_EOL; }
