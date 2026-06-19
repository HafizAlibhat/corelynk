<?php

namespace App\Services;

use App\Models\SystemSyncEnvironmentModel;
use App\Models\SystemSyncScanModel;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class SystemSyncService
{
    private SystemSyncEnvironmentModel $environmentModel;
    private SystemSyncScanModel $scanModel;
    private string $reportRoot;

    public function __construct()
    {
        $this->environmentModel = new SystemSyncEnvironmentModel();
        $this->scanModel = new SystemSyncScanModel();
        $this->reportRoot = rtrim(WRITEPATH, '\\/') . DIRECTORY_SEPARATOR . 'sync-reports';
        $this->ensureDirectory($this->reportRoot);
    }

    public function runScan(int $sourceEnvironmentId, int $destinationEnvironmentId, ?int $createdBy = null): array
    {
        if ($sourceEnvironmentId === $destinationEnvironmentId) {
            throw new RuntimeException('Source and destination environments must be different.');
        }

        $source = $this->environmentModel->find($sourceEnvironmentId);
        $destination = $this->environmentModel->find($destinationEnvironmentId);
        if (!$source || !$destination) {
            throw new RuntimeException('Sync environment profile was not found.');
        }

        $this->assertValidEnvironment($source);
        $this->assertValidEnvironment($destination);

        $fileDiff = $this->scanFiles($source, $destination);
        $schemaDiff = $this->scanSchema($source, $destination);

        $report = [
            'generated_at' => date('c'),
            'source' => [
                'id' => (int) $source['id'],
                'name' => (string) $source['name'],
                'app_path' => (string) $source['app_path'],
                'db_name' => (string) $source['db_name'],
            ],
            'destination' => [
                'id' => (int) $destination['id'],
                'name' => (string) $destination['name'],
                'app_path' => (string) $destination['app_path'],
                'db_name' => (string) $destination['db_name'],
            ],
            'files' => $fileDiff,
            'schema' => $schemaDiff,
        ];

        $summary = [
            'file_copy_count' => count($fileDiff['copy_candidates']),
            'file_conflict_count' => count($fileDiff['destination_only']),
            'table_create_count' => count($schemaDiff['missing_tables_in_destination']),
            'column_add_count' => count($schemaDiff['missing_columns_in_destination']),
            'index_add_count' => count($schemaDiff['missing_indexes_in_destination']),
            'manual_review_count' => count($schemaDiff['manual_review_items']),
        ];

        $safeOperations = [
            'schema_sql' => $schemaDiff['safe_sql_operations'],
            'file_copy_candidates' => $fileDiff['copy_candidates'],
        ];

        $publicId = $this->uuid4();
        $reportPath = $this->reportRoot . DIRECTORY_SEPARATOR . 'sync_scan_' . date('Ymd_His') . '_' . $publicId . '.json';
        file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $scanId = $this->scanModel->insert([
            'public_id' => $publicId,
            'source_env_id' => (int) $source['id'],
            'destination_env_id' => (int) $destination['id'],
            'status' => 'scanned',
            'summary_json' => json_encode($summary, JSON_UNESCAPED_SLASHES),
            'safe_operations_json' => json_encode($safeOperations, JSON_UNESCAPED_SLASHES),
            'report_path' => $reportPath,
            'created_by' => $createdBy,
        ], true);

        if (!$scanId) {
            throw new RuntimeException('Unable to save sync scan report.');
        }

        return $this->scanModel->find((int) $scanId) ?? [];
    }

    public function applyScan(string $scanPublicId, ?int $appliedBy = null, bool $backupConfirmed = false): array
    {
        if (!$backupConfirmed) {
            throw new RuntimeException('Backup confirmation is required before applying sync updates.');
        }

        $scan = $this->scanModel->where('public_id', $scanPublicId)->first();
        if (!$scan) {
            throw new RuntimeException('Sync scan report not found.');
        }
        if (($scan['status'] ?? '') !== 'scanned') {
            throw new RuntimeException('Only scanned reports can be applied.');
        }
        if (empty($scan['report_path']) || !is_file((string) $scan['report_path'])) {
            throw new RuntimeException('Sync report file is missing.');
        }

        $report = json_decode((string) file_get_contents((string) $scan['report_path']), true);
        if (!is_array($report)) {
            throw new RuntimeException('Sync report is invalid JSON.');
        }

        $source = $this->environmentModel->find((int) $scan['source_env_id']);
        $destination = $this->environmentModel->find((int) $scan['destination_env_id']);
        if (!$source || !$destination) {
            throw new RuntimeException('Source or destination environment profile was not found.');
        }

        $fileResults = $this->applyFileOperations($report['files']['copy_candidates'] ?? [], (string) $source['app_path'], (string) $destination['app_path']);
        $schemaResults = $this->applySchemaOperations($report['schema']['safe_sql_operations'] ?? [], $destination);

        $this->scanModel->update((int) $scan['id'], [
            'status' => 'applied',
            'applied_by' => $appliedBy,
            'applied_at' => date('Y-m-d H:i:s'),
            'error_message' => null,
        ]);

        return [
            'scan_public_id' => $scanPublicId,
            'file_results' => $fileResults,
            'schema_results' => $schemaResults,
        ];
    }

    private function scanFiles(array $source, array $destination): array
    {
        $sourcePath = rtrim((string) $source['app_path'], '\\/');
        $destinationPath = rtrim((string) $destination['app_path'], '\\/');

        $sourceFiles = $this->collectFiles($sourcePath);
        $destinationFiles = $this->collectFiles($destinationPath);

        $copyCandidates = [];
        $destinationOnly = [];

        foreach ($sourceFiles as $relative => $sourceFile) {
            $destinationFile = $destinationFiles[$relative] ?? null;
            if ($destinationFile === null) {
                $copyCandidates[] = [
                    'relative_path' => $relative,
                    'reason' => 'missing_in_destination',
                    'source_sha1' => $sourceFile['sha1'],
                ];
                continue;
            }

            if ($destinationFile['sha1'] !== $sourceFile['sha1']) {
                $copyCandidates[] = [
                    'relative_path' => $relative,
                    'reason' => 'content_differs',
                    'source_sha1' => $sourceFile['sha1'],
                    'destination_sha1' => $destinationFile['sha1'],
                ];
            }
        }

        foreach ($destinationFiles as $relative => $destinationFile) {
            if (!isset($sourceFiles[$relative])) {
                $destinationOnly[] = [
                    'relative_path' => $relative,
                    'destination_sha1' => $destinationFile['sha1'],
                ];
            }
        }

        return [
            'copy_candidates' => $copyCandidates,
            'destination_only' => $destinationOnly,
        ];
    }

    private function scanSchema(array $source, array $destination): array
    {
        $sourceDb = $this->connectDatabase($source);
        $destinationDb = $this->connectDatabase($destination);

        $sourceTables = $this->getTableList($sourceDb, (string) $source['db_name']);
        $destinationTables = $this->getTableList($destinationDb, (string) $destination['db_name']);

        $missingTables = array_values(array_diff($sourceTables, $destinationTables));
        $destinationOnlyTables = array_values(array_diff($destinationTables, $sourceTables));

        $missingColumns = [];
        $missingIndexes = [];
        $manualReviewItems = [];
        $safeSql = [];

        foreach ($missingTables as $table) {
            $createSql = $this->getCreateTableSql($sourceDb, $table);
            if ($createSql !== null) {
                $safeSql[] = $createSql;
            }
        }

        $commonTables = array_values(array_intersect($sourceTables, $destinationTables));
        foreach ($commonTables as $table) {
            $sourceColumns = $this->getColumnSnapshot($sourceDb, (string) $source['db_name'], $table);
            $destinationColumns = $this->getColumnSnapshot($destinationDb, (string) $destination['db_name'], $table);

            foreach ($sourceColumns as $columnName => $column) {
                if (!isset($destinationColumns[$columnName])) {
                    $missingColumns[] = [
                        'table' => $table,
                        'column' => $columnName,
                    ];
                    $safeSql[] = $this->buildAddColumnSql($table, $column);
                    continue;
                }

                if (!$this->isSameColumnDefinition($column, $destinationColumns[$columnName])) {
                    $manualReviewItems[] = [
                        'type' => 'column_definition_differs',
                        'table' => $table,
                        'column' => $columnName,
                    ];
                }
            }

            $sourceIndexes = $this->getIndexSnapshot($sourceDb, $table);
            $destinationIndexes = $this->getIndexSnapshot($destinationDb, $table);

            foreach ($sourceIndexes as $indexName => $indexParts) {
                if ($indexName === 'PRIMARY') {
                    continue;
                }

                if (!isset($destinationIndexes[$indexName])) {
                    $missingIndexes[] = [
                        'table' => $table,
                        'index' => $indexName,
                    ];
                    $safeSql[] = $this->buildCreateIndexSql($table, $indexName, $indexParts);
                }
            }
        }

        return [
            'missing_tables_in_destination' => $missingTables,
            'destination_only_tables' => $destinationOnlyTables,
            'missing_columns_in_destination' => $missingColumns,
            'missing_indexes_in_destination' => $missingIndexes,
            'manual_review_items' => $manualReviewItems,
            'safe_sql_operations' => $safeSql,
        ];
    }

    private function applyFileOperations(array $operations, string $sourceRoot, string $destinationRoot): array
    {
        $copied = 0;
        $sourceRoot = rtrim($sourceRoot, '\\/');
        $destinationRoot = rtrim($destinationRoot, '\\/');

        foreach ($operations as $operation) {
            $relativePath = (string) ($operation['relative_path'] ?? '');
            if ($relativePath === '') {
                continue;
            }

            $sourceFile = $sourceRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $destinationFile = $destinationRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

            $destinationDir = dirname($destinationFile);
            $this->ensureDirectory($destinationDir);

            if (!is_file($sourceFile)) {
                throw new RuntimeException('Source file is missing while applying sync: ' . $relativePath);
            }
            if (!@copy($sourceFile, $destinationFile)) {
                throw new RuntimeException('Failed copying file during sync: ' . $relativePath);
            }

            $sourceHash = sha1_file($sourceFile) ?: '';
            $destinationHash = sha1_file($destinationFile) ?: '';
            if ($sourceHash === '' || $sourceHash !== $destinationHash) {
                throw new RuntimeException('Checksum mismatch after copying: ' . $relativePath);
            }

            $copied++;
        }

        return [
            'copied_file_count' => $copied,
        ];
    }

    private function applySchemaOperations(array $sqlOperations, array $destination): array
    {
        $db = $this->connectDatabase($destination);
        $executed = 0;

        foreach ($sqlOperations as $sql) {
            $query = trim((string) $sql);
            if ($query === '') {
                continue;
            }
            if (!$db->query($query)) {
                throw new RuntimeException('Schema sync failed: ' . $db->error);
            }
            $executed++;
        }

        return [
            'executed_sql_count' => $executed,
        ];
    }

    private function collectFiles(string $rootPath): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $absolutePath = $item->getPathname();
            $relative = ltrim(substr($absolutePath, strlen($rootPath)), '\\/');
            if ($relative === '' || $this->isExcludedFile($relative)) {
                continue;
            }

            $hash = sha1_file($absolutePath);
            if ($hash === false) {
                continue;
            }

            $relative = str_replace('\\', '/', $relative);
            $files[$relative] = [
                'sha1' => $hash,
                'size' => $item->getSize(),
            ];
        }

        ksort($files);
        return $files;
    }

    private function isExcludedFile(string $relativePath): bool
    {
        $normalized = str_replace('/', '\\', $relativePath);
        $excludedPrefixes = [
            '.git\\',
            'writable\\',
            'vendor\\',
            'node_modules\\',
            'archives\\',
            'tmp\\',
            'public\\uploads\\',
            'public\\null\\',
        ];

        foreach ($excludedPrefixes as $prefix) {
            if (stripos($normalized, $prefix) === 0) {
                return true;
            }
        }

        $excludedFiles = [
            '.env',
            'composer.lock',
        ];

        foreach ($excludedFiles as $excluded) {
            if (strcasecmp($normalized, str_replace('/', '\\', $excluded)) === 0) {
                return true;
            }
        }

        return false;
    }

    private function connectDatabase(array $environment): \mysqli
    {
        $host = (string) ($environment['db_host'] ?? '127.0.0.1');
        $port = (int) ($environment['db_port'] ?? 3306);
        $user = (string) ($environment['db_user'] ?? 'root');
        $password = (string) ($environment['db_password'] ?? '');
        $name = (string) ($environment['db_name'] ?? '');

        $mysqli = new \mysqli($host, $user, $password, $name, $port);
        if ($mysqli->connect_errno) {
            throw new RuntimeException('Unable to connect to ' . $name . ': ' . $mysqli->connect_error);
        }

        $mysqli->set_charset('utf8mb4');
        return $mysqli;
    }

    private function getTableList(\mysqli $db, string $databaseName): array
    {
        $tables = [];
        $sql = "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE='BASE TABLE'";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Failed preparing table list query.');
        }

        $stmt->bind_param('s', $databaseName);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $tables[] = (string) $row['TABLE_NAME'];
        }
        $stmt->close();

        sort($tables);
        return $tables;
    }

    private function getCreateTableSql(\mysqli $db, string $table): ?string
    {
        $safeTable = str_replace('`', '``', $table);
        $result = $db->query('SHOW CREATE TABLE `' . $safeTable . '`');
        if (!$result) {
            return null;
        }

        $row = $result->fetch_assoc();
        if (!is_array($row)) {
            return null;
        }

        return (string) ($row['Create Table'] ?? null);
    }

    private function getColumnSnapshot(\mysqli $db, string $databaseName, string $table): array
    {
        $columns = [];
        $sql = "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, CHARACTER_SET_NAME, COLLATION_NAME, COLUMN_COMMENT
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                ORDER BY ORDINAL_POSITION";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Failed preparing column snapshot query.');
        }

        $stmt->bind_param('ss', $databaseName, $table);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $name = (string) $row['COLUMN_NAME'];
            $columns[$name] = [
                'name' => $name,
                'column_type' => (string) $row['COLUMN_TYPE'],
                'is_nullable' => (string) $row['IS_NULLABLE'],
                'column_default' => $row['COLUMN_DEFAULT'],
                'extra' => (string) $row['EXTRA'],
                'character_set_name' => $row['CHARACTER_SET_NAME'],
                'collation_name' => $row['COLLATION_NAME'],
                'column_comment' => (string) ($row['COLUMN_COMMENT'] ?? ''),
            ];
        }
        $stmt->close();

        return $columns;
    }

    private function getIndexSnapshot(\mysqli $db, string $table): array
    {
        $safeTable = str_replace('`', '``', $table);
        $result = $db->query('SHOW INDEX FROM `' . $safeTable . '`');
        if (!$result) {
            return [];
        }

        $indexes = [];
        while ($row = $result->fetch_assoc()) {
            $name = (string) $row['Key_name'];
            if (!isset($indexes[$name])) {
                $indexes[$name] = [
                    'non_unique' => (int) $row['Non_unique'],
                    'index_type' => (string) $row['Index_type'],
                    'columns' => [],
                ];
            }

            $indexes[$name]['columns'][(int) $row['Seq_in_index']] = [
                'column_name' => (string) $row['Column_name'],
                'sub_part' => $row['Sub_part'] !== null ? (int) $row['Sub_part'] : null,
            ];
        }

        foreach ($indexes as $name => $index) {
            ksort($index['columns']);
            $indexes[$name] = $index;
        }

        return $indexes;
    }

    private function buildAddColumnSql(string $table, array $column): string
    {
        $sql = 'ALTER TABLE `' . str_replace('`', '``', $table) . '` ADD COLUMN `' . str_replace('`', '``', (string) $column['name']) . '` ' . (string) $column['column_type'];

        if (!empty($column['character_set_name'])) {
            $sql .= ' CHARACTER SET ' . $column['character_set_name'];
        }
        if (!empty($column['collation_name'])) {
            $sql .= ' COLLATE ' . $column['collation_name'];
        }

        $sql .= strtoupper((string) $column['is_nullable']) === 'NO' ? ' NOT NULL' : ' NULL';

        if ($column['column_default'] !== null) {
            $default = (string) $column['column_default'];
            $upperDefault = strtoupper($default);
            if (in_array($upperDefault, ['CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP()'], true)) {
                $sql .= ' DEFAULT CURRENT_TIMESTAMP';
            } else {
                $sql .= " DEFAULT '" . $this->escapeSqlString($default) . "'";
            }
        }

        if (!empty($column['extra'])) {
            $sql .= ' ' . strtoupper((string) $column['extra']);
        }

        if (!empty($column['column_comment'])) {
            $sql .= " COMMENT '" . $this->escapeSqlString((string) $column['column_comment']) . "'";
        }

        return $sql;
    }

    private function buildCreateIndexSql(string $table, string $indexName, array $indexParts): string
    {
        $columns = [];
        foreach ($indexParts['columns'] as $part) {
            $columnSql = '`' . str_replace('`', '``', (string) $part['column_name']) . '`';
            if ($part['sub_part'] !== null) {
                $columnSql .= '(' . (int) $part['sub_part'] . ')';
            }
            $columns[] = $columnSql;
        }

        $unique = ((int) ($indexParts['non_unique'] ?? 1) === 0) ? 'UNIQUE ' : '';
        $indexType = strtoupper((string) ($indexParts['index_type'] ?? 'BTREE'));

        return 'CREATE ' . $unique . 'INDEX `' . str_replace('`', '``', $indexName) . '` ON `' . str_replace('`', '``', $table) . '` (' . implode(', ', $columns) . ') USING ' . $indexType;
    }

    private function isSameColumnDefinition(array $source, array $destination): bool
    {
        $keys = ['column_type', 'is_nullable', 'column_default', 'extra', 'character_set_name', 'collation_name'];
        foreach ($keys as $key) {
            $sourceValue = $source[$key] ?? null;
            $destinationValue = $destination[$key] ?? null;
            if ((string) $sourceValue !== (string) $destinationValue) {
                return false;
            }
        }

        return true;
    }

    private function assertValidEnvironment(array $environment): void
    {
        $path = rtrim((string) ($environment['app_path'] ?? ''), '\\/');
        if ($path === '' || !is_dir($path)) {
            throw new RuntimeException('Environment path does not exist: ' . $path);
        }

        if (empty($environment['db_name'])) {
            throw new RuntimeException('Environment database name is required.');
        }
    }

    private function escapeSqlString(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !@mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('Unable to create directory: ' . $path);
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
