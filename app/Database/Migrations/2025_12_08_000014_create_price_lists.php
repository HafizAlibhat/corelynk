<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePriceLists extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        $forge = \Config\Database::forge(true);

        // Create price_lists table
        if (! $db->tableExists('price_lists')) {
            $fields = [
                'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'customer_id' => ['type' => 'INT', 'null' => false],
                'name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'valid_from' => ['type' => 'DATE', 'null' => true],
                'valid_to' => ['type' => 'DATE', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'created_by' => ['type' => 'INT', 'null' => true],
            ];

            $forge->addField($fields);
            $forge->addKey('id', true);
            $forge->addKey('customer_id');
            $forge->createTable('price_lists', true);

            // add foreign key if customers table exists
            if ($db->tableExists('customers')) {
                try {
                    $forge->addForeignKey('customer_id', 'customers', 'id', 'CASCADE', 'CASCADE');
                } catch (\Throwable $e) {
                    // Some DB drivers may not support adding FK here; ignore if fails
                }
            }
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        $forge = \Config\Database::forge(true);
        if ($db->tableExists('price_lists')) {
            $forge->dropTable('price_lists', true);
        }
    }
}
