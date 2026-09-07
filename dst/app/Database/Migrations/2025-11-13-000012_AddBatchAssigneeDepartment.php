<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBatchAssigneeDepartment extends Migration
{
    public function up()
    {
        // Add optional department column to process_batch_employees for department assignments per batch
        if ($this->db->tableExists('process_batch_employees')) {
            $cols = $this->db->query("SHOW COLUMNS FROM process_batch_employees")->getResultArray();
            $fields = array_map(fn($r) => $r['Field'], $cols);
            if (!in_array('department', $fields, true)) {
                $this->forge->addColumn('process_batch_employees', [
                    'department' => [
                        'type' => 'VARCHAR',
                        'constraint' => 100,
                        'null' => true,
                        'after' => 'employee_id'
                    ],
                ]);
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('process_batch_employees')) {
            $cols = $this->db->query("SHOW COLUMNS FROM process_batch_employees")->getResultArray();
            $fields = array_map(fn($r) => $r['Field'], $cols);
            if (in_array('department', $fields, true)) {
                // Safe down: keep column to avoid data loss in production. Comment to drop if needed.
                // $this->forge->dropColumn('process_batch_employees', 'department');
            }
        }
    }
}
