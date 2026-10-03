<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Two additions HR asked for:
 *   - a photo on the employee record (kept on the employee, not the login, so
 *     people without a user account still have one)
 *   - commission on a salary slip, with the note explaining why it was given
 *     that month, and its own expense head so the books can tell commission
 *     apart from basic pay.
 */
class AddEmployeePhotoAndSalaryCommission extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('photo_path', 'employees')) {
            $this->forge->addColumn('employees', [
                'photo_path' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                    'after'      => 'email',
                ],
            ]);
        }

        $fields = [];
        foreach ([
            'commission'      => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0, 'null' => false, 'after' => 'allowances'],
            'commission_note' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'commission'],
        ] as $name => $definition) {
            if (! $this->db->fieldExists($name, 'salary_payments')) {
                $fields[$name] = $definition;
            }
        }
        if ($fields) {
            $this->forge->addColumn('salary_payments', $fields);
        }

        // Its own expense head, so commission is not buried in Salaries Expense.
        // 5210 sits under Salaries Expense (5200); 5400 is already Office Supplies.
        $exists = $this->db->table('accounts')->where('code', '5210')->countAllResults() > 0;
        if (! $exists) {
            $this->db->table('accounts')->insert([
                'code'          => '5210',
                'name'          => 'Commission Expense',
                'type'          => 'Expense',
                'currency_code' => 'PKR',
                'is_bank'       => 0,
                'is_active'     => 1,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down()
    {
        $this->forge->dropColumn('employees', 'photo_path');
        $this->forge->dropColumn('salary_payments', ['commission', 'commission_note']);
        // The account is left alone: journal lines may already point at it.
    }
}
