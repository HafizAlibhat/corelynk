<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMissingQuotationColumns extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        $forge = \Config\Database::forge();

        // Add missing header columns to `quotations` if they don't exist
        if ($db->tableExists('quotations')) {
            $cols = [];
            if (! $db->fieldExists('price_list_id', 'quotations')) {
                $cols['price_list_id'] = [
                    'type' => 'INT',
                    'unsigned' => true,
                    'null' => true,
                ];
            }
            if (! $db->fieldExists('discount', 'quotations')) {
                $cols['discount'] = [ 'type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00' ];
            }
            if (! $db->fieldExists('tax', 'quotations')) {
                $cols['tax'] = [ 'type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00' ];
            }
            if (! $db->fieldExists('subtotal', 'quotations')) {
                $cols['subtotal'] = [ 'type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00' ];
            }
            if (! $db->fieldExists('total', 'quotations')) {
                $cols['total'] = [ 'type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00' ];
            }
            if (! $db->fieldExists('document_discount_value', 'quotations')) {
                $cols['document_discount_value'] = [ 'type' => 'DECIMAL', 'constraint' => '14,2', 'null' => true ];
            }
            if (! $db->fieldExists('document_discount_type', 'quotations')) {
                $cols['document_discount_type'] = [ 'type' => 'VARCHAR', 'constraint' => 32, 'null' => true ];
            }
            if (! $db->fieldExists('tax_total', 'quotations')) {
                $cols['tax_total'] = [ 'type' => 'DECIMAL', 'constraint' => '14,2', 'null' => true ];
            }

            if (count($cols)) {
                $forge->addColumn('quotations', $cols);
            }
        }

        // Add missing line columns to `quotation_lines` if they don't exist
        if ($db->tableExists('quotation_lines')) {
            $lcols = [];
            if (! $db->fieldExists('product_name', 'quotation_lines')) {
                $lcols['product_name'] = [ 'type' => 'VARCHAR', 'constraint' => 255, 'null' => true ];
            }
            if (! $db->fieldExists('sort_order', 'quotation_lines')) {
                $lcols['sort_order'] = [ 'type' => 'INT', 'default' => 0 ];
            }
            if (! $db->fieldExists('discount_value', 'quotation_lines')) {
                $lcols['discount_value'] = [ 'type' => 'DECIMAL', 'constraint' => '14,4', 'default' => '0.0000' ];
            }
            if (! $db->fieldExists('discount_type', 'quotation_lines')) {
                $lcols['discount_type'] = [ 'type' => 'VARCHAR', 'constraint' => 16, 'default' => 'percent' ];
            }
            if (! $db->fieldExists('tax_rate', 'quotation_lines')) {
                $lcols['tax_rate'] = [ 'type' => 'DECIMAL', 'constraint' => '14,4', 'default' => '0.0000' ];
            }
            if (! $db->fieldExists('line_number', 'quotation_lines')) {
                $lcols['line_number'] = [ 'type' => 'INT', 'default' => 0 ];
            }
            if (! $db->fieldExists('unit_price', 'quotation_lines')) {
                $lcols['unit_price'] = [ 'type' => 'DECIMAL', 'constraint' => '14,4', 'default' => '0.0000' ];
            }
            if (! $db->fieldExists('line_total', 'quotation_lines')) {
                $lcols['line_total'] = [ 'type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00' ];
            }

            if (count($lcols)) {
                $forge->addColumn('quotation_lines', $lcols);
            }
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        $forge = \Config\Database::forge();

        // Remove added columns if they exist
        if ($db->tableExists('quotation_lines')) {
            $remove = [];
            foreach (['product_name','discount_value','discount_type','tax_rate','line_number','unit_price','line_total'] as $c) {
                if ($db->fieldExists($c, 'quotation_lines')) $remove[] = $c;
            }
            if (count($remove)) {
                // forge->dropColumn accepts array of columns
                $forge->dropColumn('quotation_lines', $remove);
            }
        }

        if ($db->tableExists('quotations')) {
            $remove = [];
            foreach (['price_list_id','discount','tax','subtotal','total','document_discount_value','document_discount_type','tax_total'] as $c) {
                if ($db->fieldExists($c, 'quotations')) $remove[] = $c;
            }
            if (count($remove)) {
                $forge->dropColumn('quotations', $remove);
            }
        }
    }
}
