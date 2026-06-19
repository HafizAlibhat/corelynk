<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDocumentLineSectionsAndOrdering extends Migration
{
    private function addColumnsIfMissing(string $table): void
    {
        if (!$this->db->tableExists($table)) {
            return;
        }

        $fields = [];
        if (!$this->db->fieldExists('display_type', $table)) {
            $fields['display_type'] = [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => false,
                'default' => 'line',
            ];
        }
        if (!$this->db->fieldExists('section_title', $table)) {
            $fields['section_title'] = [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ];
        }
        if (!$this->db->fieldExists('sort_order', $table)) {
            $fields['sort_order'] = [
                'type' => 'INT',
                'constraint' => 11,
                'null' => false,
                'default' => 0,
            ];
        }
        if (!$this->db->fieldExists('updated_at', $table)) {
            $fields['updated_at'] = [
                'type' => 'DATETIME',
                'null' => true,
            ];
        }

        if (!empty($fields)) {
            $this->forge->addColumn($table, $fields);
        }

        if ($this->db->fieldExists('sort_order', $table)) {
            try {
                $this->db->query("UPDATE `{$table}` SET sort_order = id WHERE sort_order = 0 OR sort_order IS NULL");
            } catch (\Throwable $_) {
                // best effort
            }
        }
    }

    private function dropColumnsIfExists(string $table): void
    {
        if (!$this->db->tableExists($table)) {
            return;
        }

        foreach (['display_type', 'section_title', 'sort_order', 'updated_at'] as $col) {
            if ($this->db->fieldExists($col, $table)) {
                $this->forge->dropColumn($table, $col);
            }
        }
    }

    public function up()
    {
        $this->addColumnsIfMissing('quotation_lines');
        $this->addColumnsIfMissing('sales_order_lines');
        $this->addColumnsIfMissing('purchase_order_lines');
        $this->addColumnsIfMissing('purchase_rfq_lines');
    }

    public function down()
    {
        $this->dropColumnsIfExists('quotation_lines');
        $this->dropColumnsIfExists('sales_order_lines');
        $this->dropColumnsIfExists('purchase_order_lines');
        $this->dropColumnsIfExists('purchase_rfq_lines');
    }
}
