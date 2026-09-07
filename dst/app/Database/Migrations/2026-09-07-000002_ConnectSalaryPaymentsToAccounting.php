<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Salaries were being recorded but never reached the books: there was no
 * cash/bank account to pay from and no journal entry behind the payment.
 *
 * Adds the payment source, cheque details and the posted journal entry link,
 * plus the matching source_type on journal_entries.
 */
class ConnectSalaryPaymentsToAccounting extends Migration
{
    public function up()
    {
        $fields = [];
        foreach ([
            'source_account_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true, 'after' => 'payment_method'],
            'cheque_number'     => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'after' => 'source_account_id'],
            'cheque_image'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'cheque_number'],
            'posted_entry_id'   => ['type' => 'INT', 'constraint' => 11, 'null' => true, 'after' => 'cheque_image'],
        ] as $name => $definition) {
            if (! $this->db->fieldExists($name, 'salary_payments')) {
                $fields[$name] = $definition;
            }
        }
        if ($fields) {
            $this->forge->addColumn('salary_payments', $fields);
        }

        // Widen the journal source_type enum so salary entries are identifiable
        // in the ledger rather than hiding among manual entries.
        $column = $this->db->query(
            "SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'journal_entries' AND COLUMN_NAME = 'source_type'"
        )->getRow();

        if ($column && ! str_contains($column->t, "'salary_payment'")) {
            $widened = preg_replace("/\)$/", ",'salary_payment')", $column->t);
            $this->db->query('ALTER TABLE journal_entries MODIFY source_type ' . $widened . ' NULL DEFAULT NULL');
        }
    }

    public function down()
    {
        $this->forge->dropColumn('salary_payments', ['source_account_id', 'cheque_number', 'cheque_image', 'posted_entry_id']);
        // The enum value is left alone: removing it would orphan posted entries.
    }
}
