<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Lets the user pick which GL account bank charges / withholding tax /
 * exchange gain-loss post to on a foreign-currency settlement, instead of
 * always trusting the system's auto-detected account (by code/name match).
 * NULL means "use the auto-detected account" (prior behavior, unchanged).
 */
class AddCustomerPaymentAccountOverrides extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('customer_payments')) return;
        $existing = $this->db->getFieldNames('customer_payments');

        $toAdd = [];
        if (!in_array('bank_charges_account_id', $existing, true)) {
            $toAdd['bank_charges_account_id'] = ['type' => 'INT', 'constraint' => 11, 'null' => true];
        }
        if (!in_array('wht_account_id', $existing, true)) {
            $toAdd['wht_account_id'] = ['type' => 'INT', 'constraint' => 11, 'null' => true];
        }
        if (!in_array('gain_loss_account_id', $existing, true)) {
            $toAdd['gain_loss_account_id'] = ['type' => 'INT', 'constraint' => 11, 'null' => true];
        }

        if (!empty($toAdd)) {
            $this->forge->addColumn('customer_payments', $toAdd);
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('customer_payments')) return;
        $existing = $this->db->getFieldNames('customer_payments');
        foreach (['bank_charges_account_id', 'wht_account_id', 'gain_loss_account_id'] as $col) {
            if (in_array($col, $existing, true)) {
                $this->forge->dropColumn('customer_payments', $col);
            }
        }
    }
}
