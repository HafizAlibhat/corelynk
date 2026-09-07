<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class AddProductStockAndVendors extends Migration
{
    public function up()
    {
        // Add product stock columns if they don't already exist (safe, idempotent)
    $db = \Config\Database::connect();
        $existing = [];
        try {
            $existing = $db->getFieldNames('products');
    } catch (\Throwable $e) {
            // table might not exist yet; leave $existing empty which will cause addColumn to run and fail later if table is missing
        }

        if (!in_array('current_stock', $existing)) {
            $this->forge->addColumn('products', [
                'current_stock' => ['type' => 'DECIMAL', 'constraint' => '15,3', 'default' => '0.000', 'null' => false],
            ]);
        }

        if (!in_array('unit_cost', $existing)) {
            $this->forge->addColumn('products', [
                'unit_cost' => ['type' => 'DECIMAL', 'constraint' => '15,4', 'default' => '0.0000', 'null' => false],
            ]);
        }

        // product_vendors pivot
        $this->forge->addField([
            'id' => ['type' => 'INT', 'auto_increment' => true],
            'product_id' => ['type' => 'INT', 'null' => false],
            'vendor_id'  => ['type' => 'INT', 'null' => false],
            'vendor_product_code' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'lead_time_days' => ['type' => 'INT', 'null' => true, 'default' => null],
            'last_cost' => ['type' => 'DECIMAL', 'constraint' => '15,4', 'null' => true, 'default' => null],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('product_id');
        $this->forge->addKey('vendor_id');
        $this->forge->createTable('product_vendors', true);

        // product stock transactions
        $this->forge->addField([
            'id' => ['type' => 'INT', 'auto_increment' => true],
            'product_id' => ['type' => 'INT', 'null' => false],
            'transaction_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => false],
            'quantity' => ['type' => 'DECIMAL', 'constraint' => '15,3', 'default' => '0.000', 'null' => false],
            'unit_cost' => ['type' => 'DECIMAL', 'constraint' => '15,4', 'null' => true],
            'reference_type' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'reference_id' => ['type' => 'INT', 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'INT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('product_id');
        $this->forge->createTable('product_stock_transactions', true);
    }

    public function down()
    {
        // Rollback: drop tables and columns added above
        if ($this->forge->tableExists('product_stock_transactions')) {
            $this->forge->dropTable('product_stock_transactions');
        }
        if ($this->forge->tableExists('product_vendors')) {
            $this->forge->dropTable('product_vendors');
        }
        // drop columns if exist
        $db = \Config\Database::connect();
        try {
            $db->query("ALTER TABLE `products` DROP COLUMN `current_stock`");
            $db->query("ALTER TABLE `products` DROP COLUMN `unit_cost`");
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
