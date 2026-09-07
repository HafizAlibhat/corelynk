<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnhanceBatchLogsTable extends Migration
{
    public function up()
    {
        // First, let's see if the table exists and update it
        if (!$this->db->tableExists('process_batch_logs')) {
            // Create new enhanced batch logs table
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'batch_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'employee_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'log_type' => [
                    'type'       => 'ENUM',
                    'constraint' => ['start', 'progress', 'completion', 'rejection', 'repair_send', 'repair_receive'],
                    'default'    => 'progress',
                ],
                'qty_received' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                ],
                'qty_completed' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                ],
                'qty_rejected' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                ],
                'qty_scrapped' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                ],
                'qty_sent_for_repair' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                ],
                'rejection_reason' => [
                    'type'       => 'TEXT',
                    'null'       => true,
                ],
                'work_description' => [
                    'type'       => 'TEXT',
                    'null'       => true,
                ],
                'issues_notes' => [
                    'type'       => 'TEXT',
                    'null'       => true,
                ],
                'shift' => [
                    'type'       => 'ENUM',
                    'constraint' => ['day', 'night', 'general'],
                    'default'    => 'day',
                ],
                'log_date' => [
                    'type'    => 'DATE',
                    'null'    => false,
                ],
                'log_time' => [
                    'type'    => 'TIME',
                    'null'    => false,
                ],
                'created_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
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
            $this->forge->addKey('batch_id');
            $this->forge->addKey('log_date');
            $this->forge->addForeignKey('batch_id', 'process_batches', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('employee_id', 'employees', 'id', 'SET NULL', 'SET NULL');
            $this->forge->createTable('process_batch_logs');
        }

        // Add batch assignments table for employee assignments
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'batch_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'employee_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'assigned_qty' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'start_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'start_time' => [
                'type' => 'TIME',
                'null' => true,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['assigned', 'started', 'completed', 'on_hold'],
                'default'    => 'assigned',
            ],
            'assigned_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
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
        $this->forge->addKey(['batch_id', 'employee_id']);
        $this->forge->addForeignKey('batch_id', 'process_batches', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('batch_assignments');
    }

    public function down()
    {
        $this->forge->dropTable('batch_assignments', true);
        $this->forge->dropTable('process_batch_logs', true);
    }
}
