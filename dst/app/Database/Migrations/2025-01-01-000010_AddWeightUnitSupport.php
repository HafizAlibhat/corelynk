<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddWeightUnitSupport extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        
        // Add weight_unit to products table if it doesn't exist
        if (!$db->fieldExists('weight_unit', 'products')) {
            $this->forge->addColumn('products', [
                'weight_unit' => [
                    'type' => 'VARCHAR',
                    'constraint' => 10,
                    'default' => 'kg',
                    'comment' => 'Unit of weight: kg, g, lbs, oz',
                ]
            ]);
        }
        
        // Add weight_unit to quotation_lines table if it doesn't exist
        if (!$db->fieldExists('weight_unit', 'quotation_lines')) {
            $this->forge->addColumn('quotation_lines', [
                'weight_unit' => [
                    'type' => 'VARCHAR',
                    'constraint' => 10,
                    'default' => 'kg',
                    'comment' => 'Unit of weight: kg, g, lbs, oz',
                ]
            ]);
        }
        
        // Add weight_unit to sales_order_lines table if it doesn't exist
        if ($db->tableExists('sales_order_lines') && !$db->fieldExists('weight_unit', 'sales_order_lines')) {
            $this->forge->addColumn('sales_order_lines', [
                'weight_unit' => [
                    'type' => 'VARCHAR',
                    'constraint' => 10,
                    'default' => 'kg',
                    'comment' => 'Unit of weight: kg, g, lbs, oz',
                ]
            ]);
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        
        if ($db->fieldExists('weight_unit', 'products')) {
            $this->forge->dropColumn('products', 'weight_unit');
        }
        
        if ($db->fieldExists('weight_unit', 'quotation_lines')) {
            $this->forge->dropColumn('quotation_lines', 'weight_unit');
        }
        
        if ($db->tableExists('sales_order_lines') && $db->fieldExists('weight_unit', 'sales_order_lines')) {
            $this->forge->dropColumn('sales_order_lines', 'weight_unit');
        }
    }
}
