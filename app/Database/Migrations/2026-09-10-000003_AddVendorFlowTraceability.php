<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Material sent for outsourced work rarely travels in a straight line: a vendor
 * runs out of time and the goods are collected and handed to another vendor, or
 * part of a finished batch fails QC and comes back to us for refinishing before
 * going out again. Each of those moves is a new send note, and without a link to
 * the note it came from the trail breaks — you can no longer answer "what became
 * of the 100 pcs we sent to Waqar?".
 *
 * - parent_send_note_id: the note this one was split off from (reroute / rework)
 * - origin: why it exists — sales_order | reroute | rework | resend
 * - qty_rerouted: qty pulled out of a note mid-flight, so the outstanding
 *   quantity owed by that vendor is (qty - received - rerouted)
 */
class AddVendorFlowTraceability extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        if (! $db->tableExists('vendor_send_notes')) {
            return;
        }

        $cols = $db->getFieldNames('vendor_send_notes');
        $add = [];

        if (! in_array('parent_send_note_id', $cols, true)) {
            $add['parent_send_note_id'] = [
                'type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'reference_no',
            ];
        }
        if (! in_array('origin', $cols, true)) {
            $add['origin'] = [
                'type' => 'VARCHAR', 'constraint' => 20, 'null' => false, 'default' => 'sales_order', 'after' => 'reference_no',
            ];
        }
        if (! in_array('qty_rerouted', $cols, true)) {
            $add['qty_rerouted'] = [
                'type' => 'DECIMAL', 'constraint' => '10,4', 'null' => false, 'default' => 0, 'after' => 'qty',
            ];
        }

        if ($add !== []) {
            $this->forge->addColumn('vendor_send_notes', $add);
        }

        if (! in_array('parent_send_note_id', $cols, true)) {
            $db->query('CREATE INDEX idx_vsn_parent ON vendor_send_notes (parent_send_note_id)');
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        if (! $db->tableExists('vendor_send_notes')) {
            return;
        }
        $cols = $db->getFieldNames('vendor_send_notes');
        foreach (['parent_send_note_id', 'origin', 'qty_rerouted'] as $col) {
            if (in_array($col, $cols, true)) {
                $this->forge->dropColumn('vendor_send_notes', $col);
            }
        }
    }
}
