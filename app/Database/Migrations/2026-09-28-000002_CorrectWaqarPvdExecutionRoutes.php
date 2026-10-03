<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Waqar Plasma Coating is an outside process. Imported PVD profiles retained
 * an extra in-house option, causing the SO preparation modal to advertise a
 * route that cannot actually be performed in our factory.
 */
class CorrectWaqarPvdExecutionRoutes extends Migration
{
    private const BACKUP_TABLE = 'migration_20260928_waqar_pvd_routes';

    public function up()
    {
        if (! $this->db->tableExists('step_execution_options')) {
            return;
        }

        if (! $this->db->tableExists(self::BACKUP_TABLE)) {
            $this->forge->addField([
                'option_id' => ['type' => 'INT', 'unsigned' => true],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'is_default' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            ]);
            $this->forge->addKey('option_id', true);
            $this->forge->createTable(self::BACKUP_TABLE, true);
        }

        $matches = $this->db->query(
            "SELECT ih.id, ih.step_id
             FROM step_execution_options ih
             JOIN preparation_steps ps ON ps.id = ih.step_id
             JOIN step_execution_options vo ON vo.step_id = ps.id AND vo.execution_type = 'vendor'
             JOIN vendors v ON v.id = vo.vendor_id
             WHERE ih.execution_type = 'inhouse'
               AND ps.name LIKE 'Plasma Coating of % (PVD)'
               AND v.name = 'Waqar Plasma Coating'"
        )->getResultArray();

        $stepIds = array_values(array_unique(array_map(static fn ($row) => (int) $row['step_id'], $matches)));
        $options = $stepIds === [] ? [] : $this->db->table('step_execution_options')->whereIn('step_id', $stepIds)->get()->getResultArray();
        foreach ($options as $row) {
            $this->db->table(self::BACKUP_TABLE)->ignore(true)->insert([
                'option_id' => (int) $row['id'],
                'is_active' => (int) ($row['is_active'] ?? 1),
                'is_default' => (int) ($row['is_default'] ?? 0),
            ]);
        }

        foreach ($matches as $row) {
            $this->db->table('step_execution_options')->where('id', (int) $row['id'])->update([
                'is_active' => 0,
                'is_default' => 0,
            ]);
        }

        if ($stepIds !== []) {
            foreach ($stepIds as $stepId) {
                $this->db->table('step_execution_options')
                    ->where('step_id', $stepId)
                    ->where('execution_type', 'vendor')
                    ->where('vendor_id', $this->vendorId())
                    ->update(['is_default' => 1]);
            }
        }
    }

    public function down()
    {
        if (! $this->db->tableExists(self::BACKUP_TABLE)) {
            return;
        }

        foreach ($this->db->table(self::BACKUP_TABLE)->get()->getResultArray() as $row) {
            $this->db->table('step_execution_options')->where('id', (int) $row['option_id'])->update([
                'is_active' => (int) $row['is_active'],
                'is_default' => (int) $row['is_default'],
            ]);
        }
        $this->forge->dropTable(self::BACKUP_TABLE, true);
    }

    private function vendorId(): int
    {
        $row = $this->db->table('vendors')->select('id')->where('name', 'Waqar Plasma Coating')->get()->getRowArray();
        return (int) ($row['id'] ?? 0);
    }
}
