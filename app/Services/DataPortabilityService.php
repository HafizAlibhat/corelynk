<?php

namespace App\Services;

use Config\Database;
use Config\DataPortability;
use App\Models\DataPortabilityJobModel;
use CodeIgniter\Database\BaseConnection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use RuntimeException;

/** Internal signal thrown when a running job's cancel_requested flag is seen — not a real error. */
class DataPortabilityCancelledException extends RuntimeException
{
}

/**
 * Registry-driven export/import engine for business data (customers,
 * vendors, products, quotations, RFQs, purchase orders, GRNs, delivery
 * orders, sales orders, company settings). See app/Config/DataPortability.php
 * for the module registry this class reads.
 *
 * Export produces a single .xlsx workbook: one sheet per module (named by
 * module key) plus one sheet per child/line table, built via the same
 * atomic ".building"-suffix + rename + reload-verify pattern proven in
 * SystemBackupService, so a partially-written or corrupt export can never
 * be mistaken for a finished one.
 *
 * Import opens the workbook, rebuilds each module's rows from its sheet
 * (foreign-key columns hold the target row's business key, resolved back to
 * a local id at import time), then applies all
 * modules inside a single DB transaction — if anything fails, the whole
 * import rolls back, so the database is never left half-imported.
 */
class DataPortabilityService
{
    private DataPortabilityJobModel $jobModel;
    private BaseConnection $db;
    private string $exportRoot;

    /** @var array<string,int|null> module|key => resolved local id, memoized per run */
    private array $localIdCache = [];

    public function __construct()
    {
        $this->jobModel = new DataPortabilityJobModel();
        $this->db = Database::connect();
        $this->exportRoot = rtrim(WRITEPATH, '\\/') . DIRECTORY_SEPARATOR . 'data_exports';
        $this->ensureDirectory($this->exportRoot);
        $this->ensureDirectory($this->exportRoot . DIRECTORY_SEPARATOR . 'archives');
    }

    // ------------------------------------------------------------------
    // Job queueing / lifecycle
    // ------------------------------------------------------------------

    public function queueExport(array $moduleKeys, ?int $initiatedBy): array
    {
        $moduleKeys = $this->validateModuleKeys($moduleKeys);

        $jobId = $this->jobModel->insert([
            'public_id' => $this->uuid4(),
            'direction' => 'export',
            'status' => 'queued',
            'modules_json' => json_encode(array_values($moduleKeys)),
            'initiated_by' => $initiatedBy,
        ], true);

        if (!$jobId) {
            throw new RuntimeException('Unable to create export job record.');
        }

        return $this->jobModel->find((int) $jobId) ?? [];
    }

    /** Stages an uploaded package and queues a background import job. Call validateImportPackage() first. */
    public function queueImport(string $stagedZipPath, array $moduleKeys, ?int $initiatedBy): array
    {
        $moduleKeys = $this->validateModuleKeys($moduleKeys);

        if (!is_file($stagedZipPath)) {
            throw new RuntimeException('Staged import file is missing — please re-upload.');
        }

        $jobId = $this->jobModel->insert([
            'public_id' => $this->uuid4(),
            'direction' => 'import',
            'status' => 'queued',
            'modules_json' => json_encode(array_values($moduleKeys)),
            'archive_path' => $stagedZipPath,
            'initiated_by' => $initiatedBy,
        ], true);

        if (!$jobId) {
            throw new RuntimeException('Unable to create import job record.');
        }

        return $this->jobModel->find((int) $jobId) ?? [];
    }

