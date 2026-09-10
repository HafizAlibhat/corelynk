<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Drop-off points created before locations carried a vendor_id were named after
 * the vendor ("<vendor name> - <setup> - <area>"), so they can be re-attached by
 * that prefix. Only locations in the vendor-processing warehouse are touched —
 * our own storage locations must stay vendor-less.
 */
class BackfillVendorWarehouseLocations extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        if (! $db->tableExists('warehouse_locations') || ! $db->tableExists('vendors')) {
            return;
        }
        if (! in_array('vendor_id', $db->getFieldNames('warehouse_locations'), true)) {
            return;
        }

        $db->query(
            "UPDATE warehouse_locations wl
             JOIN warehouses w ON w.id = wl.warehouse_id
             JOIN vendors v ON wl.name LIKE CONCAT(CONVERT(v.name USING utf8mb4) COLLATE utf8mb4_unicode_ci, '%')
             SET wl.vendor_id = v.id
             WHERE wl.vendor_id IS NULL
               AND (w.code = 'VENDOR-PROC' OR w.name LIKE '%vendor%')"
        );
    }

    public function down()
    {
        // Nothing to undo: the column drop in the previous migration removes the data.
    }
}
