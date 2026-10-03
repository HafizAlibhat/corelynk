<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ExtendStockReservations extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('stock_reservations')) {
            return;
        }

        $fields = [
            'document_type' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'after' => 'sales_order_line_id'],
            'document_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'document_type'],
        ];
        
        $existingFields = $this->db->getFieldNames('stock_reservations');
        if (!in_array('document_type', $existingFields, true)) {
            $this->forge->addColumn('stock_reservations', $fields);
        }

        // Make existing SO columns nullable
        $modify = [
            'sales_order_id' => ['name' => 'sales_order_id', 'type' => 'INT', 'unsigned' => true, 'null' => true],
            'sales_order_line_id' => ['name' => 'sales_order_line_id', 'type' => 'INT', 'unsigned' => true, 'null' => true],
        ];
        $this->forge->modifyColumn('stock_reservations', $modify);

        // Backfill
        $this->db->query("UPDATE stock_reservations SET document_type = 'sales_order', document_id = sales_order_id WHERE sales_order_id IS NOT NULL AND document_type IS NULL");
    }

    public function down()
    {
        if ($this->db->fieldExists('document_type', 'stock_reservations')) {
            $this->forge->dropColumn('stock_reservations', ['document_type', 'document_id']);
        }
    }
}
