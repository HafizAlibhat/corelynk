<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCancelFieldsToPurchaseRfqs extends Migration
{
    public function up()
    {
        $fields = [
            'cancel_reason' => ['type' => 'TEXT', 'null' => true],
            'cancelled_at' => ['type' => 'DATETIME', 'null' => true],
            'cancelled_by' => ['type' => 'INT', 'constraint' => 11, 'null' => true]
        ];
        $this->forge->addColumn('purchase_rfqs', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('purchase_rfqs', ['cancel_reason','cancelled_at','cancelled_by']);
    }
}
