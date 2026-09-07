<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Customers ask for their own purchase order number on the invoice so their
 * accounts payable team can match it. Optional: blank invoices simply do not
 * print the row.
 */
class AddCustomerPoNumberToInvoices extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('customer_invoices')) {
            return;
        }

        if (! in_array('customer_po_number', $this->db->getFieldNames('customer_invoices'), true)) {
            $this->forge->addColumn('customer_invoices', [
                'customer_po_number' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('customer_invoices')
            && in_array('customer_po_number', $this->db->getFieldNames('customer_invoices'), true)) {
            $this->forge->dropColumn('customer_invoices', 'customer_po_number');
        }
    }
}
