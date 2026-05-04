<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateShippingServices extends Migration
{
    public function up()
    {
    $forge = \Config\Database::forge();
    $db = \Config\Database::connect();

    if (! $db->tableExists('shipping_services')) {
            $forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'carrier' => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => false],
                'service_name' => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => false],
                'min_weight' => ['type' => 'DECIMAL', 'constraint' => '10,3', 'default' => '0.000'],
                'base_rate' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => '0.00'],
                'rate_per_kg' => ['type' => 'DECIMAL', 'constraint' => '12,4', 'default' => '0.0000'],
                'currency' => ['type' => 'VARCHAR', 'constraint' => 8, 'default' => 'USD'],
                'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'metadata' => ['type' => 'TEXT', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('shipping_services', true);

            // seed some defaults if table empty
            $db = \Config\Database::connect();
            $count = (int)$db->table('shipping_services')->countAllResults(false);
            if ($count === 0) {
                $now = date('Y-m-d H:i:s');
                $data = [
                    ['carrier' => 'DHL', 'service_name' => 'Express', 'min_weight' => '0.500', 'base_rate' => '5.00', 'rate_per_kg' => '1.50', 'currency' => 'USD', 'active' => 1, 'created_at' => $now],
                    ['carrier' => 'DHL', 'service_name' => 'Economy', 'min_weight' => '0.200', 'base_rate' => '3.50', 'rate_per_kg' => '1.00', 'currency' => 'USD', 'active' => 1, 'created_at' => $now],
                    ['carrier' => 'UPS', 'service_name' => 'Ground', 'min_weight' => '0.500', 'base_rate' => '4.50', 'rate_per_kg' => '1.25', 'currency' => 'USD', 'active' => 1, 'created_at' => $now],
                    ['carrier' => 'FedEx', 'service_name' => 'Express', 'min_weight' => '0.500', 'base_rate' => '5.50', 'rate_per_kg' => '1.60', 'currency' => 'USD', 'active' => 1, 'created_at' => $now],
                    ['carrier' => 'DPD', 'service_name' => 'Standard', 'min_weight' => '0.200', 'base_rate' => '3.00', 'rate_per_kg' => '0.95', 'currency' => 'USD', 'active' => 1, 'created_at' => $now],
                    ['carrier' => 'Internal', 'service_name' => 'Internal', 'min_weight' => '0.000', 'base_rate' => '0.00', 'rate_per_kg' => '0.0000', 'currency' => 'USD', 'active' => 1, 'created_at' => $now],
                    ['carrier' => 'Other', 'service_name' => 'Manual', 'min_weight' => '0.000', 'base_rate' => '0.00', 'rate_per_kg' => '0.0000', 'currency' => 'USD', 'active' => 1, 'created_at' => $now],
                ];
                $db->table('shipping_services')->insertBatch($data);
            }
        }
    }

    public function down()
    {
        $forge = \Config\Database::forge();
        $db = \Config\Database::connect();
        if ($db->tableExists('shipping_services')) {
            $forge->dropTable('shipping_services', true);
        }
    }
}
