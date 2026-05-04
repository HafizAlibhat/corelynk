<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddGrnPartialReceivingSupport extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        
        // 1. Add receive_status column to purchase_order_lines
        if ($this->db->tableExists('purchase_order_lines')) {
            if (!$this->db->fieldExists('receive_status', 'purchase_order_lines')) {
                $this->db->query("
                    ALTER TABLE purchase_order_lines 
                    ADD COLUMN receive_status VARCHAR(30) DEFAULT 'pending' 
                    COMMENT 'pending, partially_received, fully_received'
                ");
            }
            if (!$this->db->fieldExists('fully_received_date', 'purchase_order_lines')) {
                $this->db->query("
                    ALTER TABLE purchase_order_lines 
                    ADD COLUMN fully_received_date DATETIME NULL 
                    COMMENT 'When this line was fully received'
                ");
            }
        }

        // 2. Add warehouse_id and location_id to purchase_grn_lines (for per-product location tracking)
        if ($this->db->tableExists('purchase_grn_lines')) {
            if (!$this->db->fieldExists('warehouse_id', 'purchase_grn_lines')) {
                $this->db->query("
                    ALTER TABLE purchase_grn_lines 
                    ADD COLUMN warehouse_id INT NULL 
                    COMMENT 'Warehouse for this specific line (overrides GRN header)'
                ");
            }
            if (!$this->db->fieldExists('location_id', 'purchase_grn_lines')) {
                $this->db->query("
                    ALTER TABLE purchase_grn_lines 
                    ADD COLUMN location_id INT NULL 
                    COMMENT 'Location for this specific line (overrides GRN header)'
                ");
            }
            // Add indices for location queries
            if (!$this->db->tableExists('purchase_grn_lines')) {
                // Empty - table should exist already
            } else {
                // Create indices if they don't exist
                try {
                    $this->db->query("
                        ALTER TABLE purchase_grn_lines  
                        ADD KEY idx_warehouse_location (warehouse_id, location_id)
                    ");
                } catch (\Throwable $_) {
                    // Index might already exist
                }
            }
        }

        // 3. Create grn_receipt_history table for audit trail
        if (!$this->db->tableExists('grn_receipt_history')) {
            $this->db->query("
                CREATE TABLE grn_receipt_history (
                    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                    po_id INT UNSIGNED NOT NULL COMMENT 'Reference to purchase order',
                    po_line_id INT UNSIGNED NOT NULL COMMENT 'Reference to PO line',
                    grn_id INT UNSIGNED NOT NULL COMMENT 'Reference to GRN',
                    grn_line_id INT UNSIGNED COMMENT 'Reference to GRN line',
                    product_id INT UNSIGNED NOT NULL COMMENT 'Product received',
                    variant_id INT UNSIGNED NULL COMMENT 'Product variant if applicable',
                    unit_price DECIMAL(15,4) NULL COMMENT 'Unit price at time of receipt',
                    qty_ordered DECIMAL(15,4) COMMENT 'Total qty ordered on PO line',
                    qty_previously_received DECIMAL(15,4) COMMENT 'Qty received in previous GRNs',
                    qty_received_this_grn DECIMAL(15,4) COMMENT 'Qty received in this GRN',
                    warehouse_id INT UNSIGNED NULL COMMENT 'Warehouse location',
                    location_id INT UNSIGNED NULL COMMENT 'Location within warehouse',
                    received_date DATETIME COMMENT 'When received',
                    previous_grn_id INT UNSIGNED NULL COMMENT 'If this completes pending from earlier GRN',
                    notes TEXT NULL COMMENT 'Notes on this receipt (e.g., partial damage, over-received reason)',
                    created_by INT UNSIGNED COMMENT 'User who created receipt',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    
                    KEY idx_po (po_id),
                    KEY idx_po_line (po_line_id),
                    KEY idx_grn (grn_id),
                    KEY idx_product (product_id),
                    KEY idx_received_date (received_date),
                    UNIQUE KEY uniq_grn_line (grn_id, po_line_id),
                    CONSTRAINT fk_gnh_po FOREIGN KEY (po_id) REFERENCES purchase_orders(id),
                    CONSTRAINT fk_gnh_grn FOREIGN KEY (grn_id) REFERENCES purchase_grns(id),
                    CONSTRAINT fk_gnh_product FOREIGN KEY (product_id) REFERENCES products(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 
                COMMENT='Audit trail for GRN receipt history - tracks each partial receipt event'
            ");
        }

        // 4. Add indices for efficient pending qty queries
        if ($this->db->tableExists('purchase_order_lines')) {
            try {
                $this->db->query("
                    ALTER TABLE purchase_order_lines 
                    ADD KEY idx_receive_status (receive_status)
                ");
            } catch (\Throwable $_) {
                // Index might already exist
            }
        }
    }

    public function down()
    {
        $this->db->DBDebug = false;

        // Drop indices
        try {
            $this->db->query("ALTER TABLE purchase_order_lines DROP KEY idx_receive_status");
        } catch (\Throwable $_) {}

        try {
            $this->db->query("ALTER TABLE purchase_grn_lines DROP KEY idx_warehouse_location");
        } catch (\Throwable $_) {}

        // Drop columns
        if ($this->db->tableExists('purchase_order_lines')) {
            try {
                $this->db->query("ALTER TABLE purchase_order_lines DROP COLUMN fully_received_date");
            } catch (\Throwable $_) {}
            try {
                $this->db->query("ALTER TABLE purchase_order_lines DROP COLUMN receive_status");
            } catch (\Throwable $_) {}
        }

        if ($this->db->tableExists('purchase_grn_lines')) {
            try {
                $this->db->query("ALTER TABLE purchase_grn_lines DROP COLUMN location_id");
            } catch (\Throwable $_) {}
            try {
                $this->db->query("ALTER TABLE purchase_grn_lines DROP COLUMN warehouse_id");
            } catch (\Throwable $_) {}
        }

        // Drop table
        try {
            $this->db->query("DROP TABLE IF EXISTS grn_receipt_history");
        } catch (\Throwable $_) {}
    }
}
