<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStockAdjustmentRequestBatches extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('stock_adjustment_request_batches')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'request_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'batch_id' => ['type' => 'INT', 'unsigned' => true],
            'warehouse_id' => ['type' => 'INT', 'unsigned' => true],
            'location_id' => ['type' => 'INT', 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['request_id', 'batch_id'], 'uq_stock_adjustment_request_batch');
        $this->forge->addKey(['request_id', 'id'], false, false, 'idx_stock_adjustment_request_batches');
        $this->forge->createTable('stock_adjustment_request_batches', true);
    }

    public function down()
    {
        $this->forge->dropTable('stock_adjustment_request_batches', true);
    }
}
