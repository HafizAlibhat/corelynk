<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * An employee is the HR record; a user is a login. They are separate on
 * purpose — owners and service accounts have logins without being on the
 * payroll — so the link is one nullable, unique column on the employee.
 */
class LinkEmployeesToUsers extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('user_id', 'employees')) {
            return;
        }

        $this->forge->addColumn('employees', [
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'email',
            ],
        ]);

        // Unique: one login belongs to at most one employee.
        $this->db->query('ALTER TABLE employees ADD UNIQUE KEY uniq_employee_user (user_id)');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE employees DROP INDEX uniq_employee_user');
        $this->forge->dropColumn('employees', 'user_id');
    }
}
