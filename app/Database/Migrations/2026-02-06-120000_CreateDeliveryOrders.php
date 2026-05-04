<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDeliveryOrders extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('delivery_orders')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
            'sales_order_id' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => false,
            ],
            'do_number' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => false,
                'unique' => true,
            ],
            'status' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'draft',
                'comment' => 'draft, confirmed, delivered, cancelled',
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
        $this->forge->addKey('sales_order_id');
        $this->forge->addKey('status');
        $this->forge->createTable('delivery_orders');
        }
    }

    public function down()
    {
        if ($this->db->tableExists('delivery_orders')) {
            $this->forge->dropTable('delivery_orders');
        }
    }
}
