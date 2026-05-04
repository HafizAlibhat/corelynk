<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBatchEmployees extends Migration
{
    public function up()
    {
        // Create pivot table for batch -> employees (many-to-many)
        if (!$this->db->tableExists('process_batch_employees')) {
            $this->forge->addField([
                'id' => [ 'type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true ],
                'batch_id' => [ 'type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => false ],
                'employee_id' => [ 'type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => false ],
                'created_at' => [ 'type' => 'DATETIME', 'null' => true, 'default' => null ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['batch_id']);
            $this->forge->addKey(['employee_id']);
            $this->forge->createTable('process_batch_employees', true);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('process_batch_employees')) {
            $this->forge->dropTable('process_batch_employees');
        }
    }
}
