<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProcessingLinksToPurchasingBillingPhase5 extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('purchase_orders') && ! $this->db->fieldExists('processing_record_id', 'purchase_orders')) {
            $this->forge->addColumn('purchase_orders', [
                'processing_record_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'rfq_id',
                ],
            ]);

            $this->db->query('CREATE INDEX idx_purchase_orders_processing_record_id ON purchase_orders(processing_record_id)');
        }

        if ($this->db->tableExists('vendor_bill_lines') && ! $this->db->fieldExists('processing_record_id', 'vendor_bill_lines')) {
            $this->forge->addColumn('vendor_bill_lines', [
                'processing_record_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'po_line_id',
                ],
            ]);

            $this->db->query('CREATE INDEX idx_vendor_bill_lines_processing_record_id ON vendor_bill_lines(processing_record_id)');
        }

        if ($this->db->tableExists('purchase_orders') && $this->db->tableExists('processing_records')) {
            try {
                $this->db->query('ALTER TABLE purchase_orders ADD CONSTRAINT fk_purchase_orders_processing_record_id FOREIGN KEY (processing_record_id) REFERENCES processing_records(id) ON DELETE SET NULL ON UPDATE CASCADE');
            } catch (\Throwable $_) {
                // best-effort FK add
            }
        }

        if ($this->db->tableExists('vendor_bill_lines') && $this->db->tableExists('processing_records')) {
            try {
                $this->db->query('ALTER TABLE vendor_bill_lines ADD CONSTRAINT fk_vendor_bill_lines_processing_record_id FOREIGN KEY (processing_record_id) REFERENCES processing_records(id) ON DELETE SET NULL ON UPDATE CASCADE');
            } catch (\Throwable $_) {
                // best-effort FK add
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('vendor_bill_lines') && $this->db->fieldExists('processing_record_id', 'vendor_bill_lines')) {
            try {
                $this->db->query('ALTER TABLE vendor_bill_lines DROP FOREIGN KEY fk_vendor_bill_lines_processing_record_id');
            } catch (\Throwable $_) {
            }
            try {
                $this->db->query('DROP INDEX idx_vendor_bill_lines_processing_record_id ON vendor_bill_lines');
            } catch (\Throwable $_) {
            }
            $this->forge->dropColumn('vendor_bill_lines', 'processing_record_id');
        }

        if ($this->db->tableExists('purchase_orders') && $this->db->fieldExists('processing_record_id', 'purchase_orders')) {
            try {
                $this->db->query('ALTER TABLE purchase_orders DROP FOREIGN KEY fk_purchase_orders_processing_record_id');
            } catch (\Throwable $_) {
            }
            try {
                $this->db->query('DROP INDEX idx_purchase_orders_processing_record_id ON purchase_orders');
            } catch (\Throwable $_) {
            }
            $this->forge->dropColumn('purchase_orders', 'processing_record_id');
        }
    }
}
