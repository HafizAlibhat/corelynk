<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;

class AddCreatedByToPurchaseOrders extends Migration
{
    public function up()
    {
        $db = Database::connect();
        try {
            $cols = $db->getFieldNames('purchase_orders');
        } catch (\Throwable $e) {
            $cols = [];
        }

        if (! in_array('created_by', $cols)) {
            $fields = [
                'created_by' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                    'unsigned' => true,
                ],
            ];
            $this->forge->addColumn('purchase_orders', $fields);
        }
    }

    public function down()
    {
        $db = Database::connect();
        try {
            $cols = $db->getFieldNames('purchase_orders');
        } catch (\Throwable $e) {
            $cols = [];
        }

        if (in_array('created_by', $cols)) {
            $this->forge->dropColumn('purchase_orders', 'created_by');
        }
    }
}
