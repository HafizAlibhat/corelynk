<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProductDetailedType extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        $existing = [];
        try {
            $existing = $db->getFieldNames('products');
        } catch (\Throwable $e) {
            // If products table does not exist yet, do nothing.
            return;
        }

        if (!in_array('detailed_type', $existing, true)) {
            $this->forge->addColumn('products', [
                'detailed_type' => [
                    'type' => 'VARCHAR',
                    'constraint' => 20,
                    'null' => false,
                    'default' => 'storable',
                ],
            ]);
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        try {
            $existing = $db->getFieldNames('products');
            if (in_array('detailed_type', $existing, true)) {
                $db->query("ALTER TABLE `products` DROP COLUMN `detailed_type`");
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
