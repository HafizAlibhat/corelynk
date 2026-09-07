<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePriceListItems extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        $forge = \Config\Database::forge(true);

        if (! $db->tableExists('price_list_items')) {
            $fields = [
                'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'price_list_id' => ['type' => 'INT', 'null' => false],
                'product_id' => ['type' => 'INT', 'null' => false],
                'special_price' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
                'currency' => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'USD'],
                'min_quantity' => ['type' => 'INT', 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ];

            $forge->addField($fields);
            $forge->addKey('id', true);
            $forge->addKey('price_list_id');
            $forge->addKey('product_id');
            $forge->createTable('price_list_items', true);

            // foreign keys if possible
            try {
                if ($db->tableExists('price_lists')) {
                    $forge->addForeignKey('price_list_id', 'price_lists', 'id', 'CASCADE', 'CASCADE');
                }
                if ($db->tableExists('products')) {
                    $forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'CASCADE');
                }
            } catch (\Throwable $e) {
                // ignore FK errors
            }
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        $forge = \Config\Database::forge(true);
        if ($db->tableExists('price_list_items')) {
            $forge->dropTable('price_list_items', true);
        }
    }
}
