<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStockReservations extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('stock_reservations')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'sales_order_id' => ['type' => 'INT', 'unsigned' => true],
            'sales_order_line_id' => ['type' => 'INT', 'unsigned' => true],
            'product_id' => ['type' => 'INT', 'unsigned' => true],
            'variant_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'warehouse_id' => ['type' => 'INT', 'unsigned' => true],
            'location_id' => ['type' => 'INT', 'unsigned' => true],
            'reserved_quantity' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'consumed_quantity' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'released_quantity' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
            'created_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'released_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'release_reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'released_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['product_id', 'variant_id', 'location_id', 'status'], false, false, 'idx_stock_reservation_availability');
        $this->forge->addKey(['sales_order_id', 'sales_order_line_id', 'status'], false, false, 'idx_stock_reservation_order');
        $this->forge->addForeignKey('sales_order_id', 'sales_orders', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('sales_order_line_id', 'sales_order_lines', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('warehouse_id', 'warehouses', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('location_id', 'warehouse_locations', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('stock_reservations', true);
    }

    public function down()
    {
        $this->forge->dropTable('stock_reservations', true);
    }
}
