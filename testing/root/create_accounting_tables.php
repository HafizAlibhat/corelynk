<?php
$dsn = 'mysql:host=localhost;dbname=production_management_system;charset=utf8mb4';
$username = 'root';
$password = '';

try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Creating accounting tables...\n\n";
    
    // Accounts table
    $pdo->exec("DROP TABLE IF EXISTS journal_lines");
    $pdo->exec("DROP TABLE IF EXISTS journal_entries");
    $pdo->exec("DROP TABLE IF EXISTS accounts");
    
    $pdo->exec("
        CREATE TABLE accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(20) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            type ENUM('Asset', 'Liability', 'Equity', 'Revenue', 'Expense') NOT NULL,
            parent_id INT NULL,
            currency_code VARCHAR(3) DEFAULT 'PKR',
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (parent_id) REFERENCES accounts(id) ON DELETE SET NULL,
            INDEX idx_type (type),
            INDEX idx_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ Created accounts table\n";
    
    // Journal Entries table
    $pdo->exec("
        CREATE TABLE journal_entries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            entry_number VARCHAR(50) NOT NULL UNIQUE,
            entry_date DATE NOT NULL,
            description TEXT,
            reference VARCHAR(100),
            status ENUM('draft', 'posted', 'void') DEFAULT 'posted',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_entry_date (entry_date),
            INDEX idx_entry_number (entry_number),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ Created journal_entries table\n";
    
    // Journal Lines table
    $pdo->exec("
        CREATE TABLE journal_lines (
            id INT AUTO_INCREMENT PRIMARY KEY,
            journal_entry_id INT NOT NULL,
            account_id INT NOT NULL,
            debit DECIMAL(15,2) DEFAULT 0.00,
            credit DECIMAL(15,2) DEFAULT 0.00,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
            FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE RESTRICT,
            INDEX idx_journal_entry (journal_entry_id),
            INDEX idx_account (account_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ Created journal_lines table\n";
    
    // Insert sample Chart of Accounts
    echo "\nInserting sample accounts...\n";
    
    $accounts = [
        // Assets
        ['1000', 'Cash', 'Asset'],
        ['1100', 'Accounts Receivable', 'Asset'],
        ['1200', 'Inventory', 'Asset'],
        ['1500', 'Fixed Assets', 'Asset'],
        
        // Liabilities
        ['2000', 'Accounts Payable', 'Liability'],
        ['2100', 'Salaries Payable', 'Liability'],
        ['2500', 'Bank Loan', 'Liability'],
        
        // Equity
        ['3000', 'Owner\'s Capital', 'Equity'],
        ['3100', 'Retained Earnings', 'Equity'],
        
        // Revenue
        ['4000', 'Sales Revenue', 'Revenue'],
        ['4100', 'Service Revenue', 'Revenue'],
        
        // Expenses
        ['5400', 'Office Supplies', 'Expense'],
        ['5700', 'Petty Cash - RI-SKT', 'Expense'],
        ['5800', 'Refreshment (Breakfast / Brunch / Lunch )', 'Expense'],
    ];
    
    $stmt = $pdo->prepare("INSERT INTO accounts (code, name, type) VALUES (?, ?, ?)");
    foreach ($accounts as $account) {
        $stmt->execute($account);
        echo "  + {$account[0]} - {$account[1]}\n";
    }
    
    // Insert sample journal entries
    echo "\nCreating sample journal entries...\n";
    
    $pdo->exec("
        INSERT INTO journal_entries (entry_number, entry_date, description, status) VALUES
        ('JE-2025-001', '2025-11-01', 'Opening balance', 'posted'),
        ('JE-2025-002', '2025-11-05', 'Office supplies purchase', 'posted'),
        ('JE-2025-003', '2025-11-10', 'Customer payment received', 'posted')
    ");
    
    // Get account IDs
    $accountIds = [];
    $stmt = $pdo->query("SELECT id, code FROM accounts");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $accountIds[$row['code']] = $row['id'];
    }
    
    // Entry 1: Opening balance
    $pdo->exec("
        INSERT INTO journal_lines (journal_entry_id, account_id, debit, credit) VALUES
        (1, {$accountIds['1000']}, 10000.00, 0.00),
        (1, {$accountIds['3000']}, 0.00, 10000.00)
    ");
    echo "  ✓ JE-2025-001: Opening balance\n";
    
    // Entry 2: Office supplies
    $pdo->exec("
        INSERT INTO journal_lines (journal_entry_id, account_id, debit, credit) VALUES
        (2, {$accountIds['5400']}, 1000.00, 0.00),
        (2, {$accountIds['1000']}, 0.00, 1000.00)
    ");
    echo "  ✓ JE-2025-002: Office supplies\n";
    
    // Entry 3: Revenue - UNBALANCED (causing 100 PKR difference)
    $pdo->exec("
        INSERT INTO journal_lines (journal_entry_id, account_id, debit, credit) VALUES
        (3, {$accountIds['1000']}, 10100.00, 0.00),
        (3, {$accountIds['4000']}, 0.00, 10000.00)
    ");
    echo "  ✓ JE-2025-003: Customer payment (UNBALANCED - will show 100 PKR error)\n";
    
    // Add refreshment expense
    $pdo->exec("
        INSERT INTO journal_entries (entry_number, entry_date, description, status) VALUES
        ('JE-2025-004', '2025-11-12', 'Refreshment expense', 'posted')
    ");
    
    $pdo->exec("
        INSERT INTO journal_lines (journal_entry_id, account_id, debit, credit) VALUES
        (4, {$accountIds['5800']}, 100.00, 0.00),
        (4, {$accountIds['5700']}, 0.00, 100.00)
    ");
    echo "  ✓ JE-2025-004: Refreshment expense\n";
    
    echo "\n✅ Accounting system setup complete!\n";
    echo "\nSummary:\n";
    echo "- Accounts: " . count($accounts) . "\n";
    echo "- Journal Entries: 4\n";
    echo "- Note: Entry JE-2025-003 is deliberately unbalanced by 100 PKR to test audit system\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
