<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateProductsAddPricingColumns extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        $forge = \Config\Database::forge(true);

        // Only proceed if products table exists
        if (! $db->tableExists('products')) {
            return;
        }

        // Add product columns if they don't exist
        $fields = [];
        if (! $db->fieldExists('barcode', 'products')) {
            $fields['barcode'] = ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true];
        }
        if (! $db->fieldExists('sku', 'products')) {
            $fields['sku'] = ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true];
        }
        if (! $db->fieldExists('weight', 'products')) {
            $fields['weight'] = ['type' => 'DECIMAL', 'constraint' => '10,3', 'default' => 0];
        }
        if (! $db->fieldExists('vendor_id', 'products')) {
            $fields['vendor_id'] = ['type' => 'INT', 'null' => true];
        }
        if (! $db->fieldExists('cost_price', 'products')) {
            $fields['cost_price'] = ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0];
        }
        if (! $db->fieldExists('cost_currency', 'products')) {
            $fields['cost_currency'] = ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'USD'];
        }
        if (! $db->fieldExists('sale_price', 'products')) {
            $fields['sale_price'] = ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0];
        }
        if (! $db->fieldExists('sale_currency', 'products')) {
            $fields['sale_currency'] = ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'USD'];
        }

        if (! empty($fields)) {
            $forge->addColumn('products', $fields);
        }

        // Add indexes if not present (try-catch because forge doesn't expose indexExists conveniently)
        try {
            $forge->addKey('barcode');
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            $forge->addKey('sku');
        } catch (\Throwable $e) {
        }
        try {
            $forge->addKey('vendor_id');
        } catch (\Throwable $e) {
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        $forge = \Config\Database::forge(true);
        if (! $db->tableExists('products')) {
            return;
        }

        $cols = ['barcode','sku','weight','vendor_id','cost_price','cost_currency','sale_price','sale_currency'];
        foreach ($cols as $c) {
            if ($db->fieldExists($c, 'products')) {
                $forge->dropColumn('products', $c);
            }
        }
    }
}
