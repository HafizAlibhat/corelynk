<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCategoryIdToProcesses extends Migration
{
    public function up()
    {
        // If the processes table exists, add the column if missing.
        if ($this->db->tableExists('processes')) {
            if (! $this->db->fieldExists('category_id', 'processes')) {
                $fields = [
                    'category_id' => [
                        'type' => 'INT',
                        'constraint' => 10,
                        'unsigned' => true,
                        'null' => true,
                        'default' => null,
                        'after' => 'process_code',
                    ],
                ];

                $this->forge->addColumn('processes', $fields);
            }

            return;
        }

        // Fallback: create a minimal processes table including category_id.
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
            ],
            'process_code' => [
                'type' => 'VARCHAR',
                'constraint' => '100',
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'category_id' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'null' => true,
                'default' => null,
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
        $this->forge->createTable('processes', true);
    }

    public function down()
    {
        if ($this->db->tableExists('processes') && $this->db->fieldExists('category_id', 'processes')) {
            $this->forge->dropColumn('processes', 'category_id');
        }
    }
}
