<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPoNumberToPurchaseOrders extends Migration
{
    public function up()
    {
        $forge = $this->forge;
        if (! $this->db->fieldExists('po_number', 'purchase_orders')) {
            $forge->addColumn('purchase_orders', [
                'po_number' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'null' => true,
                    'after' => 'id',
                ],
            ]);
        }
    }

    public function down()
    {
        $forge = $this->forge;
        if ($this->db->fieldExists('po_number', 'purchase_orders')) {
            $forge->dropColumn('purchase_orders', 'po_number');
        }
    }
}
