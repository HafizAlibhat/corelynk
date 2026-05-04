<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;

class AddVariantStockToWarehouseTables extends Migration
{
    public function up()
    {
        $db = Database::connect();

        if ($db->tableExists('stock_balances')) {
            $this->alterStockBalances($db);
        }

        if ($db->tableExists('stock_movements')) {
            $this->alterStockMovements($db);
        }
    }

    private function alterStockBalances($db): void
    {
        $fields = $db->getFieldNames('stock_balances');

        if (!in_array('variant_id', $fields, true)) {
            $this->forge->addColumn('stock_balances', [
                'variant_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'default' => null, 'after' => 'product_id'],
            ]);
        }

        if (!in_array('item_key', $fields, true)) {
            $this->forge->addColumn('stock_balances', [
                'item_key' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true, 'default' => null, 'after' => (in_array('variant_id', $db->getFieldNames('stock_balances'), true) ? 'variant_id' : 'product_id')],
            ]);
        }

        // Backfill item_key for existing rows
        try {
            $cols = array_flip($db->getFieldNames('stock_balances'));
            if (isset($cols['item_key'])) {
                // Prefer variant_id if present, else product_id
                $db->query("UPDATE stock_balances SET item_key = CONCAT('p', product_id) WHERE (item_key IS NULL OR item_key='')");
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Replace unique index to support multiple variants per template
        try { $db->query('ALTER TABLE stock_balances DROP INDEX product_warehouse_location'); } catch (\Throwable $_) {}
        try { $db->query('ALTER TABLE stock_balances DROP INDEX product_location'); } catch (\Throwable $_) {}
        try { $db->query('ALTER TABLE stock_balances DROP INDEX product_id_location_id'); } catch (\Throwable $_) {}

        // Ensure helpful indexes
        try { $db->query('ALTER TABLE stock_balances ADD INDEX idx_sb_variant (variant_id)'); } catch (\Throwable $_) {}
        try { $db->query('ALTER TABLE stock_balances ADD INDEX idx_sb_itemkey (item_key)'); } catch (\Throwable $_) {}

        // Unique row per (warehouse, location, item_key)
        try { $db->query('ALTER TABLE stock_balances ADD UNIQUE KEY ux_sb_item_wh_loc (item_key, warehouse_id, location_id)'); } catch (\Throwable $_) {}
    }

    private function alterStockMovements($db): void
    {
        $fields = $db->getFieldNames('stock_movements');

        if (!in_array('variant_id', $fields, true)) {
            $this->forge->addColumn('stock_movements', [
                'variant_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'default' => null, 'after' => 'product_id'],
            ]);
        }

        if (!in_array('item_key', $fields, true)) {
            $this->forge->addColumn('stock_movements', [
                'item_key' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true, 'default' => null, 'after' => (in_array('variant_id', $db->getFieldNames('stock_movements'), true) ? 'variant_id' : 'product_id')],
            ]);
        }

        // Backfill item_key for existing rows
        try {
            $cols = array_flip($db->getFieldNames('stock_movements'));
            if (isset($cols['item_key'])) {
                $db->query("UPDATE stock_movements SET item_key = CONCAT('p', product_id) WHERE (item_key IS NULL OR item_key='')");
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Indexes
        try { $db->query('ALTER TABLE stock_movements ADD INDEX idx_sm_variant (variant_id)'); } catch (\Throwable $_) {}
        try { $db->query('ALTER TABLE stock_movements ADD INDEX idx_sm_itemkey (item_key)'); } catch (\Throwable $_) {}
    }

    public function down()
    {
        $db = Database::connect();

        if ($db->tableExists('stock_balances')) {
            try { $db->query('ALTER TABLE stock_balances DROP INDEX ux_sb_item_wh_loc'); } catch (\Throwable $_) {}
            try { $db->query('ALTER TABLE stock_balances DROP INDEX idx_sb_itemkey'); } catch (\Throwable $_) {}
            try { $db->query('ALTER TABLE stock_balances DROP INDEX idx_sb_variant'); } catch (\Throwable $_) {}

            // best-effort restore old unique
            try { $db->query('ALTER TABLE stock_balances ADD UNIQUE KEY product_warehouse_location (product_id, warehouse_id, location_id)'); } catch (\Throwable $_) {}

            try { $db->query('ALTER TABLE stock_balances DROP COLUMN item_key'); } catch (\Throwable $_) {}
            try { $db->query('ALTER TABLE stock_balances DROP COLUMN variant_id'); } catch (\Throwable $_) {}
        }

        if ($db->tableExists('stock_movements')) {
            try { $db->query('ALTER TABLE stock_movements DROP INDEX idx_sm_itemkey'); } catch (\Throwable $_) {}
            try { $db->query('ALTER TABLE stock_movements DROP INDEX idx_sm_variant'); } catch (\Throwable $_) {}
            try { $db->query('ALTER TABLE stock_movements DROP COLUMN item_key'); } catch (\Throwable $_) {}
            try { $db->query('ALTER TABLE stock_movements DROP COLUMN variant_id'); } catch (\Throwable $_) {}
        }
    }
}
