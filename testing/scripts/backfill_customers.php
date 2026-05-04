<?php
/**
 * Backfill script for migrating legacy customer-like data into `customers`.
 * Usage: php scripts/backfill_customers.php [--batch=500] [--dry-run]
 */
require __DIR__ . '/../vendor/autoload.php';

// Minimal bootstrap for CodeIgniter models when run from CLI
define('CI_RUNNING_FROM_CLI', true);

$opts = getopt('', ['batch::', 'dry-run']);
$batch = isset($opts['batch']) ? (int)$opts['batch'] : 500;
$dry = isset($opts['dry-run']);

echo "Backfill customers (batch={$batch}) dry-run=" . ($dry ? 'yes' : 'no') . "\n";

$db = \Config\Database::connect();

// Heuristic: if a `vendors` table exists, offer to import vendors as customers (review data before committing)
if ($db->tableExists('vendors')) {
    $count = $db->table('vendors')->countAllResults(false);
    echo "Found vendors table, records approx: {$count}\n";
    $builder = $db->table('vendors');
    $offset = 0;
    while (true) {
        $rows = $builder->get($batch, $offset)->getResultArray();
        if (empty($rows)) break;
        foreach ($rows as $r) {
            $name = $r['name'] ?? ($r['vendor_name'] ?? null);
            if (empty($name)) continue;
            $candidate = [
                'name' => $name,
                'type' => 'wholesale',
                'metadata' => json_encode(['source' => 'import:vendors', 'legacy_id' => $r['id'] ?? null])
            ];
            if ($dry) {
                echo "DRY: would create customer: " . $name . "\n";
            } else {
                // Insert into customers table
                $db->table('customers')->insert($candidate);
                $newId = $db->insertID();
                // Insert audit
                $db->table('customer_audit')->insert(['customer_id' => $newId, 'action' => 'import', 'note' => 'Imported from vendors']);
                echo "Imported vendor {$name} -> customer_id={$newId}\n";
            }
        }
        $offset += $batch;
    }
} else {
    echo "No vendors table detected. You can adapt this script to import from other legacy sources.\n";
}

echo "Backfill completed.\n";
