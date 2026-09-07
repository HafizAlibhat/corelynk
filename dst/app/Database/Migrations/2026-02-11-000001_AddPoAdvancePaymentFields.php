<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Add PO Advance Payment Support
 * 
 * Adds minimal fields to vendor_payments to support advance payments from PO:
 * - po_id: Links payment to specific PO
 * - payment_type: Distinguishes 'advance' from 'bill_payment'
 * 
 * STRICT RULES:
 * - Does NOT modify existing columns
 * - Does NOT break existing vendor payment functionality
 * - Allows advance payment tracking separate from bill payments
 */
class AddPoAdvancePaymentFields extends Migration
{
    public function up()
    {
        // Add po_id column (nullable - existing payments won't have PO reference)
        if (!$this->db->fieldExists('po_id', 'vendor_payments')) {
            $this->forge->addColumn('vendor_payments', [
                'po_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                    'after' => 'vendor_id'
                ]
            ]);
            
            // Add index for performance
            $this->db->query('CREATE INDEX idx_vendor_payments_po_id ON vendor_payments(po_id)');
        }

        // Add payment_type column
        if (!$this->db->fieldExists('payment_type', 'vendor_payments')) {
            $this->forge->addColumn('vendor_payments', [
                'payment_type' => [
                    'type' => 'ENUM',
                    'constraint' => ['advance', 'bill_payment'],
                    'default' => 'bill_payment',
                    'null' => false,
                    'after' => 'payment_method'
                ]
            ]);
        }

        // Add foreign key constraint (if purchase_orders table exists)
        if ($this->db->tableExists('purchase_orders')) {
            try {
                // Check if constraint doesn't already exist
                $constraints = $this->db->query(
                    "SELECT CONSTRAINT_NAME 
                     FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                     WHERE TABLE_SCHEMA = DATABASE() 
                     AND TABLE_NAME = 'vendor_payments' 
                     AND CONSTRAINT_NAME = 'fk_vendor_payments_po_id'"
                )->getResultArray();
                
                if (empty($constraints)) {
                    $this->db->query(
                        'ALTER TABLE vendor_payments 
                         ADD CONSTRAINT fk_vendor_payments_po_id 
                         FOREIGN KEY (po_id) REFERENCES purchase_orders(id) 
                         ON DELETE SET NULL'
                    );
                }
            } catch (\Exception $e) {
                log_message('warning', 'Could not add FK constraint for vendor_payments.po_id: ' . $e->getMessage());
            }
        }
    }

    public function down()
    {
        // Drop foreign key first
        try {
            $this->db->query('ALTER TABLE vendor_payments DROP FOREIGN KEY fk_vendor_payments_po_id');
        } catch (\Exception $e) {
            // Constraint may not exist
        }

        // Drop index
        try {
            $this->db->query('DROP INDEX idx_vendor_payments_po_id ON vendor_payments');
        } catch (\Exception $e) {
            // Index may not exist
        }

        // Drop columns
        if ($this->db->fieldExists('payment_type', 'vendor_payments')) {
            $this->forge->dropColumn('vendor_payments', 'payment_type');
        }

        if ($this->db->fieldExists('po_id', 'vendor_payments')) {
            $this->forge->dropColumn('vendor_payments', 'po_id');
        }
    }
}
