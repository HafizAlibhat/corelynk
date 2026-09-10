<?php

namespace App\Services;

/**
 * Copies a document row and its child rows verbatim.
 *
 * Copying stored rows (instead of replaying the create pipeline) is what makes
 * a duplicate exact: same lines, discounts, taxes, weights and totals as the
 * document on screen. Only identity/lifecycle fields are overridden by the
 * caller (number, dates, status, ownership).
 */
class DocumentDuplicator
{
    /** Never carried over: identity and row timestamps. */
    private const SKIP = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * @param array<string,mixed>  $overrides header columns to replace
     * @param array<string,string> $children  child table => foreign key column
     *
     * @return int id of the new document
     */
    public function duplicate(string $table, int $id, array $overrides = [], array $children = []): int
    {
        $db   = \Config\Database::connect();
        $cols = $db->getFieldNames($table);
        $row  = $db->table($table)->where('id', $id)->get()->getRowArray();
        if (! $row) {
            throw new \RuntimeException('Document not found.');
        }

        $header = array_merge($this->strip($row, $cols), array_intersect_key($overrides, array_flip($cols)));

        $db->transBegin();
        try {
            $db->table($table)->insert($header);
            $newId = (int) $db->insertID();
            if ($newId <= 0) {
                throw new \RuntimeException('Could not create the copy.');
            }

            foreach ($children as $childTable => $fk) {
                if (! $db->tableExists($childTable)) {
                    continue;
                }
                $childCols = $db->getFieldNames($childTable);
                $builder   = $db->table($childTable)->where($fk, $id);
                if (in_array('deleted_at', $childCols, true)) {
                    $builder->where("{$childTable}.deleted_at IS NULL", null, false);
                }
                $rows = [];
                foreach ($builder->orderBy('id', 'ASC')->get()->getResultArray() as $child) {
                    $rows[] = array_merge($this->strip($child, $childCols), [$fk => $newId]);
                }
                if ($rows) {
                    $db->table($childTable)->insertBatch($rows);
                }
            }

            $db->transCommit();

            return $newId;
        } catch (\Throwable $e) {
            $db->transRollback();

            throw $e;
        }
    }

    /** Drop identity/timestamp columns and stamp fresh timestamps where they exist. */
    private function strip(array $row, array $cols): array
    {
        $row = array_diff_key($row, array_flip(self::SKIP));
        $now = date('Y-m-d H:i:s');
        foreach (['created_at', 'updated_at'] as $ts) {
            if (in_array($ts, $cols, true)) {
                $row[$ts] = $now;
            }
        }

        return $row;
    }
}
