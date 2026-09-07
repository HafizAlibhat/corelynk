<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixPurchaseOrderLines extends Migration
{
    public function up()
    {
        // Add created_at if missing
        $fields = [];
        $db = \Config\Database::connect();
        try {
            $cols = $db->getFieldNames('purchase_order_lines');
        } catch (\Throwable $e) {
            $cols = [];
        }
        if (! in_array('created_at', $cols)) {
            $this->forge->addColumn('purchase_order_lines', [
                'created_at' => ['type' => 'DATETIME', 'null' => true]
            ]);
        }
        // Ensure id is auto_increment
        try {
            $this->db->query("ALTER TABLE `purchase_order_lines` MODIFY `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT");
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function down()
    {
        // no-op
    }
}
