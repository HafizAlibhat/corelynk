<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Employees could not be added because `employees`.`id` and `employee_skills`.`id`
 * were plain INT primary keys with no AUTO_INCREMENT: the first insert landed on
 * id 0 and every insert after it collided on the primary key.
 *
 * This migration renumbers the id 0 row, restores AUTO_INCREMENT on both tables,
 * and adds the salary fields plus the payslip table the HR module needs.
 */
class FixEmployeeIdsAndAddPayroll extends Migration
{
    public function up()
    {
        $db = $this->db;

        // 1. Move the id-0 row out of the way, taking its references with it.
        $zero = $db->query('SELECT id FROM employees WHERE id = 0')->getRowArray();
        if ($zero) {
            $next = (int) $db->query('SELECT COALESCE(MAX(id), 0) + 1 AS n FROM employees')->getRow()->n;
            $db->query('UPDATE employees SET id = ? WHERE id = 0', [$next]);
            foreach (['employee_skills', 'process_batch_employees', 'process_employee_assignments', 'cheques'] as $table) {
                if ($db->tableExists($table)) {
                    $db->query("UPDATE {$table} SET employee_id = ? WHERE employee_id = 0", [$next]);
                }
            }
        }

        // 2. Restore AUTO_INCREMENT. The cheques FK has to stand down for the ALTER.
        $db->query('SET FOREIGN_KEY_CHECKS = 0');
        $db->query('ALTER TABLE employees MODIFY id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT');
        $db->query('ALTER TABLE employee_skills MODIFY id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT');
        $db->query('SET FOREIGN_KEY_CHECKS = 1');

        // 3. Employment and pay details, so a salary can be recorded against a person.
        $fields = [];
        foreach ([
            'designation'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'department'],
            'joining_date'    => ['type' => 'DATE', 'null' => true, 'after' => 'designation'],
            'monthly_salary'  => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => true, 'after' => 'joining_date'],
            'salary_currency' => ['type' => 'VARCHAR', 'constraint' => 3, 'null' => true, 'after' => 'monthly_salary'],
        ] as $name => $definition) {
            if (! $db->fieldExists($name, 'employees')) {
                $fields[$name] = $definition;
            }
        }
        if ($fields) {
            $this->forge->addColumn('employees', $fields);
        }

        // 4. One payslip per employee per month.
        if (! $db->tableExists('salary_payments')) {
            $this->forge->addField([
                'id'             => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
                'employee_id'    => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'period_month'   => ['type' => 'DATE', 'comment' => 'First day of the salary month'],
                'basic_amount'   => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
                'allowances'     => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
                'deductions'     => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
                'net_amount'     => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
                'currency_code'  => ['type' => 'VARCHAR', 'constraint' => 3, 'null' => true],
                'status'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
                'paid_on'        => ['type' => 'DATE', 'null' => true],
                'payment_method' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'notes'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'created_by'     => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'created_at'     => ['type' => 'DATETIME', 'null' => true],
                'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['employee_id', 'period_month'], 'uniq_employee_month');
            $this->forge->addKey('period_month');
            $this->forge->createTable('salary_payments');
        }
    }

    public function down()
    {
        $this->forge->dropTable('salary_payments', true);
        $this->forge->dropColumn('employees', ['designation', 'joining_date', 'monthly_salary', 'salary_currency']);
        // AUTO_INCREMENT is deliberately left in place: reverting it would re-break inserts.
    }
}
