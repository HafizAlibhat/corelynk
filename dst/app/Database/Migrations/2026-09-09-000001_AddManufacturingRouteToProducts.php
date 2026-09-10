<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Products get an Odoo-style supply route: 'buy' (purchased from a vendor) or
 * 'manufacture' (produced in-house through a preparation profile). Only 'buy'
 * products require a vendor before auto RFQ generation — a manufactured item
 * is supplied by us, so demanding a vendor on it blocks the RFQ run wrongly.
 */
class AddManufacturingRouteToProducts extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        if (! $db->tableExists('products')) {
            return;
        }

        $cols = $db->getFieldNames('products');
        if (! in_array('manufacturing_route', $cols, true)) {
            $this->forge->addColumn('products', [
                'manufacturing_route' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'default'    => 'buy',
                    'null'       => false,
                    'after'      => 'detailed_type',
                ],
            ]);
        }

        // Existing products that already have an active preparation profile are
        // manufactured by definition — backfill so nothing needs re-entering.
        if ($db->tableExists('preparation_profiles')) {
            $db->query(
                'UPDATE products p
                 SET p.manufacturing_route = ?
                 WHERE EXISTS (
                     SELECT 1 FROM preparation_profiles pp
                     WHERE pp.product_id = p.id AND pp.is_active = 1
                 )',
                ['manufacture']
            );
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        if ($db->tableExists('products') && in_array('manufacturing_route', $db->getFieldNames('products'), true)) {
            $this->forge->dropColumn('products', 'manufacturing_route');
        }
    }
}
