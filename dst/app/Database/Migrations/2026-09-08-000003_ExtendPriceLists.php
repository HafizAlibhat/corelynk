<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Price lists become party-aware (customer or vendor) and rule-based:
 * a list can hold fixed prices per product, or derive prices from the
 * product cost with a margin, or discount the product base price.
 */
class ExtendPriceLists extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        if (! $db->tableExists('price_lists')) {
            return;
        }

        $cols = $db->getFieldNames('price_lists');
        $add  = [];
        if (! in_array('party_type', $cols, true)) {
            $add['party_type'] = ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'customer', 'after' => 'id'];
        }
        if (! in_array('vendor_id', $cols, true)) {
            $add['vendor_id'] = ['type' => 'INT', 'null' => true, 'after' => 'customer_id'];
        }
        if (! in_array('currency', $cols, true)) {
            $add['currency'] = ['type' => 'VARCHAR', 'constraint' => 3, 'null' => true, 'after' => 'name'];
        }
        if (! in_array('pricing_mode', $cols, true)) {
            $add['pricing_mode'] = ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'fixed', 'after' => 'currency'];
        }
        if (! in_array('margin_percent', $cols, true)) {
            $add['margin_percent'] = ['type' => 'DECIMAL', 'constraint' => '9,4', 'null' => true, 'after' => 'pricing_mode'];
        }
        if (! in_array('margin_method', $cols, true)) {
            $add['margin_method'] = ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'markup', 'after' => 'margin_percent'];
        }
        if ($add) {
            $this->forge->addColumn('price_lists', $add);
        }

        // A list can be company-wide (no customer, no vendor), so customer_id must allow NULL.
        try {
            $this->forge->modifyColumn('price_lists', [
                'customer_id' => ['name' => 'customer_id', 'type' => 'INT', 'null' => true],
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'price_lists.customer_id could not be made nullable: ' . $e->getMessage());
        }
        try {
            $db->query('UPDATE price_lists SET customer_id = NULL WHERE customer_id = 0');
        } catch (\Throwable $e) {
        }

        if ($db->tableExists('price_list_items')) {
            $icols = $db->getFieldNames('price_list_items');
            $iadd  = [];
            if (! in_array('pricing_mode', $icols, true)) {
                $iadd['pricing_mode'] = ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'product_id'];
            }
            if (! in_array('margin_percent', $icols, true)) {
                $iadd['margin_percent'] = ['type' => 'DECIMAL', 'constraint' => '9,4', 'null' => true, 'after' => 'special_price'];
            }
            if ($iadd) {
                $this->forge->addColumn('price_list_items', $iadd);
            }
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        foreach (['party_type', 'vendor_id', 'currency', 'pricing_mode', 'margin_percent', 'margin_method'] as $c) {
            if ($db->tableExists('price_lists') && in_array($c, $db->getFieldNames('price_lists'), true)) {
                $this->forge->dropColumn('price_lists', $c);
            }
        }
        foreach (['pricing_mode', 'margin_percent'] as $c) {
            if ($db->tableExists('price_list_items') && in_array($c, $db->getFieldNames('price_list_items'), true)) {
                $this->forge->dropColumn('price_list_items', $c);
            }
        }
    }
}
