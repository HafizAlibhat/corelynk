<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Create delivery_order_tracking_docs table for storing tracking documents per delivery order.
 */
class CreateDeliveryOrderTrackingDocs extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('delivery_order_tracking_docs')) {
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
                'file_path' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 500,
                    'null'       => false,
                ],
                'original_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'created_at' => [
                    'type'   => 'DATETIME',
                    'null'   => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('delivery_order_id');
            $this->forge->addForeignKey('delivery_order_id', 'delivery_orders', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('delivery_order_tracking_docs');
        }
    }

    public function down()
    {
        if ($this->db->tableExists('delivery_order_tracking_docs')) {
            $this->forge->dropTable('delivery_order_tracking_docs');
        }
    }
}
