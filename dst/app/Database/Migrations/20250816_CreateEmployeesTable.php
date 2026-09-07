<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEmployeesTable extends Migration
{
    public function up()
    {
        // Employees table
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'employee_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'unique'     => true,
            ],
            'first_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'last_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'phone' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'department' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);
        
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('employee_code');
        $this->forge->createTable('employees');

        // Employee skills/tasks table
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'employee_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'skill_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'proficiency_level' => [
                'type'       => 'ENUM',
                'constraint' => ['basic', 'intermediate', 'advanced', 'expert'],
                'default'    => 'basic',
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);
        
        $this->forge->addKey('id', true);
        $this->forge->addKey(['employee_id', 'skill_name']);
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('employee_skills');

        // Insert sample skills/tasks
        $this->db->table('employees')->insertBatch([
            [
                'employee_code' => 'EMP001',
                'first_name' => 'Rajesh',
                'last_name' => 'Kumar',
                'phone' => '9876543210',
                'department' => 'Production',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'employee_code' => 'EMP002', 
                'first_name' => 'Priya',
                'last_name' => 'Sharma',
                'phone' => '9876543211',
                'department' => 'Quality Control',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'employee_code' => 'EMP003',
                'first_name' => 'Amit',
                'last_name' => 'Singh',
                'phone' => '9876543212', 
                'department' => 'Production',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ]);

        // Insert sample skills
        $this->db->table('employee_skills')->insertBatch([
            ['employee_id' => 1, 'skill_name' => 'Laser Cutting', 'proficiency_level' => 'expert', 'created_at' => date('Y-m-d H:i:s')],
            ['employee_id' => 1, 'skill_name' => 'Metal Forming', 'proficiency_level' => 'advanced', 'created_at' => date('Y-m-d H:i:s')],
            ['employee_id' => 2, 'skill_name' => 'Quality Inspection', 'proficiency_level' => 'expert', 'created_at' => date('Y-m-d H:i:s')],
            ['employee_id' => 2, 'skill_name' => 'Laser Marking', 'proficiency_level' => 'intermediate', 'created_at' => date('Y-m-d H:i:s')],
            ['employee_id' => 3, 'skill_name' => 'Packing', 'proficiency_level' => 'advanced', 'created_at' => date('Y-m-d H:i:s')],
            ['employee_id' => 3, 'skill_name' => 'Quality Inspection', 'proficiency_level' => 'basic', 'created_at' => date('Y-m-d H:i:s')],
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('employee_skills', true);
        $this->forge->dropTable('employees', true);
    }
}