    public function requestCancel(string $publicId): array
    {
        $job = $this->jobModel->where('public_id', $publicId)->first();
        if (!$job) {
            throw new RuntimeException('Job not found.');
        }
        if (!in_array((string) $job['status'], ['queued', 'running'], true)) {
            throw new RuntimeException('This job already finished — nothing to cancel.');
        }

        $this->jobModel->update((int) $job['id'], [
            'cancel_requested' => 1,
            'status' => (string) $job['status'] === 'queued' ? 'cancelled' : 'running',
            'progress_stage' => (string) $job['status'] === 'queued' ? 'cancelled' : (string) ($job['progress_stage'] ?? ''),
            'completed_at' => (string) $job['status'] === 'queued' ? date('Y-m-d H:i:s') : null,
        ]);

        return $this->jobModel->find((int) $job['id']) ?? [];
    }

    public function processQueuedJob(int $jobId): array
    {
        $job = $this->jobModel->find($jobId);
        if (!$job) {
            throw new RuntimeException('Data portability job not found.');
        }

        return $job['direction'] === 'import' ? $this->runImport($jobId) : $this->runExport($jobId);
    }

    /** Stages an uploaded zip into permanent storage so it survives past the upload request. */
    public function stageUpload(string $uploadedTmpPath): string
    {
        $stored = $this->exportRoot . DIRECTORY_SEPARATOR . 'incoming_' . $this->uuid4() . '.zip';
        if (!copy($uploadedTmpPath, $stored)) {
            throw new RuntimeException('Unable to stage uploaded import file.');
        }
        return $stored;
    }

    public function jobModel(): DataPortabilityJobModel
    {
        return $this->jobModel;
    }

    // ------------------------------------------------------------------
    // Export
    // ------------------------------------------------------------------

    private function runExport(int $jobId): array
    {
        $job = $this->jobModel->find($jobId);
        $moduleKeys = json_decode((string) ($job['modules_json'] ?? '[]'), true) ?: [];
        $moduleKeys = $this->orderByTier($moduleKeys);

        $timestamp = date('Ymd_His');
        $archiveDir = $this->exportRoot . DIRECTORY_SEPARATOR . 'archives';
        $archiveName = "corelynk_export_{$timestamp}.xlsx";
        $archivePath = $archiveDir . DIRECTORY_SEPARATOR . $archiveName;
        $buildingPath = $archivePath . '.building';

        $this->jobModel->update($jobId, [
            'status' => 'running', 'started_at' => date('Y-m-d H:i:s'), 'error_message' => null,
            'progress_percent' => 1, 'progress_stage' => 'starting',
        ]);

        try {
            $spreadsheet = new Spreadsheet();
            $spreadsheet->removeSheetByIndex(0);
            $spreadsheet->getProperties()->setCreator('CoreLynk')->setTitle('CoreLynk Data Export ' . $timestamp);

            $summary = [];
            $total = max(1, count($moduleKeys));
            foreach ($moduleKeys as $i => $moduleKey) {
                $this->assertNotCancelled($jobId);
                $this->updateProgress($jobId, 5 + (int) round(($i / $total) * 80), 'exporting_' . $moduleKey);

                $def = DataPortability::$modules[$moduleKey];
                $data = $this->exportModuleRows($moduleKey);
                $this->writeModuleSheets($spreadsheet, $moduleKey, $def, $data);
                $summary[$moduleKey] = ['label' => $def['label'], 'row_count' => count($data['rows'])];
            }

            if ($spreadsheet->getSheetCount() === 0) {
                $spreadsheet->createSheet()->setTitle('empty');
            }

            $this->assertNotCancelled($jobId);
            $this->updateProgress($jobId, 88, 'writing_file');
            $writer = new XlsxWriter($spreadsheet);
            $writer->save($buildingPath);

            $this->assertNotCancelled($jobId);
            $this->updateProgress($jobId, 96, 'verifying');
            try {
                IOFactory::load($buildingPath);
            } catch (\Throwable $e) {
                @unlink($buildingPath);
                throw new RuntimeException('Export file failed integrity verification — discarded, nothing was saved.');
            }

            if (!rename($buildingPath, $archivePath)) {
                throw new RuntimeException('Unable to finalize export file on disk.');
            }

            $archiveSha256 = hash_file('sha256', $archivePath);
            $this->jobModel->update($jobId, [
                'status' => 'completed',
                'archive_path' => $archivePath,
                'archive_name' => $archiveName,
                'archive_size_bytes' => filesize($archivePath) ?: 0,
                'archive_sha256' => $archiveSha256,
                'result_summary_json' => json_encode($summary),
                'completed_at' => date('Y-m-d H:i:s'),
                'progress_percent' => 100,
                'progress_stage' => 'completed',
            ]);
        } catch (DataPortabilityCancelledException $e) {
            @unlink($buildingPath);
            $this->jobModel->update($jobId, [
                'status' => 'cancelled', 'error_message' => 'Cancelled by user.',
                'completed_at' => date('Y-m-d H:i:s'), 'progress_stage' => 'cancelled',
            ]);
        } catch (\Throwable $e) {
            @unlink($buildingPath);
            $this->jobModel->update($jobId, [
                'status' => 'failed', 'error_message' => $e->getMessage(),
                'completed_at' => date('Y-m-d H:i:s'), 'progress_stage' => 'failed',
            ]);
            throw $e;
        }

        return $this->jobModel->find($jobId) ?? [];
    }

