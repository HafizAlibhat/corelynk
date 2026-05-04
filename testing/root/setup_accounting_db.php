<?php
/**
 * Setup Accounting Database (corelynk_acc_db) and base tables.
 * Usage (PowerShell):
 *   php setup_accounting_db.php --db=corelynk_acc_db --mysql-bin=C:\xampp\mysql\bin
 * Defaults: db=corelynk_acc_db, user=root, no password
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

function arg($name, $default = null) {
    foreach ($GLOBALS['argv'] as $a) {
        if (strpos($a, "--$name=") === 0) return substr($a, strlen($name) + 3);
        if ($a === "--$name") return true;
    }
    return $default;
}
function out($m){ echo "[+] $m\n"; }
function err($m){ fwrite(STDERR, "ERROR: $m\n"); }

$mysqlBin = rtrim((string) arg('mysql-bin', ''), "\\/");
$user = arg('user', 'root');
$db = arg('db', 'corelynk_acc_db');
$mysqlExe = $mysqlBin ? $mysqlBin . DIRECTORY_SEPARATOR . 'mysql.exe' : 'C:\\xampp\\mysql\\bin\\mysql.exe';

$schema = <<<SQL
CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `$db`;

CREATE TABLE IF NOT EXISTS currencies (
  code VARCHAR(3) PRIMARY KEY,
  name VARCHAR(64) NOT NULL,
  symbol VARCHAR(8) NULL,
  is_base TINYINT(1) NOT NULL DEFAULT 0,
  decimals TINYINT NOT NULL DEFAULT 2
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exchange_rates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  base_code VARCHAR(3) NOT NULL,
  quote_code VARCHAR(3) NOT NULL,
  rate DECIMAL(18,6) NOT NULL,
  as_of DATE NOT NULL,
  UNIQUE KEY uq_rate (base_code, quote_code, as_of),
  CONSTRAINT fk_rates_base FOREIGN KEY (base_code) REFERENCES currencies(code),
  CONSTRAINT fk_rates_quote FOREIGN KEY (quote_code) REFERENCES currencies(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tax_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL UNIQUE,
  name VARCHAR(128) NOT NULL,
  rate DECIMAL(9,6) NOT NULL DEFAULT 0.0,
  is_compound TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL UNIQUE,
  name VARCHAR(128) NOT NULL,
  type VARCHAR(32) NOT NULL,
  currency_code VARCHAR(3) NULL,
  parent_id INT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_acc_currency FOREIGN KEY (currency_code) REFERENCES currencies(code),
  CONSTRAINT fk_acc_parent FOREIGN KEY (parent_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS journal_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entry_date DATE NOT NULL,
  memo VARCHAR(255) NULL,
  currency_code VARCHAR(3) NULL,
  total_debits DECIMAL(18,2) NOT NULL DEFAULT 0,
  total_credits DECIMAL(18,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_je_currency FOREIGN KEY (currency_code) REFERENCES currencies(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS journal_lines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entry_id INT NOT NULL,
  account_id INT NOT NULL,
  description VARCHAR(255) NULL,
  debit DECIMAL(18,2) NOT NULL DEFAULT 0,
  credit DECIMAL(18,2) NOT NULL DEFAULT 0,
  currency_code VARCHAR(3) NULL,
  fx_rate DECIMAL(18,6) NULL,
  base_amount DECIMAL(18,2) NULL,
  CONSTRAINT fk_jl_entry FOREIGN KEY (entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
  CONSTRAINT fk_jl_account FOREIGN KEY (account_id) REFERENCES accounts(id),
  CONSTRAINT fk_jl_currency FOREIGN KEY (currency_code) REFERENCES currencies(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed base currency (PKR) and common ones
INSERT IGNORE INTO currencies(code, name, symbol, is_base, decimals) VALUES
 ('PKR','Pakistani Rupee','₨',1,2),
 ('USD','US Dollar','$',0,2),
 ('EUR','Euro','€',0,2);
SQL;

// Write schema to temp and feed to mysql
$tmp = tempnam(sys_get_temp_dir(), 'acc_schema_');
file_put_contents($tmp, $schema);

$cmd = 'cmd /c ' . '"' . $mysqlExe . '" -u' . $user . ' < ' . '"' . $tmp . '"';
out('Applying accounting schema...');
exec($cmd, $o, $code);
@unlink($tmp);
if ($code !== 0) {
    err('Failed applying schema. Please check MySQL path and permissions.');
    exit(1);
}

out('Accounting DB setup complete for ' . $db);
