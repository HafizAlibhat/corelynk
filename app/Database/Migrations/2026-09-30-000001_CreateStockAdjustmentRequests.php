<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStockAdjustmentRequests extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('stock_adjustment_requests')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'request_key' => ['type' => 'VARCHAR', 'constraint' => 64],
            'sales_order_id' => ['type' => 'INT', 'unsigned' => true],
            'batch_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_by' => ['type' => 'INT', 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('request_key', 'uq_stock_adjustment_request_key');
        $this->forge->addKey(['sales_order_id', 'created_at'], false, false, 'idx_stock_adjustment_request_order');
        $this->forge->createTable('stock_adjustment_requests', true);
    }

    public function down()
    {
        $this->forge->dropTable('stock_adjustment_requests', true);
    }
}
