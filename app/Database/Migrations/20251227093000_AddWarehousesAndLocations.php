<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;

class AddWarehousesAndLocations extends Migration
{
    public function up()
    {
        $this->createWarehouses();
        $this->createWarehouseLocations();
        $this->alterStockBalances();
        $this->alterStockMovements();
    }

    public function down()
    {
        // rollback: drop foreign keys and columns, then tables
        $db = Database::connect();
        if ($db->tableExists('stock_movements')) {
            try { $db->query('ALTER TABLE stock_movements DROP FOREIGN KEY fk_stock_movements_warehouse'); } catch (\Throwable $_) {}
            try { $db->query('ALTER TABLE stock_movements DROP FOREIGN KEY fk_stock_movements_location'); } catch (\Throwable $_) {}
            try { $db->query('ALTER TABLE stock_movements DROP INDEX idx_sm_warehouse'); } catch (\Throwable $_) {}
            try { $db->query('ALTER TABLE stock_movements DROP COLUMN warehouse_id'); } catch (\Throwable $_) {}
        }
        if ($db->tableExists('stock_balances')) {
            try { $db->query('ALTER TABLE stock_balances DROP FOREIGN KEY fk_stock_balances_warehouse'); } catch (\Throwable $_) {}
            try { $db->query('ALTER TABLE stock_balances DROP FOREIGN KEY fk_stock_balances_location'); } catch (\Throwable $_) {}
            try { $db->query('ALTER TABLE stock_balances DROP INDEX product_warehouse_location'); } catch (\Throwable $_) {}
            try { $db->query('ALTER TABLE stock_balances ADD UNIQUE KEY product_location (product_id, location_id)'); } catch (\Throwable $_) {}
            try { $db->query('ALTER TABLE stock_balances DROP COLUMN warehouse_id'); } catch (\Throwable $_) {}
        }
        $this->forge->dropTable('warehouse_locations', true);
        $this->forge->dropTable('warehouses', true);
    }

    private function createWarehouses()
    {
        if ($this->db->tableExists('warehouses')) return;

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 191],
            'code'       => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('code');
        $this->forge->createTable('warehouses', true);

        // seed a default warehouse for existing data
        $db = Database::connect();
        $count = $db->table('warehouses')->countAll();
        if ($count === 0) {
            $db->table('warehouses')->insert([
                'name' => 'Main Warehouse',
                'code' => 'MAIN',
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function createWarehouseLocations()
    {
        if ($this->db->tableExists('warehouse_locations')) return;

        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'warehouse_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 191],
            'parent_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'is_active'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('warehouse_id');
        $this->forge->addKey('parent_id');
        $this->forge->addForeignKey('warehouse_id', 'warehouses', 'id', 'CASCADE', 'CASCADE', 'fk_wl_wh');
        $this->forge->addForeignKey('parent_id', 'warehouse_locations', 'id', 'CASCADE', 'SET NULL', 'fk_wl_parent');
        $this->forge->createTable('warehouse_locations', true);

        // Attempt to migrate legacy stock_locations into the default warehouse
        $db = Database::connect();
        $defaultWh = $db->table('warehouses')->orderBy('id', 'ASC')->get(1)->getRowArray();
        $defaultWhId = $defaultWh['id'] ?? 1;
        if ($db->tableExists('stock_locations')) {
            $rows = $db->table('stock_locations')->orderBy('id','ASC')->get()->getResultArray();
            if (!empty($rows)) {
                $map = [];
                // first pass insert with null parent
                foreach ($rows as $r) {
                    $db->table('warehouse_locations')->insert([
                        'warehouse_id' => $defaultWhId,
                        'name' => $r['name'] ?? ('Location '.$r['id']),
                        'parent_id' => null,
                        'is_active' => $r['is_active'] ?? 1,
                        'created_at' => $r['created_at'] ?? date('Y-m-d H:i:s'),
                        'updated_at' => $r['updated_at'] ?? date('Y-m-d H:i:s'),
                    ]);
                    $map[$r['id']] = $db->insertID();
                }
                // second pass update parent relationships
                foreach ($rows as $r) {
                    if (!empty($r['parent_id']) && isset($map[$r['id']]) && isset($map[$r['parent_id']])) {
                        $db->table('warehouse_locations')->where('id', $map[$r['id']])->update(['parent_id' => $map[$r['parent_id']]]);
                    }
                }
            }
        }
    }

    private function alterStockBalances()
    {
        if (!$this->db->tableExists('stock_balances')) return;
        $db = Database::connect();

        // add warehouse_id if missing
        $fields = $db->getFieldNames('stock_balances');
        if (!in_array('warehouse_id', $fields)) {
            $this->forge->addColumn('stock_balances', [
                'warehouse_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 1, 'after' => 'product_id'],
            ]);
            // set to default warehouse for existing rows
            $defaultWh = $db->table('warehouses')->orderBy('id','ASC')->get(1)->getRowArray();
            $defaultWhId = $defaultWh['id'] ?? 1;
            $db->table('stock_balances')->set('warehouse_id', $defaultWhId)->update();
        }

        // adjust unique key
        try { $db->query('ALTER TABLE stock_balances DROP INDEX product_id_location_id'); } catch (\Throwable $_) {}
        try { $db->query('ALTER TABLE stock_balances DROP INDEX product_location'); } catch (\Throwable $_) {}
        try { $db->query('ALTER TABLE stock_balances ADD UNIQUE KEY product_warehouse_location (product_id, warehouse_id, location_id)'); } catch (\Throwable $_) {}

        // add foreign keys
        try { $db->query('ALTER TABLE stock_balances ADD CONSTRAINT fk_stock_balances_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)'); } catch (\Throwable $_) {}
        try { $db->query('ALTER TABLE stock_balances ADD CONSTRAINT fk_stock_balances_location FOREIGN KEY (location_id) REFERENCES warehouse_locations(id)'); } catch (\Throwable $_) {}
    }

    private function alterStockMovements()
    {
        if (!$this->db->tableExists('stock_movements')) return;
        $db = Database::connect();

        $fields = $db->getFieldNames('stock_movements');
        if (!in_array('warehouse_id', $fields)) {
            $this->forge->addColumn('stock_movements', [
                'warehouse_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 1, 'after' => 'product_id'],
            ]);
            $defaultWh = $db->table('warehouses')->orderBy('id','ASC')->get(1)->getRowArray();
            $defaultWhId = $defaultWh['id'] ?? 1;
            $db->table('stock_movements')->set('warehouse_id', $defaultWhId)->update();
        }

        try { $db->query('ALTER TABLE stock_movements ADD INDEX idx_sm_warehouse (warehouse_id)'); } catch (\Throwable $_) {}
        try { $db->query('ALTER TABLE stock_movements ADD CONSTRAINT fk_stock_movements_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)'); } catch (\Throwable $_) {}
        try { $db->query('ALTER TABLE stock_movements ADD CONSTRAINT fk_stock_movements_location FOREIGN KEY (location_id) REFERENCES warehouse_locations(id)'); } catch (\Throwable $_) {}
    }
}
