<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProductProcesses extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('product_processes')) {
            return;
        }

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
            ],
            'process_id' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
            ],
            'sequence_order' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true,
            ],
            'is_active' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 1,
            ],
            'is_vendor_process' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
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
        $this->forge->addKey('product_id');
        $this->forge->addKey('process_id');
        $this->forge->createTable('product_processes', true);
    }

    public function down()
    {
        $this->forge->dropTable('product_processes', true);
    }
}
