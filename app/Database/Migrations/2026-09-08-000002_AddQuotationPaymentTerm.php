<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Payment terms on a quotation, so the instalment plan is agreed and approved
 * with the quote and carries into the invoice raised after approval.
 */
class AddQuotationPaymentTerm extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('quotations')) return;
        if (in_array('payment_term_id', $this->db->getFieldNames('quotations'), true)) return;

        $this->forge->addColumn('quotations', [
            'payment_term_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ]);
    }

    public function down()
    {
        if (!$this->db->tableExists('quotations')) return;
        if (in_array('payment_term_id', $this->db->getFieldNames('quotations'), true)) {
            $this->forge->dropColumn('quotations', 'payment_term_id');
        }
    }
}
