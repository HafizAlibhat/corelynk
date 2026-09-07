<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Sales documents used to render the customer's address live, so changing the
 * customer's default address silently rewrote the address on every historical
 * quotation, order and invoice. Each document now carries its own JSON snapshot
 * of the address and contact used when it was issued.
 */
class AddCustomerSnapshotToSalesDocuments extends Migration
{
    private array $tables = ['quotations', 'sales_orders', 'customer_invoices'];

    public function up()
    {
        foreach ($this->tables as $table) {
            if (! $this->db->tableExists($table)) {
                continue;
            }
            if (in_array('customer_snapshot', $this->db->getFieldNames($table), true)) {
                continue;
            }

            $this->forge->addColumn($table, [
                'customer_snapshot' => [
                    'type'    => 'TEXT',
                    'null'    => true,
                    'comment' => 'JSON snapshot of the customer address/contact used on this document',
                ],
            ]);
        }
    }

    public function down()
    {
        foreach ($this->tables as $table) {
            if ($this->db->tableExists($table) && in_array('customer_snapshot', $this->db->getFieldNames($table), true)) {
                $this->forge->dropColumn($table, 'customer_snapshot');
            }
        }
    }
}
