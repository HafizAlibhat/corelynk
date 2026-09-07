<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDatesToPurchaseRfqs extends Migration
{
    public function up()
    {
        $fields = [
            'rfq_date' => [ 'type' => 'DATETIME', 'null' => true ],
            'delivery_date' => [ 'type' => 'DATE', 'null' => true ]
        ];
        $this->forge->addColumn('purchase_rfqs', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('purchase_rfqs', ['rfq_date','delivery_date']);
    }
}
