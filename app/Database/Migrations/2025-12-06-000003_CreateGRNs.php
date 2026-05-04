<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class CreateGRNs extends Migration
{
    public function up()
    {
        // GRN header
        $this->forge->addField([
            'id' => ['type' => 'INT', 'auto_increment' => true],
            'po_id' => ['type' => 'INT', 'null' => true],
            'vendor_id' => ['type' => 'INT', 'null' => true],
            'grn_number' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'received_at' => ['type' => 'DATETIME', 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'INT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('po_id');
        $this->forge->createTable('grns', true);

        // GRN lines
        $this->forge->addField([
            'id' => ['type' => 'INT', 'auto_increment' => true],
            'grn_id' => ['type' => 'INT', 'null' => false],
            'po_line_id' => ['type' => 'INT', 'null' => true],
            'product_id' => ['type' => 'INT', 'null' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'qty_received' => ['type' => 'DECIMAL', 'constraint' => '15,3', 'default' => '0.000', 'null' => false],
            'unit_cost' => ['type' => 'DECIMAL', 'constraint' => '15,4', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('grn_id');
        $this->forge->addKey('product_id');
        $this->forge->createTable('grn_lines', true);
    }

    public function down()
    {
        if ($this->forge->tableExists('grn_lines')) {
            $this->forge->dropTable('grn_lines');
        }
        if ($this->forge->tableExists('grns')) {
            $this->forge->dropTable('grns');
        }
    }
}
