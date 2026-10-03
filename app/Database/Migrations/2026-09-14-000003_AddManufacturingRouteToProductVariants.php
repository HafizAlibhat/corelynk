<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddManufacturingRouteToProductVariants extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        if (! $db->tableExists('product_variants')) {
            return;
        }

        $cols = $db->getFieldNames('product_variants');
        if (! in_array('manufacturing_route', $cols, true)) {
            $this->forge->addColumn('product_variants', [
                'manufacturing_route' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'default'    => 'buy',
                    'null'       => false,
                    'after'      => 'vendor_currency',
                ],
            ]);
        }

        if ($db->tableExists('products') && in_array('manufacturing_route', $db->getFieldNames('products'), true)) {
            $db->query(
                'UPDATE product_variants pv
                 JOIN products p ON p.id = pv.product_id
                 SET pv.manufacturing_route = COALESCE(NULLIF(p.manufacturing_route, \'\'), ?)',
                ['buy']
            );
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        if ($db->tableExists('product_variants') && in_array('manufacturing_route', $db->getFieldNames('product_variants'), true)) {
            $this->forge->dropColumn('product_variants', 'manufacturing_route');
        }
    }
}
