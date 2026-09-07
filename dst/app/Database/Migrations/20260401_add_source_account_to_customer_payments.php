<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSourceAccountToCustomerPayments extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('customer_payments')) {
            if (! $this->db->fieldExists('source_account_id', 'customer_payments')) {
                $this->forge->addColumn('customer_payments', [
                    'source_account_id' => [
                        'type' => 'INT',
                        'constraint' => 11,
                        'null' => true,
                        'default' => null,
                        'after' => 'advance_amount',
                    ],
                ]);
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('customer_payments') && $this->db->fieldExists('source_account_id', 'customer_payments')) {
            $this->forge->dropColumn('customer_payments', 'source_account_id');
        }
    }
}
