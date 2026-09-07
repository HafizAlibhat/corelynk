<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDeliveryOrderLines extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('delivery_order_lines')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
            'delivery_order_id' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => false,
            ],
            'sales_order_line_id' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => false,
            ],
            'product_id' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => true,
            ],
            'quantity_ordered' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'default' => 0,
            ],
            'ready_qty' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'default' => 0,
                'comment' => 'Available to ship',
            ],
            'qty_to_ship' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'default' => 0,
                'comment' => 'User-editable quantity for delivery',
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
        $this->forge->addKey('delivery_order_id');
        $this->forge->addKey('sales_order_line_id');
        $this->forge->addKey('product_id');
        $this->forge->createTable('delivery_order_lines');
        }
    }

    public function down()
    {
        if ($this->db->tableExists('delivery_order_lines')) {
            $this->forge->dropTable('delivery_order_lines');
        }
    }
}
