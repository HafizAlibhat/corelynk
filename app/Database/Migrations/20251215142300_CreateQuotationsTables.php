<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateQuotationsTables extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        // Quotations master table
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'quote_number' => [
                'type' => 'VARCHAR',
                'constraint' => 64,
                'null' => false,
            ],
            'company_id' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => true,
            ],
            'customer_id' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => false,
            ],
            'price_list_id' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => true,
            ],
            'issue_date' => [
                'type' => 'DATE',
                'null' => false,
                'default' => 'CURRENT_DATE',
            ],
            'expires_at' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'status' => [
                'type' => 'VARCHAR',
                'constraint' => 32,
                'null' => false,
                'default' => 'draft',
            ],
            'currency' => [
                'type' => 'VARCHAR',
                'constraint' => 8,
                'null' => true,
                'default' => 'USD',
            ],
            'subtotal' => [ 'type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00' ],
            'discount' => [ 'type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00' ],
            'tax' => [ 'type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00' ],
            'total' => [ 'type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00' ],
            'notes' => [ 'type' => 'TEXT', 'null' => true ],
            'created_by' => [ 'type' => 'INT', 'unsigned' => true, 'null' => true ],
            'created_at' => [ 'type' => 'DATETIME', 'null' => true ],
            'updated_at' => [ 'type' => 'DATETIME', 'null' => true ],
            'deleted_at' => [ 'type' => 'DATETIME', 'null' => true ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('quote_number');
        $this->forge->addKey('customer_id');

        // Conditional foreign keys (only if referenced tables exist)
        if ($db->tableExists('customers')) {
            $this->forge->addForeignKey('customer_id', 'customers', 'id', 'CASCADE', 'CASCADE');
        }
        if ($db->tableExists('price_lists')) {
            $this->forge->addForeignKey('price_list_id', 'price_lists', 'id', 'SET NULL', 'CASCADE');
        }
        if ($db->tableExists('users')) {
            $this->forge->addForeignKey('created_by', 'users', 'id', 'SET NULL', 'CASCADE');
        }
        if ($db->tableExists('companies')) {
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'SET NULL', 'CASCADE');
        }

        $this->forge->createTable('quotations', true);

        // Quotation lines table
        $this->forge->addField([
            'id' => [ 'type' => 'INT', 'unsigned' => true, 'auto_increment' => true ],
            'quotation_id' => [ 'type' => 'INT', 'unsigned' => true, 'null' => false ],
            'product_id' => [ 'type' => 'INT', 'unsigned' => true, 'null' => true ],
            'price_list_id' => [ 'type' => 'INT', 'unsigned' => true, 'null' => true ],
            'description' => [ 'type' => 'TEXT', 'null' => true ],
            'quantity' => [ 'type' => 'DECIMAL', 'constraint' => '12,4', 'default' => '1.0000' ],
            'unit' => [ 'type' => 'VARCHAR', 'constraint' => 16, 'null' => true ],
            'unit_price' => [ 'type' => 'DECIMAL', 'constraint' => '14,4', 'default' => '0.0000' ],
            'discount' => [ 'type' => 'DECIMAL', 'constraint' => '14,4', 'default' => '0.0000' ],
            'tax' => [ 'type' => 'DECIMAL', 'constraint' => '14,4', 'default' => '0.0000' ],
            'line_total' => [ 'type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00' ],
            'sort_order' => [ 'type' => 'INT', 'default' => 0 ],
            'created_at' => [ 'type' => 'DATETIME', 'null' => true ],
            'updated_at' => [ 'type' => 'DATETIME', 'null' => true ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('quotation_id');
        $this->forge->addKey('product_id');

        // FK -> quotations (must exist now)
        if ($db->tableExists('quotations')) {
            $this->forge->addForeignKey('quotation_id', 'quotations', 'id', 'CASCADE', 'CASCADE');
        }
        if ($db->tableExists('products')) {
            $this->forge->addForeignKey('product_id', 'products', 'id', 'SET NULL', 'CASCADE');
        }
        if ($db->tableExists('price_lists')) {
            $this->forge->addForeignKey('price_list_id', 'price_lists', 'id', 'SET NULL', 'CASCADE');
        }

        $this->forge->createTable('quotation_lines', true);
    }

    public function down()
    {
        // Drop child table first
        $this->forge->dropTable('quotation_lines', true);
        $this->forge->dropTable('quotations', true);
    }
}
