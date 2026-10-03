<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddVariantToVendorSendNoteItems extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('vendor_send_note_items') && ! $this->db->fieldExists('variant_id', 'vendor_send_note_items')) {
            $this->forge->addColumn('vendor_send_note_items', [
                'variant_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'product_id'],
            ]);
            $this->db->query('CREATE INDEX idx_vendor_send_note_items_variant ON vendor_send_note_items (variant_id)');
        }
    }

    public function down()
    {
        if ($this->db->tableExists('vendor_send_note_items') && $this->db->fieldExists('variant_id', 'vendor_send_note_items')) {
            $this->forge->dropColumn('vendor_send_note_items', 'variant_id');
        }
    }
}