    /** @return array{rows: array<int,array>} */
    private function exportModuleRows(string $moduleKey): array
    {
        $def = DataPortability::$modules[$moduleKey];
        $table = $def['table'];

        if (!empty($def['singleton'])) {
            $row = $this->db->table($table)->get()->getRowArray();
            if (!$row) {
                return ['rows' => []];
            }
            unset($row['id']);
            return ['rows' => [['fields' => $row]]];
        }

        $fkColumns = array_keys($def['foreign_keys'] ?? []);
        $rows = $this->db->table($table)->get()->getResultArray();
        $out = [];

        foreach ($rows as $row) {
            $sourceId = (int) $row['id'];
            $fields = $row;
            unset($fields['id']);
            foreach ($fkColumns as $col) {
                unset($fields[$col]);
            }

            $refs = [];
            foreach ($def['foreign_keys'] ?? [] as $col => $targetModule) {
                $localVal = $row[$col] ?? null;
                $refs[$col] = $localVal !== null && $localVal !== ''
                    ? ['module' => $targetModule, 'key' => $this->lookupKeyValueForId($targetModule, (int) $localVal)]
                    : null;
            }

            $entry = ['_source_id' => $sourceId, 'fields' => $fields, 'refs' => $refs];

            if (!empty($def['children'])) {
                $entry['children'] = [];
                foreach ($def['children'] as $childTable => $childDef) {
                    $childRows = $this->db->table($childTable)->where($childDef['parent_fk'], $sourceId)->get()->getResultArray();
                    $childFk = array_keys($childDef['foreign_keys'] ?? []);
                    $lineFk = array_keys($childDef['line_foreign_keys'] ?? []);

                    $entry['children'][$childTable] = array_map(function ($crow) use ($childDef, $childFk, $lineFk) {
                        $childSourceId = (int) $crow['id'];
                        $cfields = $crow;
                        unset($cfields['id'], $cfields[$childDef['parent_fk']]);
                        foreach ($childFk as $col) {
                            unset($cfields[$col]);
                        }
                        foreach ($lineFk as $col) {
                            unset($cfields[$col]);
                        }

                        $crefs = [];
                        foreach ($childDef['foreign_keys'] ?? [] as $col => $targetModule) {
                            $localVal = $crow[$col] ?? null;
                            $crefs[$col] = $localVal !== null && $localVal !== ''
                                ? ['module' => $targetModule, 'key' => $this->lookupKeyValueForId($targetModule, (int) $localVal)]
                                : null;
                        }

                        $lineRefs = [];
                        foreach ($childDef['line_foreign_keys'] ?? [] as $col => $targetTable) {
                            $localVal = $crow[$col] ?? null;
                            $lineRefs[$col] = $localVal !== null && $localVal !== ''
                                ? ['table' => $targetTable, 'source_line_id' => (int) $localVal]
                                : null;
                        }

                        return ['_source_id' => $childSourceId, 'fields' => $cfields, 'refs' => $crefs, 'line_refs' => $lineRefs];
                    }, $childRows);
                }
            }

            $out[] = $entry;
        }

        return ['rows' => $out];
    }

