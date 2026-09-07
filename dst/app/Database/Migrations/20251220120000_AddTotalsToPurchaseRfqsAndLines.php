<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTotalsToPurchaseRfqsAndLines extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        $forge = $this->forge;

        // Add totals to purchase_rfqs if missing
        if ($db->tableExists('purchase_rfqs')) {
            $fields = [];
            if (! $db->fieldExists('subtotal', 'purchase_rfqs')) $fields['subtotal'] = ['type' => 'FLOAT', 'null' => true, 'default' => 0];
            if (! $db->fieldExists('discount', 'purchase_rfqs')) $fields['discount'] = ['type' => 'FLOAT', 'null' => true, 'default' => 0];
            if (! $db->fieldExists('tax_amount', 'purchase_rfqs')) $fields['tax_amount'] = ['type' => 'FLOAT', 'null' => true, 'default' => 0];
            if (! $db->fieldExists('grand_total', 'purchase_rfqs')) $fields['grand_total'] = ['type' => 'FLOAT', 'null' => true, 'default' => 0];
            // New header totals: total_discount and total_tax (kept in addition to legacy fields)
            if (! $db->fieldExists('total_discount', 'purchase_rfqs')) $fields['total_discount'] = ['type' => 'FLOAT', 'null' => true, 'default' => 0];
            if (! $db->fieldExists('total_tax', 'purchase_rfqs')) $fields['total_tax'] = ['type' => 'FLOAT', 'null' => true, 'default' => 0];
            if (!empty($fields)) $forge->addColumn('purchase_rfqs', $fields);
        }

        // Add line_total to purchase_rfq_lines if missing
        if ($db->tableExists('purchase_rfq_lines')) {
            $fields = [];
            if (! $db->fieldExists('line_total', 'purchase_rfq_lines')) $fields['line_total'] = ['type' => 'FLOAT', 'null' => true, 'default' => 0];
            // Per-line discount/tax fields
            if (! $db->fieldExists('discount', 'purchase_rfq_lines')) $fields['discount'] = ['type' => 'FLOAT', 'null' => true, 'default' => 0];
            if (! $db->fieldExists('discount_percent', 'purchase_rfq_lines')) $fields['discount_percent'] = ['type' => 'FLOAT', 'null' => true, 'default' => 0];
            if (! $db->fieldExists('tax_percent', 'purchase_rfq_lines')) $fields['tax_percent'] = ['type' => 'FLOAT', 'null' => true, 'default' => 0];
            if (! $db->fieldExists('tax_amount', 'purchase_rfq_lines')) $fields['tax_amount'] = ['type' => 'FLOAT', 'null' => true, 'default' => 0];
            if (!empty($fields)) $forge->addColumn('purchase_rfq_lines', $fields);
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        if ($db->tableExists('purchase_rfqs')) {
            // drop both legacy and new total columns
            $cols = ['subtotal','discount','tax_amount','grand_total','total_discount','total_tax'];
            $this->forge->dropColumn('purchase_rfqs', $cols);
        }
        if ($db->tableExists('purchase_rfq_lines')) {
            $cols = ['line_total','discount','discount_percent','tax_percent','tax_amount'];
            $this->forge->dropColumn('purchase_rfq_lines', $cols);
        }
    }
}
