<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Foreign-currency settlement details for a customer payment: the actual PKR
 * the bank credited, bank/correspondent charges deducted in transit, and any
 * withholding tax (e.g. Section 154 export WHT) deducted locally. Lets the
 * receiving page derive the realized FX rate and post the correct
 * bank/charges/WHT/exchange-gain-or-loss journal lines instead of assuming
 * fx_rate=1 for foreign-currency receipts.
 */
class AddCustomerPaymentFxSettlementFields extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('customer_payments')) return;
        $cols = $this->db->getFieldNames('customer_payments');

        $add = [];
        if (!in_array('received_amount_base', $cols, true)) {
            $add['received_amount_base'] = ['type' => 'DECIMAL', 'constraint' => '14,2', 'null' => true];
        }
        if (!in_array('bank_charges_fc', $cols, true)) {
            $add['bank_charges_fc'] = ['type' => 'DECIMAL', 'constraint' => '14,2', 'null' => true];
        }
        if (!in_array('withholding_tax_base', $cols, true)) {
            $add['withholding_tax_base'] = ['type' => 'DECIMAL', 'constraint' => '14,2', 'null' => true];
        }
        if (!in_array('settlement_fx_rate', $cols, true)) {
            $add['settlement_fx_rate'] = ['type' => 'DECIMAL', 'constraint' => '12,4', 'null' => true];
        }

        if (!empty($add)) {
            $this->forge->addColumn('customer_payments', $add);
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('customer_payments')) return;
        $cols = $this->db->getFieldNames('customer_payments');
        foreach (['received_amount_base', 'bank_charges_fc', 'withholding_tax_base', 'settlement_fx_rate'] as $c) {
            if (in_array($c, $cols, true)) {
                $this->forge->dropColumn('customer_payments', $c);
            }
        }
    }
}
