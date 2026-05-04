<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOverReceivedToGrnLines extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('over_received_qty', 'purchase_grn_lines')) {
            $this->forge->addColumn('purchase_grn_lines', [
                'over_received_qty' => [
                    'type' => 'DECIMAL',
                    'constraint' => '18,4',
                    'null' => false,
                    'default' => '0.0000',
                    'after' => 'qty_received'
                ]
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('over_received_qty', 'purchase_grn_lines')) {
            $this->forge->dropColumn('purchase_grn_lines', 'over_received_qty');
        }
    }
}
