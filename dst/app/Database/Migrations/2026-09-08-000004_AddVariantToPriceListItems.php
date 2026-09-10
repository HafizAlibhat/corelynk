<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A price list line can target one product variant (size/colour/thickness)
 * instead of the whole product. NULL variant_id keeps the old meaning:
 * the price applies to every variant of the product.
 */
class AddVariantToPriceListItems extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        if (! $db->tableExists('price_list_items')) {
            return;
        }
        if (in_array('variant_id', $db->getFieldNames('price_list_items'), true)) {
            return;
        }

        $this->forge->addColumn('price_list_items', [
            'variant_id' => ['type' => 'INT', 'null' => true, 'after' => 'product_id'],
        ]);
        $db->query('CREATE INDEX idx_pli_variant ON price_list_items (variant_id)');
    }

    public function down()
    {
        $db = \Config\Database::connect();
        if ($db->tableExists('price_list_items') && in_array('variant_id', $db->getFieldNames('price_list_items'), true)) {
            $this->forge->dropColumn('price_list_items', 'variant_id');
        }
    }
}
