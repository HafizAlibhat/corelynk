<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProcessCategoriesTable extends Migration
{
    public function up()
    {
        // Create process_categories table
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => false,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'is_active' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 1,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->createTable('process_categories', true);

        // Insert some default categories
        $data = [
            ['name' => 'Manufacturing', 'description' => 'Manufacturing processes', 'is_active' => 1],
            ['name' => 'Assembly', 'description' => 'Assembly processes', 'is_active' => 1],
            ['name' => 'Quality Control', 'description' => 'Quality control processes', 'is_active' => 1],
            ['name' => 'Finishing', 'description' => 'Finishing processes', 'is_active' => 1],
            ['name' => 'Packaging', 'description' => 'Packaging processes', 'is_active' => 1],
        ];

        $this->db->table('process_categories')->insertBatch($data);

        // Create product_processes table
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'product_id' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'null' => false,
            ],
            'process_id' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'null' => false,
            ],
            'sequence_order' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 1,
            ],
            'is_active' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 1,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->createTable('product_processes', true);
    }

    public function down()
    {
        $this->forge->dropTable('product_processes', true);
        $this->forge->dropTable('process_categories', true);
    }
}
