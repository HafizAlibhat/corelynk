<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnhanceQuotationsAndLines extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        $forge = \Config\Database::forge(true);

        // Enhance quotations table
        if ($db->tableExists('quotations')) {
            $fields = [];
            if (! $db->fieldExists('document_discount_type', 'quotations')) {
                $fields['document_discount_type'] = ['type' => "ENUM('percent','fixed')", 'default' => 'percent'];
            }
            if (! $db->fieldExists('document_discount_value', 'quotations')) {
                $fields['document_discount_value'] = ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0];
            }
            if (! $db->fieldExists('document_tax_type', 'quotations')) {
                $fields['document_tax_type'] = ['type' => "ENUM('percent','fixed')", 'default' => 'percent'];
            }
            if (! $db->fieldExists('document_tax_value', 'quotations')) {
                $fields['document_tax_value'] = ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0];
            }
            if (! $db->fieldExists('shipping_cost', 'quotations')) {
                $fields['shipping_cost'] = ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0];
            }
            if (! $db->fieldExists('handling_charges', 'quotations')) {
                $fields['handling_charges'] = ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0];
            }
            if (! $db->fieldExists('packaging_charges', 'quotations')) {
                $fields['packaging_charges'] = ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0];
            }
            if (! $db->fieldExists('insurance_cost', 'quotations')) {
                $fields['insurance_cost'] = ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0];
            }
            if (! $db->fieldExists('total_weight', 'quotations')) {
                $fields['total_weight'] = ['type' => 'DECIMAL', 'constraint' => '10,3', 'default' => 0];
            }
            if (! $db->fieldExists('exchange_rate', 'quotations')) {
                $fields['exchange_rate'] = ['type' => 'DECIMAL', 'constraint' => '10,4', 'default' => 1.0000];
            }
            if (! $db->fieldExists('base_currency', 'quotations')) {
                $fields['base_currency'] = ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'USD'];
            }
            if (! $db->fieldExists('quote_currency', 'quotations')) {
                $fields['quote_currency'] = ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'USD'];
            }

            if (! empty($fields)) {
                $forge->addColumn('quotations', $fields);
            }
        }

        // Enhance quotation_lines / quotation_items table
        $linesTable = $db->tableExists('quotation_lines') ? 'quotation_lines' : ($db->tableExists('quotation_items') ? 'quotation_items' : null);
        if ($linesTable) {
            $fields = [];
            if (! $db->fieldExists('line_number', $linesTable)) {
                $fields['line_number'] = ['type' => 'INT', 'null' => true];
            }
            if (! $db->fieldExists('discount_type', $linesTable)) {
                $fields['discount_type'] = ['type' => "ENUM('percent','fixed')", 'default' => 'percent'];
            }
            if (! $db->fieldExists('discount_value', $linesTable)) {
                $fields['discount_value'] = ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0];
            }
            if (! $db->fieldExists('tax_rate', $linesTable)) {
                $fields['tax_rate'] = ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0];
            }
            if (! $db->fieldExists('tax_amount', $linesTable)) {
                $fields['tax_amount'] = ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0];
            }
            if (! $db->fieldExists('weight', $linesTable)) {
                $fields['weight'] = ['type' => 'DECIMAL', 'constraint' => '10,3', 'default' => 0];
            }
            if (! $db->fieldExists('vendor_id', $linesTable)) {
                $fields['vendor_id'] = ['type' => 'INT', 'null' => true];
            }
            if (! $db->fieldExists('cost_price', $linesTable)) {
                $fields['cost_price'] = ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0];
            }
            if (! $db->fieldExists('sale_price_currency', $linesTable)) {
                $fields['sale_price_currency'] = ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'USD'];
            }

            if (! empty($fields)) {
                $forge->addColumn($linesTable, $fields);
            }
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        $forge = \Config\Database::forge(true);

        if ($db->tableExists('quotations')) {
            $cols = ['document_discount_type','document_discount_value','document_tax_type','document_tax_value','shipping_cost','handling_charges','packaging_charges','insurance_cost','total_weight','exchange_rate','base_currency','quote_currency'];
            foreach ($cols as $c) {
                if ($db->fieldExists($c, 'quotations')) {
                    $forge->dropColumn('quotations', $c);
                }
            }
        }

        $linesTable = $db->tableExists('quotation_lines') ? 'quotation_lines' : ($db->tableExists('quotation_items') ? 'quotation_items' : null);
        if ($linesTable) {
            $cols = ['line_number','discount_type','discount_value','tax_rate','tax_amount','weight','vendor_id','cost_price','sale_price_currency'];
            foreach ($cols as $c) {
                if ($db->fieldExists($c, $linesTable)) {
                    $forge->dropColumn($linesTable, $c);
                }
            }
        }
    }
}
