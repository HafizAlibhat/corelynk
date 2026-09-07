<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProcessResponsibility extends Migration
{
    public function up()
    {
        // Add responsibility columns to processes table (defensive checks)
        if (!$this->db->fieldExists('responsibility_mode', 'processes')) {
            $this->forge->addColumn('processes', [
                'responsibility_mode' => [
                    'type' => 'VARCHAR',
                    'constraint' => 20,
                    'null' => true,
                    'after' => 'standard_time_minutes'
                ],
                'responsibility_department' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'null' => true,
                    'after' => 'responsibility_mode'
                ],
            ]);
        }

        // Pivot table for process -> employees assignments
        if (!$this->db->tableExists('process_employee_assignments')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'process_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                ],
                'employee_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('process_id');
            $this->forge->addKey('employee_id');
            $this->forge->createTable('process_employee_assignments');
        }
    }

    public function down()
    {
        // Drop pivot table
        if ($this->db->tableExists('process_employee_assignments')) {
            $this->forge->dropTable('process_employee_assignments');
        }
        // Leave columns in place (safe down). Uncomment to remove if desired.
        // if ($this->db->fieldExists('responsibility_mode','processes')) {
        //     $this->forge->dropColumn('processes','responsibility_mode');
        // }
        // if ($this->db->fieldExists('responsibility_department','processes')) {
        //     $this->forge->dropColumn('processes','responsibility_department');
        // }
    }
}