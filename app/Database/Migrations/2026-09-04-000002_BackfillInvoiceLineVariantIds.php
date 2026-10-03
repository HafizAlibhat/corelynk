<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Invoices created from a sales order used to drop the line's
 * product_variant_id, so variant products printed with no product code on the
 * PDF. The generator has been fixed; this repairs the invoices already issued.
 *
 * A line is only repaired when exactly one sales order line matches it on
 * product, quantity and unit price — an ambiguous match is left alone rather
 * than guessing which variant the customer bought.
 */
class BackfillInvoiceLineVariantIds extends Migration
{
    public function up()
    {
        $db = $this->db;

        if (! $db->tableExists('customer_invoice_lines') || ! $db->tableExists('sales_order_lines')) {
            return;
        }

        $lineCols = $db->getFieldNames('customer_invoice_lines');
        $invCols  = $db->getFieldNames('customer_invoices');
        if (! in_array('product_variant_id', $lineCols, true) || ! in_array('sales_order_id', $invCols, true)) {
            return;
        }

        $orphans = $db->table('customer_invoice_lines cil')
            ->select('cil.id, cil.product_id, cil.quantity, cil.unit_price, ci.sales_order_id')
            ->join('customer_invoices ci', 'ci.id = cil.invoice_id', 'inner')
            ->where('cil.product_variant_id IS NULL', null, false)
            ->where('cil.product_id IS NOT NULL', null, false)
            ->where('ci.sales_order_id IS NOT NULL', null, false)
            ->get()
            ->getResultArray();

        foreach ($orphans as $line) {
            $matches = $db->table('sales_order_lines')
                ->select('product_variant_id')
                ->where('sales_order_id', (int) $line['sales_order_id'])
                ->where('product_id', (int) $line['product_id'])
                ->where('quantity', $line['quantity'])
                ->where('unit_price', $line['unit_price'])
                ->get()
                ->getResultArray();

            if (count($matches) !== 1 || empty($matches[0]['product_variant_id'])) {
                continue;
            }

            $db->table('customer_invoice_lines')
                ->where('id', (int) $line['id'])
                ->update(['product_variant_id' => (int) $matches[0]['product_variant_id']]);
        }
    }

    public function down()
    {
        // Restoring the missing data would be the bug, so this is a no-op.
    }
}
