<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddStatusToQuotations extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('status', 'quotations')) {
            // Add status column with default 'draft'
            $this->forge->addColumn('quotations', [
                'status' => [
                    'type'       => 'ENUM',
                    'constraint' => ['draft', 'quoted', 'accepted', 'cancelled'],
                    'default'    => 'draft',
                    'null'       => false,
                    'after'      => 'tax_total',
                ],
            ]);
        }

        // Set existing quotations to 'quoted' by default
        $this->db->table('quotations')->set('status', 'quoted')->update();
    }

    public function down()
    {
        if ($this->db->fieldExists('status', 'quotations')) {
            $this->forge->dropColumn('quotations', 'status');
        }
    }
}
