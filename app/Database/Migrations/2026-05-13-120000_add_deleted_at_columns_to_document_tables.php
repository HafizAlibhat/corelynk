<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDeletedAtColumnsToDocumentTables extends Migration
{
    public function up()
    {
        // Add deleted_at column to quotations (for soft delete support)
        if (!$this->db->fieldExists('deleted_at', 'quotations')) {
            $this->forge->addColumn('quotations', [
                'deleted_at' => [
                    'type'       => 'DATETIME',
                    'null'       => true,
                    'default'    => null,
                    'comment'    => 'Soft delete timestamp',
                ],
            ]);
        }

        // Add deleted_at column to sales_orders
        if (!$this->db->fieldExists('deleted_at', 'sales_orders')) {
            $this->forge->addColumn('sales_orders', [
                'deleted_at' => [
                    'type'       => 'DATETIME',
                    'null'       => true,
                    'default'    => null,
                    'comment'    => 'Soft delete timestamp',
                ],
            ]);
        }

        // Add deleted_at column to quotation_lines
        if (!$this->db->fieldExists('deleted_at', 'quotation_lines')) {
            $this->forge->addColumn('quotation_lines', [
                'deleted_at' => [
                    'type'       => 'DATETIME',
                    'null'       => true,
                    'default'    => null,
                    'comment'    => 'Soft delete timestamp',
                ],
            ]);
        }

        // Add deleted_at column to sales_order_lines
        if (!$this->db->fieldExists('deleted_at', 'sales_order_lines')) {
            $this->forge->addColumn('sales_order_lines', [
                'deleted_at' => [
                    'type'       => 'DATETIME',
                    'null'       => true,
                    'default'    => null,
                    'comment'    => 'Soft delete timestamp',
                ],
            ]);
        }
    }

    public function down()
    {
        // Drop columns in reverse order
        if ($this->db->fieldExists('deleted_at', 'sales_order_lines')) {
            $this->forge->dropColumn('sales_order_lines', 'deleted_at');
        }
        if ($this->db->fieldExists('deleted_at', 'quotation_lines')) {
            $this->forge->dropColumn('quotation_lines', 'deleted_at');
        }
        if ($this->db->fieldExists('deleted_at', 'sales_orders')) {
            $this->forge->dropColumn('sales_orders', 'deleted_at');
        }
        if ($this->db->fieldExists('deleted_at', 'quotations')) {
            $this->forge->dropColumn('quotations', 'deleted_at');
        }
    }
}
