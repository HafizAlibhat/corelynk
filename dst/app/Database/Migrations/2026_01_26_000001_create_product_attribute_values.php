<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProductAttributeValues extends Migration
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
            'attribute_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'value' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => false,
            ],
            // Deterministic token used later for variant article number derivation (e.g. RED, BLU, XL)
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'null'       => false,
            ],
            'sort_order' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
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
        $this->forge->addKey('attribute_id');
        $this->forge->addKey(['attribute_id', 'value'], false, true);
        $this->forge->addKey(['attribute_id', 'code'], false, true);

        if ($db->tableExists('product_attributes')) {
            $this->forge->addForeignKey('attribute_id', 'product_attributes', 'id', 'CASCADE', 'CASCADE');
        }

        $this->forge->createTable('product_attribute_values', true);
    }

    public function down()
    {
        $this->forge->dropTable('product_attribute_values', true);
    }
}
