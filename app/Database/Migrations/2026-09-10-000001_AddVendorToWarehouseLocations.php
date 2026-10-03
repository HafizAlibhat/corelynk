<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A vendor drop-off point is a place that belongs to one vendor — a branch, an
 * office, a working setup. Without this link the "send to vendor" picker had to
 * offer every warehouse location we own, so our own storage boxes showed up as
 * valid vendor destinations. Tagging the location with its vendor lets the
 * picker show only that vendor's places, and lets users add more of them.
 */
class AddVendorToWarehouseLocations extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        if (! $db->tableExists('warehouse_locations')) {
            return;
        }

        if (! in_array('vendor_id', $db->getFieldNames('warehouse_locations'), true)) {
            $this->forge->addColumn('warehouse_locations', [
                'vendor_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'warehouse_id',
                ],
            ]);
            $db->query('CREATE INDEX idx_warehouse_locations_vendor ON warehouse_locations (vendor_id)');
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        if ($db->tableExists('warehouse_locations') && in_array('vendor_id', $db->getFieldNames('warehouse_locations'), true)) {
            $this->forge->dropColumn('warehouse_locations', 'vendor_id');
        }
    }
}
