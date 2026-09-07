<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

class ReconcileCustomsInvoiceSchema extends Migration
{
    private array $customsTables = [
        'customs_invoices',
        'customs_invoice_versions',
        'customs_invoice_items',
        'customs_invoice_audit_logs',
        'customs_invoice_approvals',
        'customs_invoice_files',
    ];

    public function up()
    {
        if ($this->hasTargetSchemaSignature()) {
            $this->ensurePermissionsMigration();
            return;
        }

        $existingTables = array_values(array_filter($this->customsTables, fn(string $table): bool => $this->db->tableExists($table)));
        if ($existingTables === []) {
            $this->recreateTargetSchema();
            return;
        }

        $nonEmptyTables = [];
        foreach ($existingTables as $table) {
            if ($this->db->table($table)->countAllResults() > 0) {
                $nonEmptyTables[] = $table;
            }
        }

        if ($nonEmptyTables !== []) {
            throw new RuntimeException(
                'Customs invoice schema reconciliation aborted. These tables contain data and require a manual migration path: '
                . implode(', ', $nonEmptyTables)
            );
        }

        $this->db->disableForeignKeyChecks();
        try {
            foreach (array_reverse($existingTables) as $table) {
                $this->forge->dropTable($table, true);
            }
        } finally {
            $this->db->enableForeignKeyChecks();
        }

        $this->recreateTargetSchema();
    }

    public function down()
    {
        // This migration intentionally has no destructive rollback.
        // Restoring the previous schema should be done from a verified backup.
    }

    private function hasTargetSchemaSignature(): bool
    {
        return $this->db->tableExists('customs_invoices')
            && $this->db->fieldExists('uuid', 'customs_invoices')
            && $this->db->fieldExists('current_version_id', 'customs_invoices')
            && $this->db->fieldExists('row_version', 'customs_invoices')
            && $this->db->tableExists('customs_invoice_versions')
            && $this->db->fieldExists('version_no', 'customs_invoice_versions')
            && $this->db->fieldExists('snapshot_hash', 'customs_invoice_versions')
            && $this->db->tableExists('customs_invoice_items')
            && $this->db->fieldExists('customs_invoice_version_id', 'customs_invoice_items')
            && $this->db->fieldExists('custom_description', 'customs_invoice_items')
            && $this->db->tableExists('customs_invoice_approvals')
            && $this->db->fieldExists('token_hash', 'customs_invoice_approvals')
            && $this->db->tableExists('customs_invoice_files')
            && $this->db->fieldExists('storage_path', 'customs_invoice_files');
    }

    private function recreateTargetSchema(): void
    {
        require_once __DIR__ . DIRECTORY_SEPARATOR . '2026-05-12-000201_CreateCustomsInvoiceTables.php';
        require_once __DIR__ . DIRECTORY_SEPARATOR . '2026-05-12-000202_AddCustomsInvoicePermissions.php';

        $createTables = new CreateCustomsInvoiceTables();
        $createTables->up();

        $permissions = new AddCustomsInvoicePermissions();
        $permissions->up();
    }
}