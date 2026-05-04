<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddVariantIdToPurchaseGrnLines extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('variant_id', 'purchase_grn_lines')) {
            $this->forge->addColumn('purchase_grn_lines', [
                'variant_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                    'after' => 'product_id'
                ]
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('variant_id', 'purchase_grn_lines')) {
            $this->forge->dropColumn('purchase_grn_lines', 'variant_id');
        }
    }
}
