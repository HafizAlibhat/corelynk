<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddReceiptNumberToCheques extends Migration
{
    public function up()
    {
        $fields = [
            'receipt_number' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
                'default' => null,
            ],
        ];
        $this->forge->addColumn('cheques', $fields);
        // add an index to speed lookups (optional)
        $this->forge->addKey('receipt_number');
    }

    public function down()
    {
        $this->forge->dropColumn('cheques', 'receipt_number');
    }
}
