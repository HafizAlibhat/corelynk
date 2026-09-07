<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Create delivery_order_parcel_images table for storing multiple parcel images per delivery order.
 */
class CreateDeliveryOrderParcelImages extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('delivery_order_parcel_images')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'delivery_order_id' => [
                    'type'       => 'INT',
                    'unsigned'   => true,
                    'null'       => false,
                ],
                'image_path' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 500,
                    'null'       => false,
                ],
                'created_at' => [
                    'type'   => 'DATETIME',
                    'null'   => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('delivery_order_id');
            $this->forge->addForeignKey('delivery_order_id', 'delivery_orders', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('delivery_order_parcel_images');
        }
    }

    public function down()
    {
        if ($this->db->tableExists('delivery_order_parcel_images')) {
            $this->forge->dropTable('delivery_order_parcel_images');
        }
    }
}
