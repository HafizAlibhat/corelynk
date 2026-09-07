<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddVendorBillPoLinkage extends Migration
{
    public function up()
    {
        // Add po_id and based_on columns to vendor_bills if they don't exist
        if ($this->db->tableExists('vendor_bills')) {
            if (!$this->db->fieldExists('po_id', 'vendor_bills')) {
                $this->forge->addColumn('vendor_bills', [
                    'po_id' => [
                        'type' => 'INT',
                        'null' => true,
                        'after' => 'vendor_id',
                    ]
                ]);
            }

            if (!$this->db->fieldExists('based_on', 'vendor_bills')) {
                $this->forge->addColumn('vendor_bills', [
                    'based_on' => [
                        'type' => 'ENUM',
                        'constraint' => ['po_qty', 'grn_qty', 'manual'],
                        'default' => 'manual',
                        'null' => false,
                        'after' => 'po_id',
                    ]
                ]);
            }
        }

        // Create vendor_bill_lines table if it doesn't exist
        if (!$this->db->tableExists('vendor_bill_lines')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'vendor_bill_id' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'null' => false,
                ],
                'po_line_id' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'null' => true,
                ],
                'product_id' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'null' => true,
                ],
                'variant_id' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'null' => true,
                ],
                'qty' => [
                    'type' => 'DECIMAL',
                    'constraint' => '18,4',
                    'null' => false,
                    'default' => 0,
                ],
                'unit_price' => [
                    'type' => 'DECIMAL',
                    'constraint' => '18,4',
                    'null' => false,
                    'default' => 0,
                ],
                'line_total' => [
                    'type' => 'DECIMAL',
                    'constraint' => '18,4',
                    'null' => false,
                    'default' => 0,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addForeignKey('vendor_bill_id', 'vendor_bills', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('po_line_id', 'purchase_order_lines', 'id', 'SET NULL', 'CASCADE');
            $this->forge->addKey('vendor_bill_id');
            $this->forge->addKey('po_line_id');
            $this->forge->addKey('product_id');
            $this->forge->createTable('vendor_bill_lines');
        }

        // Add foreign key for po_id if it doesn't exist
        if ($this->db->tableExists('vendor_bills') && $this->db->tableExists('purchase_orders')) {
            try {
                $constraints = $this->db->query(
                    "SELECT CONSTRAINT_NAME 
                     FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                     WHERE TABLE_SCHEMA = DATABASE() 
                     AND TABLE_NAME = 'vendor_bills' 
                     AND CONSTRAINT_NAME = 'fk_vendor_bills_po_id'"
                )->getResultArray();

                if (empty($constraints)) {
                    $this->db->query(
                        'ALTER TABLE vendor_bills 
                         ADD CONSTRAINT fk_vendor_bills_po_id 
                         FOREIGN KEY (po_id) REFERENCES purchase_orders(id) 
                         ON DELETE SET NULL'
                    );
                }
            } catch (\Exception $e) {
                log_message('warning', 'Could not add FK constraint for vendor_bills.po_id: ' . $e->getMessage());
            }
        }
    }

    public function down()
    {
        // Drop foreign key
        try {
            $this->db->query('ALTER TABLE vendor_bills DROP FOREIGN KEY fk_vendor_bills_po_id');
        } catch (\Exception $e) {
            // May not exist
        }

        // Drop vendor_bill_lines table
        if ($this->db->tableExists('vendor_bill_lines')) {
            $this->forge->dropTable('vendor_bill_lines');
        }

        // Drop columns from vendor_bills
        if ($this->db->tableExists('vendor_bills')) {
            if ($this->db->fieldExists('based_on', 'vendor_bills')) {
                $this->forge->dropColumn('vendor_bills', 'based_on');
            }
            if ($this->db->fieldExists('po_id', 'vendor_bills')) {
                $this->forge->dropColumn('vendor_bills', 'po_id');
            }
        }
    }
}
