<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProductAttributeAssignments extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'product_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'attribute_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            // Stable ordering for later deterministic variant generation / article number derivation
            'position' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('product_id');
        $this->forge->addKey('attribute_id');
        $this->forge->addKey(['product_id', 'attribute_id'], false, true);

        if ($db->tableExists('products')) {
            $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'CASCADE');
        }
        if ($db->tableExists('product_attributes')) {
            $this->forge->addForeignKey('attribute_id', 'product_attributes', 'id', 'CASCADE', 'CASCADE');
        }

        $this->forge->createTable('product_attribute_assignments', true);
    }

    public function down()
    {
        $this->forge->dropTable('product_attribute_assignments', true);
    }
}
