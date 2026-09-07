<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddInvoiceLinksAndPostingMetadata extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        try {
            // customer_invoices: add sales_order_id, posted_at, posted_by
            $cols = $db->getFieldNames('customer_invoices');
        } catch (\Throwable $_) {
            $cols = [];
        }

        $fields = [];
        if (!in_array('sales_order_id', $cols)) {
            $fields['sales_order_id'] = [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true,
                'after' => 'created_by'
            ];
        }
        if (!in_array('posted_at', $cols)) {
            $fields['posted_at'] = [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'status'
            ];
        }
        if (!in_array('posted_by', $cols)) {
            $fields['posted_by'] = [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true,
                'after' => 'posted_at'
            ];
        }

        if (!empty($fields)) {
            try {
                $forge = \Config\Database::forge();
                $forge->addColumn('customer_invoices', $fields);
            } catch (\Throwable $_) {
                // best-effort: skip if cannot alter
            }
        }

        // sales_orders: add invoice_id
        try {
            $sCols = $db->getFieldNames('sales_orders');
        } catch (\Throwable $_) {
            $sCols = [];
        }

        if (!in_array('invoice_id', $sCols)) {
            try {
                $forge = \Config\Database::forge();
                $forge->addColumn('sales_orders', [
                    'invoice_id' => [
                        'type' => 'INT',
                        'constraint' => 11,
                        'null' => true,
                        'after' => 'id'
                    ]
                ]);
            } catch (\Throwable $_) {
                // best-effort
            }
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        try { $cols = $db->getFieldNames('customer_invoices'); } catch (\Throwable $_) { $cols = []; }
        try { $sCols = $db->getFieldNames('sales_orders'); } catch (\Throwable $_) { $sCols = []; }

        $forge = \Config\Database::forge();
        if (in_array('sales_order_id', $cols)) {
            try { $forge->dropColumn('customer_invoices', 'sales_order_id'); } catch (\Throwable $_) {}
        }
        if (in_array('posted_at', $cols)) {
            try { $forge->dropColumn('customer_invoices', 'posted_at'); } catch (\Throwable $_) {}
        }
        if (in_array('posted_by', $cols)) {
            try { $forge->dropColumn('customer_invoices', 'posted_by'); } catch (\Throwable $_) {}
        }
        if (in_array('invoice_id', $sCols)) {
            try { $forge->dropColumn('sales_orders', 'invoice_id'); } catch (\Throwable $_) {}
        }
    }
}
