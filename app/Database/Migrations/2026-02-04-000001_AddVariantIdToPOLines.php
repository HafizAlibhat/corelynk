<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddVariantIdToPOLines extends Migration
{
    public function up()
    {
        // Add variant_id column to purchase_order_lines if not already present
        $db = \Config\Database::connect();
        try {
            $cols = $db->getFieldNames('purchase_order_lines');
        } catch (\Throwable $e) {
            $cols = [];
        }
        
        if (!in_array('variant_id', $cols, true)) {
            $this->forge->addColumn('purchase_order_lines', [
                'variant_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true, 'after' => 'product_id']
            ]);
        }
    }

    public function down()
    {
        // Optional: drop the column on rollback
        try {
            $db = \Config\Database::connect();
            $cols = $db->getFieldNames('purchase_order_lines');
            if (in_array('variant_id', $cols, true)) {
                $this->forge->dropColumn('purchase_order_lines', 'variant_id');
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
