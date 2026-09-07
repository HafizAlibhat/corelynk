<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUnitPriceToVendorSendNoteItems extends Migration
{
    public function up()
    {
        try {
            if ($this->db->tableExists('vendor_send_note_items')) {
                $fields = [
                    'unit_price' => [
                        'type' => 'DECIMAL',
                        'constraint' => '18,4',
                        'null' => true,
                        'default' => null,
                    ],
                ];
                $this->forge->addColumn('vendor_send_note_items', $fields);
            }
        } catch (\Throwable $_) {
            // best-effort migration
        }
    }

    public function down()
    {
        try {
            if ($this->db->tableExists('vendor_send_note_items')) {
                $this->forge->dropColumn('vendor_send_note_items', 'unit_price');
            }
        } catch (\Throwable $_) {
            // ignore
        }
    }
}
