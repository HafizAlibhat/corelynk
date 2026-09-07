<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateNewPurchaseTables extends Migration
{
    public function up()
    {
        // purchase_rfqs
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'rfq_number' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'vendor_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'draft'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('purchase_rfqs', true);

        // purchase_rfq_lines
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'rfq_id' => ['type' => 'INT', 'constraint' => 11],
            'product_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'description' => ['type' => 'TEXT', 'null' => true],
            'quantity' => ['type' => 'FLOAT', 'null' => false, 'default' => 0],
            'unit_cost' => ['type' => 'FLOAT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('purchase_rfq_lines', true);

        // purchase_orders
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'po_number' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'rfq_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'vendor_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'draft'],
            'subtotal' => ['type' => 'FLOAT', 'null' => true],
            'total' => ['type' => 'FLOAT', 'null' => true],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('purchase_orders', true);

        // purchase_order_lines
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'po_id' => ['type' => 'INT', 'constraint' => 11],
            'product_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'description' => ['type' => 'TEXT', 'null' => true],
            'quantity' => ['type' => 'FLOAT', 'null' => false, 'default' => 0],
            'unit_cost' => ['type' => 'FLOAT', 'null' => true],
            'qty_received' => ['type' => 'FLOAT', 'null' => false, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('purchase_order_lines', true);

        // purchase_grns
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'grn_number' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'po_id' => ['type' => 'INT', 'constraint' => 11],
            'vendor_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'received_at' => ['type' => 'DATETIME', 'null' => true],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('purchase_grns', true);

        // purchase_grn_lines
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'grn_id' => ['type' => 'INT', 'constraint' => 11],
            'po_line_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'product_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'description' => ['type' => 'TEXT', 'null' => true],
            'qty_received' => ['type' => 'FLOAT', 'null' => false, 'default' => 0],
            'unit_cost' => ['type' => 'FLOAT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('purchase_grn_lines', true);
    }

    public function down()
    {
        $this->forge->dropTable('purchase_grn_lines', true);
        $this->forge->dropTable('purchase_grns', true);
        $this->forge->dropTable('purchase_order_lines', true);
        $this->forge->dropTable('purchase_orders', true);
        $this->forge->dropTable('purchase_rfq_lines', true);
        $this->forge->dropTable('purchase_rfqs', true);
    }
}
