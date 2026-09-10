<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Lets one PO bundle several vendor_send_notes lots (colors, jobs) for the
 * same vendor, and flags a PO as subcontract job-work so the normal GRN
 * "receive against this PO" screen skips it entirely — those lots already
 * moved stock through the vendor receive flow, so a GRN receipt here would
 * double count them.
 */
class AddSubcontractJobPoLinking extends Migration
{
    public function up()
    {
        $this->forge->addColumn('purchase_orders', [
            'source_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
                'default'    => 'material',
                'after'      => 'processing_record_id',
            ],
        ]);

        $this->forge->addColumn('purchase_order_lines', [
            'vendor_send_note_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => false,
                'null'       => true,
                'after'      => 'po_id',
            ],
        ]);

        $this->db->query('CREATE INDEX idx_pol_vendor_send_note_id ON purchase_order_lines (vendor_send_note_id)');
        $this->db->query('CREATE INDEX idx_po_source_type ON purchase_orders (source_type)');
    }

    public function down()
    {
        $this->db->query('DROP INDEX idx_pol_vendor_send_note_id ON purchase_order_lines');
        $this->db->query('DROP INDEX idx_po_source_type ON purchase_orders');
        $this->forge->dropColumn('purchase_order_lines', 'vendor_send_note_id');
        $this->forge->dropColumn('purchase_orders', 'source_type');
    }
}