    /** @var array<string,mixed> module:id => key value, memoized per run */
    private array $keyValueCache = [];

    private function lookupKeyValueForId(string $moduleKey, int $id): mixed
    {
        $cacheKey = $moduleKey . ':' . $id;
        if (array_key_exists($cacheKey, $this->keyValueCache)) {
            return $this->keyValueCache[$cacheKey];
        }

        $def = DataPortability::$modules[$moduleKey] ?? null;
        if (!$def || empty($def['key_column'])) {
            return $this->keyValueCache[$cacheKey] = null;
        }

        $row = $this->db->table($def['table'])->select($def['key_column'])->where('id', $id)->get()->getRowArray();
        return $this->keyValueCache[$cacheKey] = ($row[$def['key_column']] ?? null);
    }

    // ------------------------------------------------------------------
    // Import
    // ------------------------------------------------------------------

    /**
     * Opens an uploaded .xlsx workbook and decodes it back into the same
     * module-row shape the export builds, without writing anything to the
     * database. A module is only recognized if its sheet is present.
     *
     * @return array{manifest: array, moduleData: array<string,array>}
     */
    public function loadAndVerifyPackage(string $filePath, array $moduleKeys): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (\Throwable $e) {
            throw new RuntimeException('Import file failed to open — it may be corrupted or was not produced by CoreLynk (expected an .xlsx export).');
        }

        $moduleKeys = array_values(array_filter(
            $moduleKeys,
            fn ($key) => isset(DataPortability::$modules[$key]) && $spreadsheet->sheetNameExists($key)
        ));
        if (!$moduleKeys) {
            throw new RuntimeException('None of the selected modules have a matching sheet in this file.');
        }

        $moduleData = [];
        foreach ($moduleKeys as $moduleKey) {
            $def = DataPortability::$modules[$moduleKey];
            $rows = $this->sheetToRows($spreadsheet->getSheetByName($moduleKey), $def);

            if (!empty($def['children'])) {
                foreach ($def['children'] as $childTable => $childDef) {
                    if (!$spreadsheet->sheetNameExists($childTable)) {
                        continue;
                    }
                    $childRows = $this->sheetToRows($spreadsheet->getSheetByName($childTable), $childDef);
                    $byParent = [];
                    foreach ($childRows as $crow) {
                        $byParent[$crow['_parent_source_id']][] = $crow;
                    }
                    foreach ($rows as &$row) {
                        $row['children'][$childTable] = $byParent[$row['_source_id']] ?? [];
                    }
                    unset($row);
                }
            }

            $moduleData[$moduleKey] = ['rows' => $rows];
        }

