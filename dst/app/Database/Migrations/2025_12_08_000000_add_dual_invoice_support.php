<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDualInvoiceSupport extends Migration
{
    public function up()
    {
        // Add columns to customer_invoices safely
        $db = \Config\Database::connect();
        $forge = \Config\Database::forge();

        if (!$this->db->fieldExists('invoice_type', 'customer_invoices')) {
            $this->forge->addColumn('customer_invoices', [
                'invoice_type' => ['type' => "ENUM('system','custom')", 'null' => false, 'default' => 'system'],
            ]);
        }

        if (!$this->db->fieldExists('parent_invoice_id', 'customer_invoices')) {
            $this->forge->addColumn('customer_invoices', [
                'parent_invoice_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true, 'default' => null],
            ]);
        }

        if (!$this->db->fieldExists('is_custom_adjusted', 'customer_invoices')) {
            $this->forge->addColumn('customer_invoices', [
                'is_custom_adjusted' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            ]);
        }

        if (!$this->db->fieldExists('custom_notes', 'customer_invoices')) {
            $this->forge->addColumn('customer_invoices', [
                'custom_notes' => ['type' => 'TEXT', 'null' => true, 'default' => null],
            ]);
        }

        if (!$this->db->fieldExists('shipping_cost', 'customer_invoices')) {
            $this->forge->addColumn('customer_invoices', [
                'shipping_cost' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => '0.00'],
            ]);
        }

        if (!$this->db->fieldExists('customs_value', 'customer_invoices')) {
            $this->forge->addColumn('customer_invoices', [
                'customs_value' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => '0.00'],
            ]);
        }

        if (!$this->db->fieldExists('export_reference', 'customer_invoices')) {
            $this->forge->addColumn('customer_invoices', [
                'export_reference' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'default' => null],
            ]);
        }

    // Create invoice_documents table if not exists
    if (!$this->db->tableExists('invoice_documents')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'invoice_id' => ['type' => 'INT', 'constraint' => 11, 'null' => false],
                'document_type' => ['type' => "ENUM('system_invoice','custom_invoice','receipt','credit_note')", 'null' => false],
                'file_path' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => false],
                'file_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
                'generated_at' => ['type' => 'DATETIME', 'null' => false, 'default' => null],
                'generated_by' => ['type' => 'INT', 'constraint' => 11, 'null' => false],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('invoice_id');
            $this->forge->createTable('invoice_documents', true);

            // Add foreign key via direct query if DB supports it
            try {
                $db->query('ALTER TABLE `invoice_documents` ADD CONSTRAINT `fk_invoice_documents_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `customer_invoices`(`id`) ON DELETE CASCADE');
            } catch (\Throwable $e) {
                // ignore if FK cannot be created in older MyISAM or other setups
            }
        }
    }

    public function down()
    {
        // Drop invoice_documents
        if ($this->db->tableExists('invoice_documents')) {
            $this->forge->dropTable('invoice_documents', true);
        }

        // Drop columns from customer_invoices if exist
        $cols = ['invoice_type','parent_invoice_id','is_custom_adjusted','custom_notes','shipping_cost','customs_value','export_reference'];
        foreach ($cols as $col) {
            if ($this->db->fieldExists($col, 'customer_invoices')) {
                try {
                    $this->forge->dropColumn('customer_invoices', $col);
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }
    }
}
