<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddVendorPriceToProducts extends Migration
{
    public function up()
    {
        $fields = [
            'vendor_price' => [
                'type' => 'DECIMAL',
                'constraint' => '15,2',
                'null' => true,
                'default' => null,
            ],
            'vendor_currency' => [
                'type' => 'VARCHAR',
                'constraint' => 3,
                'null' => true,
                'default' => null,
            ],
        ];

        $this->forge->addColumn('products', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('products', 'vendor_price');
        $this->forge->dropColumn('products', 'vendor_currency');
    }
}
