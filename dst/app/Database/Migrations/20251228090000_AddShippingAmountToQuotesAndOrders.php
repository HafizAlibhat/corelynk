<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddShippingAmountToQuotesAndOrders extends Migration
{
    public function up()
    {
        // quotations.shipping_amount
        if (! $this->db->fieldExists('shipping_amount', 'quotations')) {
            $after = null;
            try {
                $fields = $this->db->getFieldNames('quotations');
                if (in_array('tax_total', $fields)) $after = 'tax_total';
                elseif (in_array('tax', $fields)) $after = 'tax';
                elseif (in_array('subtotal', $fields)) $after = 'subtotal';
            } catch (\Throwable $_) {
                $after = null;
            }
            $this->forge->addColumn('quotations', [
                'shipping_amount' => [
                    'type' => 'DECIMAL',
                    'constraint' => '15,2',
                    'default' => '0.00',
                    // place near tax/total fields when possible
                    'after' => $after ?: 'total'
                ]
            ]);

            // Backfill from legacy column if it exists
            try {
                if ($this->db->fieldExists('shipping_cost', 'quotations')) {
                    $this->db->query("UPDATE quotations SET shipping_amount = COALESCE(shipping_cost, 0) WHERE (shipping_amount IS NULL OR shipping_amount = 0) AND shipping_cost IS NOT NULL");
                }
            } catch (\Throwable $_) {
                // best-effort backfill
            }
        }

        // sales_orders.shipping_amount
        if (! $this->db->fieldExists('shipping_amount', 'sales_orders')) {
            $after = null;
            try {
                $fields = $this->db->getFieldNames('sales_orders');
                if (in_array('tax_total', $fields)) $after = 'tax_total';
                elseif (in_array('tax', $fields)) $after = 'tax';
                elseif (in_array('subtotal', $fields)) $after = 'subtotal';
            } catch (\Throwable $_) {
                $after = null;
            }
            $this->forge->addColumn('sales_orders', [
                'shipping_amount' => [
                    'type' => 'DECIMAL',
                    'constraint' => '15,2',
                    'default' => '0.00',
                    'after' => $after ?: 'total'
                ]
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('shipping_amount', 'quotations')) {
            $this->forge->dropColumn('quotations', 'shipping_amount');
        }
        if ($this->db->fieldExists('shipping_amount', 'sales_orders')) {
            $this->forge->dropColumn('sales_orders', 'shipping_amount');
        }
    }
}