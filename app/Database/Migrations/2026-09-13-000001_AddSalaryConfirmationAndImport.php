<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Salaries went straight from "prepared" to "paid" with no accountant
 * sign-off, and there was no way to bring in salaries paid before this
 * system existed. Adds a confirmation trail (draft -> confirmed -> paid)
 * and a flag for slips brought in from a historical import.
 */
class AddSalaryConfirmationAndImport extends Migration
{
    public function up()
    {
        $fields = [];
        foreach ([
            'confirmed_by' => ['type' => 'INT', 'constraint' => 11, 'null' => true, 'after' => 'status'],
            'confirmed_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'confirmed_by'],
            'is_imported'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'notes'],
        ] as $name => $definition) {
            if (! $this->db->fieldExists($name, 'salary_payments')) {
                $fields[$name] = $definition;
            }
        }

        if ($fields) {
            $this->forge->addColumn('salary_payments', $fields);
        }
    }

    public function down()
    {
        $this->forge->dropColumn('salary_payments', ['confirmed_by', 'confirmed_at', 'is_imported']);
    }
}
