<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class CreateSubcontractIssues extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'auto_increment' => true],
            'issue_number' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'vendor_id' => ['type' => 'INT', 'null' => true],
            'issued_at' => ['type' => 'DATETIME', 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'INT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('subcontract_issues', true);

        $this->forge->addField([
            'id' => ['type' => 'INT', 'auto_increment' => true],
            'issue_id' => ['type' => 'INT', 'null' => false],
            'product_id' => ['type' => 'INT', 'null' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'quantity' => ['type' => 'DECIMAL', 'constraint' => '15,3', 'default' => '0.000', 'null' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('issue_id');
        $this->forge->createTable('subcontract_issue_lines', true);
    }

    public function down()
    {
        if ($this->forge->tableExists('subcontract_issue_lines')) $this->forge->dropTable('subcontract_issue_lines');
        if ($this->forge->tableExists('subcontract_issues')) $this->forge->dropTable('subcontract_issues');
    }
}
