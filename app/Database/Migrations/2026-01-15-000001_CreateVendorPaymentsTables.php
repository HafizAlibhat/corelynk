<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateVendorPaymentsTables extends Migration
{
    public function up()
    {
        // Step 1: DB-safe layer for vendor bill payments
        // - vendor_payments (can exist without bill = advance)
        // - vendor_payment_allocations (many-to-many payment <-> vendor_bills)

        if (! $this->db->tableExists('vendor_payments')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],

                'vendor_id' => ['type' => 'INT', 'constraint' => 11, 'null' => false],
                'payment_date' => ['type' => 'DATETIME', 'null' => false],

                // cash | bank | cheque | advance
                'payment_method' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => false],

                // PKR-only in Phase 1 (kept for forward compatibility)
                'currency_code' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => false, 'default' => 'PKR'],

                'amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],

                // Selected cash/bank account. Nullable for non-cash movements (e.g., pure advance allocation).
                'source_account_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true, 'default' => null],

                // Link to the posted journal entry created by AccountingPostingService (or cheque module).
                'posted_entry_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true, 'default' => null],

                // Cheque link (when method=cheque). Do not enforce FK to keep this migration safe.
                'cheque_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true, 'default' => null],

                // Optional reference for bank transfer / receipt / manual note.
                'reference_no' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'default' => null],
                'memo' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'default' => null],
                'notes' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'default' => null],

                // draft | posted | void
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => false, 'default' => 'draft'],

                'created_by' => ['type' => 'INT', 'constraint' => 11, 'null' => true, 'default' => null],
                'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
                'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('vendor_id');
            $this->forge->addKey('payment_date');
            $this->forge->addKey('payment_method');
            $this->forge->addKey('status');
            $this->forge->addKey('source_account_id');
            $this->forge->addKey('posted_entry_id');
            $this->forge->addKey('cheque_id');

            // Prevent accidental linking of multiple payments to the same posted entry/cheque.
            $this->forge->addUniqueKey('posted_entry_id');
            $this->forge->addUniqueKey('cheque_id');

            $this->forge->createTable('vendor_payments', true);
        }

        if (! $this->db->tableExists('vendor_payment_allocations')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],

                'payment_id' => ['type' => 'INT', 'constraint' => 11, 'null' => false],
                'vendor_bill_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true, 'default' => null],
                'purchase_order_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true, 'default' => null],

                'amount_allocated' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],

                'allocated_at' => ['type' => 'DATETIME', 'null' => false],

                'created_by' => ['type' => 'INT', 'constraint' => 11, 'null' => true, 'default' => null],
                'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('payment_id');
            $this->forge->addKey('vendor_bill_id');
            $this->forge->addKey('purchase_order_id');
            $this->forge->addUniqueKey(['vendor_bill_id', 'payment_id']);

            $this->forge->createTable('vendor_payment_allocations', true);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('vendor_payment_allocations')) {
            $this->forge->dropTable('vendor_payment_allocations', true);
        }

        if ($this->db->tableExists('vendor_payments')) {
            $this->forge->dropTable('vendor_payments', true);
        }
    }
}
