<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Milestone payment terms (e.g. "80% advance, 20% net 30 after delivery"),
 * the per-invoice schedule generated from them, plus the bank details and
 * terms & conditions blocks printed on the invoice.
 */
class AddInvoicePaymentTermsSchedule extends Migration
{
    public function up()
    {
        $addMissing = function (string $table, array $fields): void {
            if (!$this->db->tableExists($table)) return;
            $existing = $this->db->getFieldNames($table);
            $toAdd = array_diff_key($fields, array_flip($existing));
            if (!empty($toAdd)) $this->forge->addColumn($table, $toAdd);
        };

        $addMissing('payment_terms', [
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            // JSON array: [{label, percentage, basis, offset_days}, ...]
            'milestones'  => ['type' => 'TEXT', 'null' => true],
        ]);

        $addMissing('company_settings', [
            'bank_details'  => ['type' => 'TEXT', 'null' => true],
            'invoice_terms' => ['type' => 'TEXT', 'null' => true],
        ]);

        // Per-invoice overrides; blank falls back to the company defaults above.
        $addMissing('customer_invoices', [
            'bank_details'      => ['type' => 'TEXT', 'null' => true],
            'terms_conditions'  => ['type' => 'TEXT', 'null' => true],
        ]);

        if (!$this->db->tableExists('customer_invoice_schedules')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'invoice_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => false],
                'seq'         => ['type' => 'INT', 'constraint' => 11, 'null' => false, 'default' => 1],
                'label'       => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => false, 'default' => 'Payment'],
                'percentage'  => ['type' => 'DECIMAL', 'constraint' => '9,4', 'null' => false, 'default' => '0.0000'],
                'amount'      => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => false, 'default' => '0.00'],
                'basis'       => ['type' => 'ENUM', 'constraint' => ['invoice_date', 'delivery_date'], 'null' => false, 'default' => 'invoice_date'],
                'offset_days' => ['type' => 'INT', 'constraint' => 11, 'null' => false, 'default' => 0],
                // NULL while a delivery-based milestone has no delivery date yet.
                'due_date'    => ['type' => 'DATE', 'null' => true],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
                'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['invoice_id', 'seq']);
            $this->forge->addKey('due_date');
            $this->forge->createTable('customer_invoice_schedules', true);
        }

        // Milestone definitions for the terms seeded by the original invoicing migration.
        $flat = [
            'NET30'          => [['label' => 'Full Payment', 'percentage' => 100, 'basis' => 'invoice_date', 'offset_days' => 30]],
            'NET15'          => [['label' => 'Full Payment', 'percentage' => 100, 'basis' => 'invoice_date', 'offset_days' => 15]],
            '2_10_NET30'     => [['label' => 'Full Payment', 'percentage' => 100, 'basis' => 'invoice_date', 'offset_days' => 30]],
            'DUE_ON_RECEIPT' => [['label' => 'Full Payment', 'percentage' => 100, 'basis' => 'invoice_date', 'offset_days' => 0]],
        ];
        foreach ($flat as $code => $milestones) {
            $this->db->table('payment_terms')
                ->where('code', $code)->where('milestones IS NULL', null, false)
                ->update(['milestones' => json_encode($milestones)]);
        }

        $advance = [
            'name'                => '80% Advance / 20% Net 30 After Delivery',
            'code'                => 'ADV80_NET30_DEL',
            'description'         => '80% payable in advance before production, 20% payable 30 days after goods are delivered.',
            'net_days'            => 30,
            'discount_days'       => 0,
            'discount_percentage' => 0.00,
            'is_active'           => 1,
            'created_at'          => date('Y-m-d H:i:s'),
            'milestones'          => json_encode([
                ['label' => 'Advance Payment', 'percentage' => 80, 'basis' => 'invoice_date', 'offset_days' => 0],
                ['label' => 'Balance After Delivery', 'percentage' => 20, 'basis' => 'delivery_date', 'offset_days' => 30],
            ]),
        ];
        $exists = $this->db->table('payment_terms')->where('code', $advance['code'])->countAllResults();
        if (!$exists) $this->db->table('payment_terms')->insert($advance);
    }

    public function down()
    {
        $this->forge->dropTable('customer_invoice_schedules', true);
        $this->db->table('payment_terms')->where('code', 'ADV80_NET30_DEL')->delete();

        $drop = function (string $table, array $cols): void {
            if (!$this->db->tableExists($table)) return;
            $existing = $this->db->getFieldNames($table);
            foreach ($cols as $c) {
                if (in_array($c, $existing, true)) $this->forge->dropColumn($table, $c);
            }
        };
        $drop('payment_terms', ['description', 'milestones']);
        $drop('company_settings', ['bank_details', 'invoice_terms']);
        $drop('customer_invoices', ['bank_details', 'terms_conditions']);
    }
}
