<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRfqIdToPurchaseOrders extends Migration
{
    public function up()
    {
        $forge = $this->forge;
        if (! $this->db->fieldExists('rfq_id', 'purchase_orders')) {
            $forge->addColumn('purchase_orders', [
                'rfq_id' => [
                    'type' => 'INT',
                    'null' => true,
                    'after' => 'id',
                ],
            ]);
        }
    }

    public function down()
    {
        $forge = $this->forge;
        if ($this->db->fieldExists('rfq_id', 'purchase_orders')) {
            $forge->dropColumn('purchase_orders', 'rfq_id');
        }
    }
}
