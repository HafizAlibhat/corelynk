<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPdfLogoVisibilitySettings extends Migration
{
    private array $columns = [
        'pdf_inv_hide_logo',
        'pdf_quote_hide_logo',
        'pdf_so_hide_logo',
        'pdf_po_hide_logo',
        'pdf_rfq_hide_logo',
    ];

    public function up()
    {
        if (! $this->db->tableExists('company_settings')) {
            return;
        }

        foreach ($this->columns as $column) {
            if (! $this->db->fieldExists($column, 'company_settings')) {
                $this->forge->addColumn('company_settings', [
                    $column => [
                        'type' => 'TINYINT',
                        'constraint' => 1,
                        'default' => 0,
                        'null' => false,
                    ],
                ]);
            }
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('company_settings')) {
            return;
        }

        foreach (array_reverse($this->columns) as $column) {
            if ($this->db->fieldExists($column, 'company_settings')) {
                $this->forge->dropColumn('company_settings', $column);
            }
        }
    }
}
