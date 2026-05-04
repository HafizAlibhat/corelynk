<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProductTypeAndAttributes extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        $forge = \Config\Database::forge();

        if ($db->tableExists('products')) {
            // Add product_type if missing
            if (! $db->fieldExists('product_type', 'products')) {
                $fields = [
                    'product_type' => [
                        'type' => 'VARCHAR',
                        'constraint' => 20,
                        'default' => 'simple',
                        'null' => false,
                    ]
                ];
                $forge->addColumn('products', $fields);
            }

            // Add attributes_definitions if missing (JSON stored as TEXT)
            if (! $db->fieldExists('attributes_definitions', 'products')) {
                $fields = [
                    'attributes_definitions' => [
                        'type' => 'TEXT',
                        'null' => true,
                    ]
                ];
                $forge->addColumn('products', $fields);
            }
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        $forge = \Config\Database::forge();

        if ($db->tableExists('products')) {
            if ($db->fieldExists('attributes_definitions', 'products')) {
                $forge->dropColumn('products', 'attributes_definitions');
            }
            if ($db->fieldExists('product_type', 'products')) {
                $forge->dropColumn('products', 'product_type');
            }
        }
    }
}
