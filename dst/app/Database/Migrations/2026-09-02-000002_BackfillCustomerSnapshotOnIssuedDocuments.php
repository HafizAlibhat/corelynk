<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Freeze the documents that were already issued before snapshots existed.
 *
 * These rows currently render the live customer address, so a profile edit
 * still rewrites them. Stamping them with the address they render *today*
 * changes nothing on screen and stops them drifting from here on.
 *
 * Only documents that are out of the door are stamped: every issued invoice,
 * orders past draft, and converted quotations. Anything still being edited
 * keeps following the customer until it is issued or explicitly refreshed.
 * Customers with no address on file are skipped so nothing renders blank.
 */
class BackfillCustomerSnapshotOnIssuedDocuments extends Migration
{
    public function up()
    {
        helper('customer_snapshot');

        $targets = [
            'customer_invoices' => null,
            'sales_orders'      => "TRIM(COALESCE(status, '')) NOT IN ('', 'draft')",
            'quotations'        => "TRIM(COALESCE(status, '')) = 'converted' OR converted_to_sales_order_id IS NOT NULL",
        ];

        $snapshots = [];

        foreach ($targets as $table => $condition) {
            if (! $this->db->tableExists($table)) {
                continue;
            }
            if (! in_array('customer_snapshot', $this->db->getFieldNames($table), true)) {
                continue;
            }

            $builder = $this->db->table($table)->select('id, customer_id')->where('customer_snapshot', null);
            if ($condition !== null) {
                $builder->where('(' . $condition . ')');
            }

            foreach ($builder->get()->getResultArray() as $row) {
                $customerId = (int) ($row['customer_id'] ?? 0);
                if ($customerId <= 0) {
                    continue;
                }

                if (! array_key_exists($customerId, $snapshots)) {
                    $snap = customer_snapshot_capture($customerId);
                    $hasAddress = ! empty($snap['line1']) || ! empty($snap['line2']) || ! empty($snap['city_name']);
                    $snapshots[$customerId] = $hasAddress ? json_encode($snap) : null;
                }

                if ($snapshots[$customerId] === null) {
                    continue;
                }

                $this->db->table($table)
                    ->where('id', (int) $row['id'])
                    ->update(['customer_snapshot' => $snapshots[$customerId]]);
            }
        }
    }

    public function down()
    {
        // The backfill only wrote rows that were NULL; clearing them restores the
        // pre-migration state for documents that were never refreshed since.
        foreach (['customer_invoices', 'sales_orders', 'quotations'] as $table) {
            if ($this->db->tableExists($table) && in_array('customer_snapshot', $this->db->getFieldNames($table), true)) {
                $this->db->table($table)->where('customer_snapshot IS NOT NULL')->update(['customer_snapshot' => null]);
            }
        }
    }
}
