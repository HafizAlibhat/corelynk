<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProductAttributes extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'values' => ['type' => 'TEXT', 'null' => true], // stored as JSON array
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('product_attributes', true);
    }

    public function down()
    {
        $this->forge->dropTable('product_attributes', true);
    }
}
