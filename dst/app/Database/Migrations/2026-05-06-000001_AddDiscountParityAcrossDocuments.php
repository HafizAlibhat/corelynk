<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDiscountParityAcrossDocuments extends Migration
{
    public function up()
    {
        $this->addColumnsIfMissing('quotations', [
            'document_discount_type' => ['type' => "ENUM('percent','fixed')", 'default' => 'fixed'],
            'document_discount_value' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'discount_exclude_shipping' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ]);

        $this->addColumnsIfMissing('sales_orders', [
            'discount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'document_discount_type' => ['type' => "ENUM('percent','fixed')", 'default' => 'fixed'],
            'document_discount_value' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'discount_exclude_shipping' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'shipping_amount' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => '0.00'],
        ]);

        $this->addColumnsIfMissing('sales_order_lines', [
            'discount_type' => ['type' => "ENUM('percent','fixed')", 'default' => 'percent'],
            'discount_value' => ['type' => 'DECIMAL', 'constraint' => '12,4', 'default' => '0.0000'],
            'discount_amount' => ['type' => 'DECIMAL', 'constraint' => '12,4', 'default' => '0.0000'],
            'tax_rate' => ['type' => 'DECIMAL', 'constraint' => '8,4', 'default' => '0.0000'],
            'tax_amount' => ['type' => 'DECIMAL', 'constraint' => '12,4', 'default' => '0.0000'],
            'product_variant_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
        ]);

        $this->addColumnsIfMissing('customer_invoices', [
            'discount_total' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'document_discount_type' => ['type' => "ENUM('percent','fixed')", 'default' => 'fixed'],
            'document_discount_value' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'discount_exclude_shipping' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ]);

        $this->addColumnsIfMissing('purchase_orders', [
            'discount_total' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'document_discount_type' => ['type' => "ENUM('percent','fixed')", 'default' => 'fixed'],
            'document_discount_value' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'discount_exclude_shipping' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'shipping_amount' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => '0.00'],
        ]);

        $this->addColumnsIfMissing('purchase_order_lines', [
            'discount_type' => ['type' => "ENUM('percent','fixed')", 'default' => 'percent'],
            'discount_value' => ['type' => 'DECIMAL', 'constraint' => '12,4', 'default' => '0.0000'],
            'discount_amount' => ['type' => 'DECIMAL', 'constraint' => '12,4', 'default' => '0.0000'],
        ]);
    }

    public function down()
    {
        $this->dropColumnsIfExists('sales_orders', ['discount', 'document_discount_type', 'document_discount_value', 'discount_exclude_shipping']);
        $this->dropColumnsIfExists('sales_order_lines', ['discount_type', 'discount_value', 'discount_amount', 'tax_rate', 'tax_amount']);
        $this->dropColumnsIfExists('customer_invoices', ['discount_total', 'document_discount_type', 'document_discount_value', 'discount_exclude_shipping']);
        $this->dropColumnsIfExists('purchase_orders', ['discount_total', 'document_discount_type', 'document_discount_value', 'discount_exclude_shipping', 'shipping_amount']);
        $this->dropColumnsIfExists('purchase_order_lines', ['discount_type', 'discount_value', 'discount_amount']);
    }

    private function addColumnsIfMissing(string $table, array $columns): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        $toAdd = [];
        foreach ($columns as $name => $definition) {
            if (! $this->db->fieldExists($name, $table)) {
                $toAdd[$name] = $definition;
            }
        }

        if (! empty($toAdd)) {
            $this->forge->addColumn($table, $toAdd);
        }
    }

    private function dropColumnsIfExists(string $table, array $columns): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        foreach ($columns as $col) {
            if ($this->db->fieldExists($col, $table)) {
                try {
                    $this->forge->dropColumn($table, $col);
                } catch (\Throwable $_) {
                    // ignore best-effort rollback failures
                }
            }
        }
    }
}
