<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * CLI command to backfill public_id (UUID) for existing records.
 *
 * Usage:
 *   php spark security:backfill-public-ids
 *   php spark security:backfill-public-ids --table=vendors
 */
class BackfillPublicIds extends BaseCommand
{
    protected $group       = 'Security';
    protected $name        = 'security:backfill-public-ids';
    protected $description = 'Generate public_id (UUID) for existing records that have none.';

    protected $usage   = 'security:backfill-public-ids [--table=<table>]';
    protected $options = [
        '--table' => 'Only backfill a specific table (e.g. vendors)',
    ];

    private array $targets = [
        'vendors'            => \App\Models\VendorModel::class,
        'customers'          => \App\Models\CustomerModel::class,
        'products'           => \App\Models\ProductModel::class,
        'purchase_orders'    => \App\Models\PurchaseOrderModel::class,
        'quotations'         => \App\Models\QuotationModel::class,
        'invoices'           => \App\Models\InvoiceModel::class,
        'vendor_bills'       => \App\Models\VendorBillModel::class,
        'purchase_rfqs'      => \App\Models\PurchaseRfqModel::class,
        'delivery_orders'    => \App\Models\DeliveryOrderModel::class,
        'customer_invoices'  => \App\Models\CustomerInvoiceModel::class,
        'gate_passes'        => \App\Models\GatePassModel::class,
        'subcontract_orders' => \App\Models\SubcontractOrderModel::class,
    ];

    public function run(array $params)
    {
        $tableFilter = $params['table'] ?? CLI::getOption('table');

        $db = \Config\Database::connect();
        $total = 0;

        foreach ($this->targets as $table => $modelClass) {
            if ($tableFilter && $table !== $tableFilter) {
                continue;
            }

            if (! $db->tableExists($table)) {
                CLI::write("  Table '{$table}' does not exist — skipped.", 'yellow');
                continue;
            }

            if (! $db->fieldExists('public_id', $table)) {
                CLI::write("  Table '{$table}' has no public_id column — skipped. Run migration first.", 'yellow');
                continue;
            }

            if (! class_exists($modelClass)) {
                CLI::write("  Model '{$modelClass}' not found — skipped.", 'yellow');
                continue;
            }

            $nullCount = $db->table($table)->where('public_id IS NULL')->countAllResults();
            if ($nullCount === 0) {
                CLI::write("  {$table}: all records already have public_id.", 'green');
                continue;
            }

            CLI::write("  {$table}: backfilling {$nullCount} records...");

            $count = 0;
            do {
                $rows = $db->table($table)
                           ->where('public_id IS NULL')
                           ->limit(500)
                           ->get()
                           ->getResultArray();

                foreach ($rows as $row) {
                    $uuid = \App\Traits\PublicIdTrait::uuid4();
                    $db->table($table)
                       ->where('id', $row['id'])
                       ->update(['public_id' => $uuid]);
                    $count++;
                }
            } while (count($rows) === 500);

            CLI::write("  {$table}: ✓ {$count} records updated.", 'green');
            $total += $count;
        }

        CLI::write("\nDone. Total records backfilled: {$total}", $total > 0 ? 'green' : 'white');
    }
}
