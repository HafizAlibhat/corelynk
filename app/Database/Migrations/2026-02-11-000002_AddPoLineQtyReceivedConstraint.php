<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Add CHECK constraint to prevent over-receiving on PO lines
 * 
 * STRICT RULE: qty_received must NEVER exceed qty
 * 
 * This prevents data corruption where received quantity exceeds ordered quantity.
 * If MySQL version doesn't support CHECK constraints, validation enforced at application level.
 */
class AddPoLineQtyReceivedConstraint extends Migration
{
    public function up()
    {
        // Try to add CHECK constraint (MySQL 8.0.16+)
        try {
            $this->db->query(
                'ALTER TABLE purchase_order_lines 
                 ADD CONSTRAINT chk_qty_received_valid 
                 CHECK (qty_received >= 0 AND qty_received <= qty)'
            );
            log_message('info', 'CHECK constraint added to purchase_order_lines.qty_received');
        } catch (\Exception $e) {
            log_message('warning', 'Could not add CHECK constraint (MySQL version may not support it): ' . $e->getMessage());
            log_message('info', 'Validation will be enforced at application level instead');
        }

        // Add index for performance on qty_received queries
        $indexExists = false;
        try {
            $row = $this->db->query("SHOW INDEX FROM purchase_order_lines WHERE Key_name = 'idx_qty_received'")->getRowArray();
            $indexExists = !empty($row);
        } catch (\Throwable $_) {
            $indexExists = false;
        }

        if (! $indexExists) {
            $this->db->query('CREATE INDEX idx_qty_received ON purchase_order_lines(qty_received)');
        }
    }

    public function down()
    {
        // Drop CHECK constraint
        try {
            $this->db->query('ALTER TABLE purchase_order_lines DROP CONSTRAINT chk_qty_received_valid');
        } catch (\Exception $e) {
            // Constraint may not exist
        }

        // Drop index
        try {
            $this->db->query('DROP INDEX idx_qty_received ON purchase_order_lines');
        } catch (\Exception $e) {
            // Index may not exist
        }
    }
}
