<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateVariantInventory extends Migration
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
            'variant_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            // warehouse reference (nullable to be safe if warehouses are represented differently)
            'warehouse_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'quantity' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,4',
                'default'    => 0,
            ],
            'reserved' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,4',
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
        $this->forge->addKey('variant_id');
        $this->forge->addKey('warehouse_id');
        // ensure one row per variant+warehouse
        $this->forge->addKey(['variant_id', 'warehouse_id'], false, true);

        $this->forge->createTable('variant_inventory');
    }

    public function down()
    {
        $this->forge->dropTable('variant_inventory');
    }
}
