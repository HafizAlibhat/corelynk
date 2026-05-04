<?php
/**
 * GRN Partial Receiving Migration Runner
 * Run this through your web browser to execute the pending migrations
 * URL: http://yoursite/migrate_grn.php
 */

header('Content-Type: text/plain; charset=utf-8');

// Get environment
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);
$_SERVER['CI_ENVIRONMENT'] = $_GET['env'] ?? 'development';

try {
    // Load CodeIgniter
    require FCPATH . 'vendor/autoload.php';
    
    // Get database connection
    $db = Config\Database::connect();
    
    // Test connection
    echo "=== GRN Partial Receiving Migration Runner ===\n\n";
    echo "1. Testing database connection...\n";
    
    try {
        $test = $db->query("SELECT VERSION() as version")->getRow();
        echo "   ✓ Connected to MySQL " . $test->version . "\n\n";
    } catch (\Throwable $e) {
        throw new \Exception("Database connection failed: " . $e->getMessage());
    }
    
    // Check what migrations need to run
    echo "2. Checking pending migrations...\n";
    
    $migrator = new \CodeIgniter\Database\MigrationRunner(
        new App\Config\Migrations(),
        $db
    );
    $migrator->setNamespace('App');
    
    // Get all migration files
    $allMigrations = $migrator->discoverMigrations();
    
    // Get completed migrations
    $completedSql = "SELECT file FROM migrations WHERE migration_time IS NOT NULL ORDER BY migration_time DESC LIMIT 100";
    $completed = [];
    try {
        $results = $db->query($completedSql)->getResultArray();
        foreach ($results as $row) {
            $completed[] = $row['file'];
        }
    } catch (\Throwable $e) {
        echo "   ⚠ Note: Migrations table doesn't exist yet (will be created)\n";
    }
    
    $pending = [];
    foreach ($allMigrations as $migration) {
        if (!in_array($migration, $completed, true)) {
            $pending[] = $migration;
        }
    }
    
    if (empty($pending)) {
        echo "   ✓ No pending migrations found\n\n";
    } else {
        echo "   Found " . count($pending) . " pending migration(s):\n";
        foreach ($pending as $mig) {
            echo "      - " . $mig . "\n";
        }
        echo "\n";
    }
    
    // Run migrations
    echo "3. Running migrations...\n";
    
    $latest = $migrator->latest();
    
    echo "\n   ✓ Migrations completed successfully!\n\n";
    
    // Show final status
    echo "4. Migration Summary:\n";
    $migrationStatus = $db->table('migrations')
        ->select('file, batch')
        ->orderBy('batch DESC, migration_time DESC')
        ->limit(15)
        ->get()
        ->getResultArray();
    
    if (!empty($migrationStatus)) {
        $batches = [];
        foreach ($migrationStatus as $row) {
            $batch = $row['batch'];
            if (!isset($batches[$batch])) {
                $batches[$batch] = [];
            }
            $batches[$batch][] = basename($row['file']);
        }
        
        foreach ($batches as $batch => $files) {
            echo "   Batch $batch:\n";
            foreach ($files as $file) {
                echo "      ✓ " . substr($file, 0, 60) . "\n";
            }
        }
    }
    
    echo "\n✓ All migrations completed successfully!\n";
    
    // Check if GRN schema changes were applied
    echo "\n5. Verifying GRN schema changes:\n";
    
    $checks = [
        ['table' => 'purchase_order_lines', 'column' => 'receive_status', 'description' => 'PO Line receive status tracking'],
        ['table' => 'purchase_order_lines', 'column' => 'fully_received_date', 'description' => 'Fully received date tracking'],
        ['table' => 'purchase_grn_lines', 'column' => 'warehouse_id', 'description' => 'Per-line warehouse'],
        ['table' => 'purchase_grn_lines', 'column' => 'location_id', 'description' => 'Per-line location'],
    ];
    
    foreach ($checks as $check) {
        $exists = $db->fieldExists($check['column'], $check['table']);
        $status = $exists ? '✓' : '✗';
        echo "   $status " . $check['description'] . " ({$check['table']}.{$check['column']})\n";
    }
    
    // Check grn_receipt_history table
    $tableExists = $db->tableExists('grn_receipt_history');
    $status = $tableExists ? '✓' : '✗';
    echo "   $status GRN Receipt History audit table (grn_receipt_history)\n";
    
    echo "\n✓ Schema verification complete!\n";
    
} catch (\Throwable $e) {
    http_response_code(500);
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    if (php_sapi_name() === 'cli') {
        echo "\nFull trace:\n" . $e->getTraceAsString() . "\n";
    }
}
?>