        return ['manifest' => ['schema_version' => 1], 'moduleData' => $moduleData];
    }

    /** Writes one sheet per module (named by module key) plus one sheet per child table for line items. */
    private function writeModuleSheets(Spreadsheet $spreadsheet, string $moduleKey, array $def, array $data): void
    {
        $isSingleton = !empty($def['singleton']);
        $rows = [];
        foreach ($data['rows'] as $row) {
            $cols = $isSingleton ? [] : ['_source_id' => $row['_source_id']];
            foreach ($row['fields'] as $col => $val) {
                $cols[$col] = $val;
            }
            foreach ($row['refs'] ?? [] as $col => $ref) {
                $cols[$col] = $ref['key'] ?? null;
            }
            $rows[] = $cols;
        }
        $this->writeSheet($spreadsheet, $moduleKey, $rows);

        foreach ($def['children'] ?? [] as $childTable => $childDef) {
            $childRows = [];
            foreach ($data['rows'] as $row) {
                foreach ($row['children'][$childTable] ?? [] as $crow) {
                    $cols = ['_source_id' => $crow['_source_id'], '_parent_source_id' => $row['_source_id']];
                    foreach ($crow['fields'] as $col => $val) {
                        $cols[$col] = $val;
                    }
                    foreach ($crow['refs'] ?? [] as $col => $ref) {
                        $cols[$col] = $ref['key'] ?? null;
                    }
                    foreach ($crow['line_refs'] ?? [] as $col => $lref) {
                        $cols[$col] = $lref['source_line_id'] ?? null;
                    }
                    $childRows[] = $cols;
                }
            }
            $this->writeSheet($spreadsheet, $childTable, $childRows);
        }
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function writeSheet(Spreadsheet $spreadsheet, string $sheetName, array $rows): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(substr($sheetName, 0, 31));

        if (!$rows) {
            return;
        }

        $headers = array_keys($rows[0]);
        foreach ($headers as $c => $header) {
            $sheet->setCellValueExplicit([$c + 1, 1], $header, DataType::TYPE_STRING);
        }

        foreach ($rows as $r => $row) {
            foreach ($headers as $c => $header) {
                $val = $row[$header] ?? null;
                if ($val === null || $val === '') {
                    continue;
                }
                $sheet->setCellValueExplicit([$c + 1, $r + 2], (string) $val, DataType::TYPE_STRING);
            }
        }

        $sheet->freezePane('A2');
    }

    /** Reads a data sheet (header row + data rows) back into rows shaped for import, resolving fk/line-fk columns via $def. */
    private function sheetToRows(Worksheet $sheet, array $def): array
    {
        $fkColumns = $def['foreign_keys'] ?? [];
        $lineFkColumns = $def['line_foreign_keys'] ?? [];
        $highestRow = $sheet->getHighestDataRow();
        $highestCol = $sheet->getHighestDataColumn();

        $headers = [];
        foreach ($sheet->rangeToArray('A1:' . $highestCol . '1')[0] as $c => $header) {
            if ($header !== null && $header !== '') {
                $headers[$c] = (string) $header;
            }
        }

        $rows = [];
        for ($r = 2; $r <= $highestRow; $r++) {
            $cells = $sheet->rangeToArray('A' . $r . ':' . $highestCol . $r)[0];
            if (!array_filter($cells, fn ($v) => $v !== null && $v !== '')) {
                continue;
            }

            $sourceId = null;
            $parentSourceId = null;
            $fields = [];
            $refs = [];
            $lineRefs = [];

            foreach ($headers as $c => $header) {
                $val = $cells[$c] ?? null;
                $val = ($val === '') ? null : $val;

                if ($header === '_source_id') {
                    $sourceId = $val !== null ? (int) $val : null;
                } elseif ($header === '_parent_source_id') {
                    $parentSourceId = $val !== null ? (int) $val : null;
                } elseif (isset($fkColumns[$header])) {
                    $refs[$header] = $val !== null ? ['module' => $fkColumns[$header], 'key' => $val] : null;
                } elseif (isset($lineFkColumns[$header])) {
                    $lineRefs[$header] = $val !== null ? ['table' => $lineFkColumns[$header], 'source_line_id' => (int) $val] : null;
                } else {
                    $fields[$header] = $val;
                }
            }

            $rows[] = [
                '_source_id' => $sourceId,
                '_parent_source_id' => $parentSourceId,
                'fields' => $fields,
                'refs' => $refs,
                'line_refs' => $lineRefs,
            ];
        }

        return $rows;
    }

    /**
     * Read-only dry run: reports how many rows would be created/updated per
     * module and flags any foreign-key reference that can't be resolved,
     * without writing anything. Used to render a confirmation screen before
     * a real import runs.
     */
    public function previewImport(string $zipPath, array $moduleKeys): array
    {
        $moduleKeys = $this->validateModuleKeys($moduleKeys);
        $package = $this->loadAndVerifyPackage($zipPath, $moduleKeys);
        $orderedKeys = $this->orderByTier(array_keys($package['moduleData']));

        // Keys bundled in this package "will exist" by the time later tiers import,
        // even if they don't exist in the target DB yet.
        $bundledKeys = [];
        foreach ($orderedKeys as $moduleKey) {
            $def = DataPortability::$modules[$moduleKey];
            if (empty($def['key_column'])) {
                continue;
            }
            foreach ($package['moduleData'][$moduleKey]['rows'] as $row) {
                $val = $row['fields'][$def['key_column']] ?? null;
                if ($val !== null) {
                    $bundledKeys[$moduleKey][$val] = true;
                }
            }
        }

        $result = [];
        foreach ($orderedKeys as $moduleKey) {
            $def = DataPortability::$modules[$moduleKey];
            $rows = $package['moduleData'][$moduleKey]['rows'];
            $create = 0;
            $update = 0;
            $unresolved = 0;
            $missingModules = [];

            foreach ($rows as $row) {
                if (!empty($def['singleton'])) {
                    $update++;
                    continue;
                }
                $keyValue = $row['fields'][$def['key_column']] ?? null;
                $existing = $keyValue !== null ? $this->resolveLocalId($moduleKey, $keyValue) : null;
                $existing ? $update++ : $create++;

                foreach ($row['refs'] ?? [] as $col => $ref) {
                    if (!$ref || $ref['key'] === null) {
                        continue;
                    }
                    $resolvable = $this->resolveLocalId($ref['module'], $ref['key']) !== null
                        || !empty($bundledKeys[$ref['module']][$ref['key']] ?? false);
                    if (!$resolvable) {
                        $unresolved++;
                        $missingModules[$ref['module']] = DataPortability::$modules[$ref['module']]['label'] ?? $ref['module'];
                    }
                }
            }

            $result[$moduleKey] = [
                'label' => $def['label'],
                'create' => $create,
                'update' => $update,
                'unresolved_refs' => $unresolved,
                'missing_modules' => array_values($missingModules),
            ];
        }

        return $result;
    }

    private function runImport(int $jobId): array
    {
        $job = $this->jobModel->find($jobId);
        $moduleKeys = json_decode((string) ($job['modules_json'] ?? '[]'), true) ?: [];
        $zipPath = (string) ($job['archive_path'] ?? '');

        $this->jobModel->update($jobId, [
            'status' => 'running', 'started_at' => date('Y-m-d H:i:s'), 'error_message' => null,
            'progress_percent' => 1, 'progress_stage' => 'validating',
        ]);

        $lineIdMap = [];
        $summary = [];

        try {
            $package = $this->loadAndVerifyPackage($zipPath, $moduleKeys);
            $orderedKeys = $this->orderByTier(array_keys($package['moduleData']));

            $this->assertNotCancelled($jobId);
            $this->db->transStart();

            $total = max(1, count($orderedKeys));
            foreach ($orderedKeys as $i => $moduleKey) {
                $this->assertNotCancelled($jobId);
                $this->updateProgress($jobId, 10 + (int) round(($i / $total) * 80), 'importing_' . $moduleKey);
                $summary[$moduleKey] = $this->importModuleRows($moduleKey, $package['moduleData'][$moduleKey], $lineIdMap);
            }

            $this->db->transComplete();
            if ($this->db->transStatus() === false) {
                $dbError = trim((string) ($this->db->error()['message'] ?? ''));
                $hint = '';
                if ($dbError !== '' && stripos($dbError, 'duplicate entry') !== false) {
                    $hint = ' This usually means an existing record is missing its internal reference id (public_id) so it could not be matched to the row being imported — run "php spark security:backfill-public-ids", then re-export and re-import.';
                }
                throw new RuntimeException(
                    'Import failed and was rolled back — nothing was written.'
                    . ($dbError !== '' ? ' Database said: ' . $dbError : '')
                    . $hint
                );
            }

            $this->jobModel->update($jobId, [
                'status' => 'completed',
                'result_summary_json' => json_encode($summary),
                'progress_percent' => 100,
                'progress_stage' => 'completed',
                'completed_at' => date('Y-m-d H:i:s'),
            ]);

            // Staged upload no longer needed once successfully imported.
            if ($zipPath && is_file($zipPath)) {
                @unlink($zipPath);
            }
        } catch (DataPortabilityCancelledException $e) {
            $this->db->transRollback();
            $this->jobModel->update($jobId, [
                'status' => 'cancelled', 'error_message' => 'Cancelled by user — no changes were written.',
                'completed_at' => date('Y-m-d H:i:s'), 'progress_stage' => 'cancelled',
            ]);
        } catch (\Throwable $e) {
            $this->db->transRollback();
            $this->jobModel->update($jobId, [
                'status' => 'failed', 'error_message' => $e->getMessage(),
                'completed_at' => date('Y-m-d H:i:s'), 'progress_stage' => 'failed',
            ]);
            throw $e;
        }

        return $this->jobModel->find($jobId) ?? [];
    }

    /** @return array{created:int,updated:int} */
    private function importModuleRows(string $moduleKey, array $moduleData, array &$lineIdMap): array
    {
        $def = DataPortability::$modules[$moduleKey];
        $table = $def['table'];
        $created = 0;
        $updated = 0;

        foreach ($moduleData['rows'] as $row) {
            $fields = $row['fields'];

            if (!empty($def['singleton'])) {
                $exists = $this->db->table($table)->select('id')->get()->getRowArray();
                if ($exists) {
                    $this->assertWriteOk($this->db->table($table)->where('id', $exists['id'])->update($fields), $table);
                } else {
                    $this->assertWriteOk($this->db->table($table)->insert($fields), $table);
                }
                $updated++;
                continue;
            }

            foreach ($def['foreign_keys'] ?? [] as $col => $targetModule) {
                $ref = $row['refs'][$col] ?? null;
                if (!$ref || $ref['key'] === null) {
                    $fields[$col] = null;
                    continue;
                }
                $localId = $this->resolveLocalId($targetModule, $ref['key']);
                if ($localId === null) {
                    $targetLabel = DataPortability::$modules[$targetModule]['label'] ?? $targetModule;
                    $moduleLabel = $def['label'] ?? $moduleKey;
                    throw new RuntimeException("This file contains {$moduleLabel} that reference {$targetLabel} missing in the system — please import {$targetLabel} first.");
                }
                $fields[$col] = $localId;
            }

            $keyColumn = $def['key_column'];
            $keyValue = $fields[$keyColumn] ?? null;
            $existingId = $keyValue !== null ? $this->resolveLocalId($moduleKey, $keyValue) : null;

            if ($existingId) {
                $this->assertWriteOk($this->db->table($table)->where('id', $existingId)->update($fields), $table);
                $localRowId = $existingId;
                $updated++;
            } else {
                $this->assertWriteOk($this->db->table($table)->insert($fields), $table);
                $localRowId = (int) $this->db->insertID();
                $created++;
            }

            if ($keyValue !== null) {
                $this->cacheLocalId($moduleKey, $keyValue, $localRowId);
            }

            foreach ($def['children'] ?? [] as $childTable => $childDef) {
                $this->db->table($childTable)->where($childDef['parent_fk'], $localRowId)->delete();

                foreach (($row['children'][$childTable] ?? []) as $crow) {
                    $cfields = $crow['fields'];
                    $cfields[$childDef['parent_fk']] = $localRowId;

                    foreach ($childDef['foreign_keys'] ?? [] as $col => $targetModule) {
                        $cref = $crow['refs'][$col] ?? null;
                        $cfields[$col] = ($cref && $cref['key'] !== null) ? $this->resolveLocalId($targetModule, $cref['key']) : null;
                    }

                    foreach ($childDef['line_foreign_keys'] ?? [] as $col => $targetTable) {
                        $lref = $crow['line_refs'][$col] ?? null;
                        $cfields[$col] = ($lref && $lref['source_line_id'] !== null)
                            ? ($lineIdMap[$targetTable . ':' . $lref['source_line_id']] ?? null)
                            : null;
                    }

                    $this->assertWriteOk($this->db->table($childTable)->insert($cfields), $childTable);
                    $newChildId = (int) $this->db->insertID();

                    if (isset($crow['_source_id'])) {
                        $lineIdMap[$childTable . ':' . $crow['_source_id']] = $newChildId;
                    }
                }
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * Query Builder's insert()/update() return false on failure instead of
     * throwing when DBDebug is off, so the real MySQL error must be read
     * immediately — by the time transComplete() runs, later successful
     * queries have already overwritten the connection's error state.
     */
    private function assertWriteOk(mixed $result, string $table): void
    {
        if ($result !== false) {
            return;
        }

        $error = $this->db->error();
        $message = trim((string) ($error['message'] ?? ''));
        $hint = '';
        if (stripos($message, 'duplicate entry') !== false) {
            $hint = ' This usually means an existing record is missing its internal reference id (public_id) so it could not be matched to the row being imported — run "php spark security:backfill-public-ids", then re-export and re-import.';
        }
        throw new RuntimeException(
            'Database write to "' . $table . '" failed'
            . ($message !== '' ? ': ' . $message : ' (unknown database error).')
            . $hint
        );
    }

    private function resolveLocalId(string $moduleKey, mixed $keyValue): ?int
    {
        $cacheKey = $moduleKey . '|' . $keyValue;
        if (array_key_exists($cacheKey, $this->localIdCache)) {
            return $this->localIdCache[$cacheKey];
        }

        $def = DataPortability::$modules[$moduleKey] ?? null;
        if (!$def || empty($def['key_column'])) {
            return $this->localIdCache[$cacheKey] = null;
        }

        $row = $this->db->table($def['table'])->select('id')->where($def['key_column'], $keyValue)->get()->getRowArray();
        return $this->localIdCache[$cacheKey] = ($row['id'] ?? null) !== null ? (int) $row['id'] : null;
    }

    private function cacheLocalId(string $moduleKey, mixed $keyValue, int $localId): void
    {
        $this->localIdCache[$moduleKey . '|' . $keyValue] = $localId;
    }

    // ------------------------------------------------------------------
    // Shared helpers
    // ------------------------------------------------------------------

    /** @return string[] */
    private function validateModuleKeys(array $moduleKeys): array
    {
        $valid = array_keys(DataPortability::$modules);
        $moduleKeys = array_values(array_unique(array_intersect($moduleKeys, $valid)));
        if (!$moduleKeys) {
            throw new RuntimeException('No valid modules were selected.');
        }
        return $moduleKeys;
    }

    /** @return string[] */
    private function orderByTier(array $moduleKeys): array
    {
        $moduleKeys = array_values(array_intersect($moduleKeys, array_keys(DataPortability::$modules)));
        usort($moduleKeys, static fn ($a, $b) => (DataPortability::$modules[$a]['tier'] ?? 99) <=> (DataPortability::$modules[$b]['tier'] ?? 99));
        return $moduleKeys;
    }

    private function updateProgress(int $jobId, int $percent, string $stage): void
    {
        try {
            $this->jobModel->update($jobId, [
                'progress_percent' => max(0, min(100, $percent)),
                'progress_stage' => $stage,
            ]);
        } catch (\Throwable $e) {
            // Progress reporting must never break the actual export/import.
        }
    }

    private function assertNotCancelled(int $jobId): void
    {
        $job = $this->jobModel->select('cancel_requested')->find($jobId);
        if ($job && (int) ($job['cancel_requested'] ?? 0) === 1) {
            throw new DataPortabilityCancelledException('Cancelled by user.');
        }
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }
    }

    private function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
