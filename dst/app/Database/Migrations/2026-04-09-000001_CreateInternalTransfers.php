<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInternalTransfers extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'transfer_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => false,
            ],
            'product_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'variant_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'item_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
            ],
            'quantity' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,4',
            ],
            'from_warehouse_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'from_location_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'to_warehouse_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'to_location_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'reason' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'out_movement_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'in_movement_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'created_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
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
        $this->forge->addUniqueKey('transfer_number');
        $this->forge->addKey('product_id');
        $this->forge->addKey('from_location_id');
        $this->forge->addKey('to_location_id');
        $this->forge->addKey('created_at');
        $this->forge->createTable('internal_transfers', true);
    }

    public function down()
    {
        $this->forge->dropTable('internal_transfers', true);
    }
}
