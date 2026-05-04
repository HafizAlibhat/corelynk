<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAvatarPathToCustomers extends Migration
{
    public function up()
    {
        $fields = [
            'avatar_path' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'customer_code'
            ]
        ];
        $this->forge->addColumn('customers', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('customers', 'avatar_path');
    }
}
