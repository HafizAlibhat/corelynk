<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBatchReleases extends Migration
{
    public function up()
    {
        $this->db->disableForeignKeyChecks();
        $this->forge->addField([
            'id'          => ['type' => 'INT','constraint' => 11,'unsigned' => true,'auto_increment' => true],
            'process_batch_id' => ['type' => 'INT','constraint' => 11,'unsigned' => true],
            'released_qty' => ['type' => 'INT','constraint' => 11,'default' => 0],
            'released_by' => ['type' => 'INT','constraint' => 11,'null' => true],
            'released_at' => ['type' => 'DATETIME','null' => true],
            'carrier' => ['type' => 'VARCHAR','constraint' => 191,'null' => true],
            'notes' => ['type' => 'TEXT','null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('process_batch_id');
        $this->forge->addForeignKey('process_batch_id', 'process_batches', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('process_batch_releases', true);
        $this->db->enableForeignKeyChecks();
    }

    public function down()
    {
        $this->forge->dropTable('process_batch_releases', true);
    }
}
