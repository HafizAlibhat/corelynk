<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCustomerInvoicingTables extends Migration
{
    public function up()
    {
        // Create payment_terms
        $this->forge->addField([
            'id' => [
                'type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true
            ],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'code' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => false],
            'net_days' => ['type' => 'INT', 'constraint' => 11, 'null' => false],
            'discount_days' => ['type' => 'INT', 'constraint' => 11, 'null' => true, 'default' => 0],
            'discount_percentage' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true, 'default' => 0.00],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('code');
        $this->forge->createTable('payment_terms', true);

        // Create customer_invoices
        $this->forge->addField([
            'id' => ['type'=>'INT','constraint'=>11,'unsigned'=>true,'auto_increment'=>true],
            'invoice_number' => ['type'=>'VARCHAR','constraint'=>50,'null'=>false],
            'customer_id' => ['type'=>'INT','constraint'=>11,'null'=>false],
            'issue_date' => ['type'=>'DATE','null'=>false],
            'due_date' => ['type'=>'DATE','null'=>false],
            'payment_term_id' => ['type'=>'INT','constraint'=>11,'null'=>true],
            'currency_code' => ['type'=>'VARCHAR','constraint'=>3,'null'=>false,'default'=>'PKR'],
            'subtotal' => ['type'=>'DECIMAL','constraint'=>'15,2','null'=>false,'default'=>'0.00'],
            'tax_total' => ['type'=>'DECIMAL','constraint'=>'15,2','null'=>false,'default'=>'0.00'],
            'total_amount' => ['type'=>'DECIMAL','constraint'=>'15,2','null'=>false,'default'=>'0.00'],
            "status" => ['type' => 'ENUM', 'constraint' => ['draft','issued','partially_paid','paid','overdue','cancelled'], 'default' => 'draft'],
            'notes' => ['type'=>'TEXT','null'=>true],
            'posted_entry_id' => ['type'=>'INT','constraint'=>11,'null'=>true],
            'created_by' => ['type'=>'INT','constraint'=>11,'null'=>false],
            'created_at' => ['type'=>'DATETIME','null'=>true,'default'=>null],
            'updated_at' => ['type'=>'DATETIME','null'=>true,'default'=>null],
            'deleted_at' => ['type'=>'DATETIME','null'=>true]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('customer_id');
        $this->forge->addKey('status');
        $this->forge->addKey('due_date');
        $this->forge->createTable('customer_invoices', true);

        // Create customer_invoice_lines
        $this->forge->addField([
            'id' => ['type'=>'INT','constraint'=>11,'unsigned'=>true,'auto_increment'=>true],
            'invoice_id' => ['type'=>'INT','constraint'=>11,'null'=>false],
            'product_id' => ['type'=>'INT','constraint'=>11,'null'=>true],
            'description' => ['type'=>'VARCHAR','constraint'=>500,'null'=>false],
            'quantity' => ['type'=>'DECIMAL','constraint'=>'10,2','null'=>false,'default'=>'0.00'],
            'unit_price' => ['type'=>'DECIMAL','constraint'=>'15,2','null'=>false,'default'=>'0.00'],
            'tax_code_id' => ['type'=>'INT','constraint'=>11,'null'=>true],
            'line_total' => ['type'=>'DECIMAL','constraint'=>'15,2','null'=>false,'default'=>'0.00'],
            'created_at' => ['type'=>'DATETIME','null'=>true,'default'=>null]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('invoice_id');
        $this->forge->createTable('customer_invoice_lines', true);

        // Create customer_payments
        $this->forge->addField([
            'id' => ['type'=>'INT','constraint'=>11,'unsigned'=>true,'auto_increment'=>true],
            'customer_id' => ['type'=>'INT','constraint'=>11,'null'=>false],
            'payment_date' => ['type'=>'DATE','null'=>false],
            'payment_method_id' => ['type'=>'INT','constraint'=>11,'null'=>false],
            'amount' => ['type'=>'DECIMAL','constraint'=>'15,2','null'=>false,'default'=>'0.00'],
            'reference_number' => ['type'=>'VARCHAR','constraint'=>100,'null'=>true],
            'notes' => ['type'=>'TEXT','null'=>true],
            'posted_entry_id' => ['type'=>'INT','constraint'=>11,'null'=>true],
            'created_by' => ['type'=>'INT','constraint'=>11,'null'=>false],
            'created_at' => ['type'=>'DATETIME','null'=>true,'default'=>null],
            'updated_at' => ['type'=>'DATETIME','null'=>true,'default'=>null]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('customer_id');
        $this->forge->addKey('payment_date');
        $this->forge->createTable('customer_payments', true);

        // Create customer_payment_allocations
        $this->forge->addField([
            'id' => ['type'=>'INT','constraint'=>11,'unsigned'=>true,'auto_increment'=>true],
            'payment_id' => ['type'=>'INT','constraint'=>11,'null'=>false],
            'invoice_id' => ['type'=>'INT','constraint'=>11,'null'=>false],
            'allocated_amount' => ['type'=>'DECIMAL','constraint'=>'15,2','null'=>false,'default'=>'0.00'],
            'created_at' => ['type'=>'DATETIME','null'=>true,'default'=>null]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['payment_id','invoice_id'], false, true);
        $this->forge->addKey('payment_id');
        $this->forge->addKey('invoice_id');
        $this->forge->createTable('customer_payment_allocations', true);

        // Create customer_deposits
        $this->forge->addField([
            'id' => ['type'=>'INT','constraint'=>11,'unsigned'=>true,'auto_increment'=>true],
            'customer_id' => ['type'=>'INT','constraint'=>11,'null'=>false],
            'deposit_date' => ['type'=>'DATE','null'=>false],
            'amount' => ['type'=>'DECIMAL','constraint'=>'15,2','null'=>false,'default'=>'0.00'],
            'payment_method_id' => ['type'=>'INT','constraint'=>11,'null'=>false],
            'reference' => ['type'=>'VARCHAR','constraint'=>100,'null'=>true],
            'posted_entry_id' => ['type'=>'INT','constraint'=>11,'null'=>true],
            'created_at' => ['type'=>'DATETIME','null'=>true,'default'=>null]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('customer_id');
        $this->forge->createTable('customer_deposits', true);

        // Alter journal_entries to add source tracking
        $fields = [
            'source_type' => [ 'type' => "ENUM('invoice','payment','cheque','credit_note','manual')", 'null' => true, 'default' => null ],
            'source_id'   => [ 'type' => 'INT', 'constraint' => 11, 'null' => true, 'default' => null ]
        ];
        $this->forge->addColumn('journal_entries', $fields);

        // Alter credit_notes to reference invoices (nullable)
        $this->forge->addColumn('credit_notes', [
            'invoice_id' => ['type'=>'INT','constraint'=>11,'null'=>true,'default'=>null]
        ]);

        // Seed initial payment_terms
        $now = date('Y-m-d H:i:s');
        $data = [
            ['name'=>'Net 30','code'=>'NET30','net_days'=>30,'discount_days'=>0,'discount_percentage'=>0.00,'is_active'=>1,'created_at'=>$now],
            ['name'=>'2/10 Net 30','code'=>'2_10_NET30','net_days'=>30,'discount_days'=>10,'discount_percentage'=>2.00,'is_active'=>1,'created_at'=>$now],
            ['name'=>'Due on Receipt','code'=>'DUE_ON_RECEIPT','net_days'=>0,'discount_days'=>0,'discount_percentage'=>0.00,'is_active'=>1,'created_at'=>$now],
            ['name'=>'Net 15','code'=>'NET15','net_days'=>15,'discount_days'=>0,'discount_percentage'=>0.00,'is_active'=>1,'created_at'=>$now]
        ];
        $this->db->table('payment_terms')->insertBatch($data);
    }

    public function down()
    {
        // Reverse actions
        // Remove seeded payment_terms rows (by codes)
        $this->db->table('payment_terms')->whereIn('code', ['NET30','2_10_NET30','DUE_ON_RECEIPT','NET15'])->delete();

        // Drop columns from altered tables safely
        if ($this->db->fieldExists('source_type', 'journal_entries')) {
            $this->forge->dropColumn('journal_entries', 'source_type');
        }
        if ($this->db->fieldExists('source_id', 'journal_entries')) {
            $this->forge->dropColumn('journal_entries', 'source_id');
        }
        if ($this->db->fieldExists('invoice_id', 'credit_notes')) {
            $this->forge->dropColumn('credit_notes', 'invoice_id');
        }

        // Drop created tables (if exist)
        $this->forge->dropTable('customer_payment_allocations', true);
        $this->forge->dropTable('customer_payments', true);
        $this->forge->dropTable('customer_invoice_lines', true);
        $this->forge->dropTable('customer_invoices', true);
        $this->forge->dropTable('payment_terms', true);
        $this->forge->dropTable('customer_deposits', true);
    }
}
