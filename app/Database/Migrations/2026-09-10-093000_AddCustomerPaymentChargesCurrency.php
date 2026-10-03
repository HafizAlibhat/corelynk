<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Lets a customer payment's bank/transfer charges be entered in whichever
 * currency the user actually saw them in (e.g. Stripe/Wise fees quoted in
 * GBP for a USD invoice), instead of forcing everyone to convert manually.
 * NULL means "same as the invoice/payment currency" (prior behavior).
 */
class AddCustomerPaymentChargesCurrency extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('customer_payments')) return;
        if (in_array('bank_charges_currency', $this->db->getFieldNames('customer_payments'), true)) return;

        $this->forge->addColumn('customer_payments', [
            'bank_charges_currency' => ['type' => 'VARCHAR', 'constraint' => 3, 'null' => true],
        ]);
    }

    public function down()
    {
        if (!$this->db->tableExists('customer_payments')) return;
        if (in_array('bank_charges_currency', $this->db->getFieldNames('customer_payments'), true)) {
            $this->forge->dropColumn('customer_payments', 'bank_charges_currency');
        }
    }
}
