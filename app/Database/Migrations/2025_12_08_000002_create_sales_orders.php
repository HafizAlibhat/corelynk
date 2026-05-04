<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSalesOrders extends Migration
{
    public function up()
    {
        // sales_orders
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'order_number' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => false],
            'quotation_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'customer_id' => ['type' => 'INT', 'constraint' => 11, 'null' => false],
            'order_date' => ['type' => 'DATE', 'null' => true],
            'subtotal' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => '0.00'],
            'tax_total' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => '0.00'],
            'total' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => '0.00'],
            'status' => ['type' => "ENUM('draft','confirmed','shipped','closed')", 'default' => 'draft'],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('customer_id');
        $this->forge->createTable('sales_orders', true);

        // sales_order_lines
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'sales_order_id' => ['type' => 'INT', 'constraint' => 11, 'null' => false],
            'product_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => false],
            'quantity' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => '0.00'],
            'unit_price' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => '0.00'],
            'line_total' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => '0.00']
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('sales_order_id');
        $this->forge->createTable('sales_order_lines', true);
    }

    public function down()
    {
        $this->forge->dropTable('sales_order_lines', true);
        $this->forge->dropTable('sales_orders', true);
    }
}
